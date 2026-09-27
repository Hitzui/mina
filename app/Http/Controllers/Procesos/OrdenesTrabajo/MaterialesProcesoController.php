<?php

namespace App\Http\Controllers\Procesos\OrdenesTrabajo;

use App\DataTables\MaterialesProcesoDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Concerns\ValidaMovimientos;
use App\Http\Controllers\Controller;
use App\Models\MovimientosInventario;
use App\Models\OrdenesTrabajo;
use App\Models\ProcesosOrden;
use App\Services\InventarioService;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * La materia prima que se consume en un proceso: el cemento y los quimicos
 * de la etapa de Pilas, o de cualquiera otra.
 *
 * El consumo se registra aqui y no en la pantalla general del almacen
 * porque es el proceso quien lo gasta: la fila queda imputada a el, y su
 * costo entra en el desglose del proceso como concepto automatico. Si se
 * registrara desde el almacen sin proceso, el material saldria del stock
 * pero no se cargaria a ninguna etapa, que es justo el problema que hay
 * que resolver.
 */
class MaterialesProcesoController extends Controller
{
    use AuthorizesModule;
    use ValidaMovimientos;

    public function __construct()
    {
        $this->authorizeModule('movimientos_inventario');
    }

    public function index(
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden,
        MaterialesProcesoDataTable $dataTable
    ) {
        $this->validarProcesoPertenece($ordenTrabajo, $procesoOrden);

        $dataTable->setProcesoOrdenId($procesoOrden->id);
        $dataTable->setOrdenTrabajoId($ordenTrabajo->id);

        return $dataTable->ajax();
    }

    /**
     * El consumo se registra desde el modal de la pantalla del proceso.
     * Esta ruta se conserva para que la url no de error.
     */
    public function create(OrdenesTrabajo $ordenTrabajo, ProcesosOrden $procesoOrden)
    {
        $this->validarProcesoPertenece($ordenTrabajo, $procesoOrden);

        return redirect()->route(
            'procesos.ordenes_trabajo.procesos.show',
            [$ordenTrabajo, $procesoOrden]
        );
    }

    public function store(
        Request $request,
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden,
        InventarioService $inventario
    ) {
        $this->validarProcesoPertenece($ordenTrabajo, $procesoOrden);

        $validado = $this->validarMovimiento($request);

        /*
         * El tipo lo pone la pantalla, no el formulario: aqui solo se
         * consumen materiales. Si llegara "entrada", se meteria material
         * en el almacen imputandolo a un proceso, que no significa nada y
         * dejaria el costo del proceso sin tocar.
         */
        $validado['tipo'] = MovimientosInventario::TIPO_SALIDA;
        $validado['orden_trabajo_id'] = $ordenTrabajo->id;
        $validado['proceso_orden_id'] = $procesoOrden->id;

        /*
         * Los dos identificadores se toman de la url, y validarProcesoPertenece
         * ya ha comprobado que el proceso es de esa orden. Por eso la fila
         * sale consistente sin volver a preguntarlo despues: comprobandolo
         * ahora, despues de haber movido el stock, un fallo dejaria el
         * almacen cambiado sin consumo registrado.
         */
        $movimiento = $inventario->registrar($validado);

        Alert::toast($this->mensaje($movimiento))->success()->flash();

        return redirect()->route(
            'procesos.ordenes_trabajo.procesos.show',
            [$ordenTrabajo, $procesoOrden]
        );
    }

    /**
     * El consumo se borra desde la tabla de la pantalla del proceso, asi
     * que esta ruta solo existe para que la url no de error.
     */
    public function destroy(
        Request $request,
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden,
        MovimientosInventario $movimiento,
        InventarioService $inventario
    ) {
        $this->validarConsumoPertenece($ordenTrabajo, $procesoOrden, $movimiento);

        /*
         * Al borrar, el material vuelve al almacen. Es lo que hace el
         * servicio, y es la razon de que el borrado no sea un delete seco:
         * si solo se quitara la fila, el almacen quedaria corto de algo que
         * sigue dentro y el siguiente consumo se rechazaria sin explicacion.
         */
        $inventario->revertir($movimiento);

        $mensaje = 'Consumo eliminado. Las existencias se corrigieron solas.';

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $mensaje]);
        }

        Alert::toast($mensaje)->success()->flash();

        return redirect()->route(
            'procesos.ordenes_trabajo.procesos.show',
            [$ordenTrabajo, $procesoOrden]
        );
    }

    /**
     * El proceso de la url tiene que ser de la orden de la url.
     */
    private function validarProcesoPertenece(
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden
    ): void {
        abort_unless(
            (int) $procesoOrden->orden_trabajo_id === $ordenTrabajo->id,
            404
        );
    }

    /**
     * El consumo de la url tiene que ser de ese proceso y de esa orden.
     *
     * Sin las dos comprobaciones se podrian tocar consumos de otro proceso
     * escribiendo su id en la url, y el material volveria al almacen
     * equivocado.
     */
    private function validarConsumoPertenece(
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden,
        MovimientosInventario $movimiento
    ): void {
        $this->validarProcesoPertenece($ordenTrabajo, $procesoOrden);

        abort_unless(
            (int) $movimiento->proceso_orden_id === $procesoOrden->id
                && (int) $movimiento->orden_trabajo_id === $ordenTrabajo->id,
            404
        );
    }

    private function mensaje(MovimientosInventario $movimiento): string
    {
        $cantidad = rtrim(
            rtrim(number_format((float) $movimiento->cantidad, 3), '0'),
            '.'
        );

        $unidad = $movimiento->producto?->unidad_medida ?? '';

        $mensaje = sprintf(
            'Consumo registrado: %s%s de %s. Costo del proceso: %s.',
            $cantidad,
            $unidad !== '' ? ' ' . $unidad : '',
            $movimiento->producto?->nombre ?? 'material',
            number_format((float) $movimiento->costo_total_nio, 2)
        );

        /*
         * El costo unitario sale del promedio del almacen, no de la
         * pantalla. Se avisa cuando sale en cero, porque significaria que no
         * habia valor cargado en el stock y el proceso se llevaria el
         * material gratis sin que nada lo dijera.
         */
        if ((float) $movimiento->costo_unitario <= 0) {
            $mensaje .= ' Ojo: el material no tiene costo cargado en el almacen, '
                . 'asi que este consumo suma cero al costo del proceso.';
        }

        return $mensaje;
    }
}
