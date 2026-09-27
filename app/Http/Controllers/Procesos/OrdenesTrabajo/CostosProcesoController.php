<?php

namespace App\Http\Controllers\Procesos\OrdenesTrabajo;

use App\DataTables\CostosProcesoDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Concerns\OrdenCerrada;
use App\Http\Controllers\Concerns\ValidaCostos;
use App\Http\Controllers\Controller;
use App\Models\MovimientosCosto;
use App\Models\OrdenesTrabajo;
use App\Models\ProcesosOrden;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * Costos de un proceso: energia, agua, materia prima y demas conceptos que
 * se cargan a mano.
 *
 * Van colgados del proceso porque es el que sabe de que orden se trata, y
 * la orden se comprueba para que no se pueda meter el costo de un proceso
 * en otra orden escribiendo la url.
 */
class CostosProcesoController extends Controller
{
    use AuthorizesModule;
    use OrdenCerrada;
    use ValidaCostos;

    public function __construct()
    {
        $this->authorizeModule('movimientos_costo');
    }

    public function index(
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden,
        CostosProcesoDataTable $dataTable
    ) {
        $this->validarProcesoPertenece($ordenTrabajo, $procesoOrden);

        $dataTable->setProcesoOrdenId($procesoOrden->id);
        $dataTable->setOrdenTrabajoId($ordenTrabajo->id);

        return $dataTable->ajax();
    }

    /**
     * El costo se registra desde el modal de la pantalla del proceso.
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
        ProcesosOrden $procesoOrden
    ) {
        if ($bloqueo = $this->bloquearOrdenCerrada($ordenTrabajo, 'un costo')) {
            return $bloqueo;
        }

        $this->validarProcesoPertenece($ordenTrabajo, $procesoOrden);

        $validado = $this->validarCosto($request);

        [$costo, $tipoCambio] = $this->armarCosto(
            $validado,
            $ordenTrabajo->id,
            $procesoOrden->id
        );

        $costo->validarProcesoPertenece();
        $costo->save();

        return redirect()
            ->route(
                'procesos.ordenes_trabajo.procesos.show',
                [$ordenTrabajo, $procesoOrden]
            )
            ->with('success', $this->mensaje($costo, $tipoCambio));
    }

    public function show(
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden,
        MovimientosCosto $costo
    ) {
        $this->validarCostoProceso($ordenTrabajo, $procesoOrden, $costo);

        $costo->load(['categoria_costo', 'moneda']);

        return response()->json([
            'id' => $costo->id,
            'categoria' => $costo->categoria_costo?->nombre ?? '—',
            'fecha' => $costo->fecha?->format('d/m/Y'),
            'descripcion' => $costo->descripcion,
            'cantidad' => $costo->cantidad,
            'costo_unitario' => $costo->costo_unitario,
            'costo_total' => $costo->costo_total,
            'moneda' => $costo->moneda?->codigo ?? '—',
            'costo_unitario_nio' => $costo->costo_unitario_nio,
            'costo_total_nio' => $costo->costo_total_nio,
            'observaciones' => $costo->observaciones ?? '',
        ]);
    }

    public function edit(
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden,
        MovimientosCosto $costo
    ) {
        $this->validarCostoProceso($ordenTrabajo, $procesoOrden, $costo);

        $costo->load(['categoria_costo', 'moneda']);

        return response()->json([
            'id' => $costo->id,
            'categoria_costo_id' => $costo->categoria_costo_id,
            'fecha' => $costo->fecha?->format('Y-m-d'),
            'descripcion' => $costo->descripcion,
            'cantidad' => $costo->cantidad,
            'costo_unitario' => $costo->costo_unitario,
            'costo_total' => $costo->costo_total,
            'moneda_id' => $costo->moneda_id,
            'costo_unitario_nio' => $costo->costo_unitario_nio,
            'costo_total_nio' => $costo->costo_total_nio,
            'observaciones' => $costo->observaciones ?? '',
        ]);
    }

    public function update(
        Request $request,
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden,
        MovimientosCosto $costo
    ) {
        $this->validarCostoProceso($ordenTrabajo, $procesoOrden, $costo);

        $validado = $this->validarCosto($request, $costo->id);

        [$costo, $tipoCambio] = $this->armarCosto(
            $validado,
            $ordenTrabajo->id,
            $procesoOrden->id,
            $costo
        );

        $costo->validarProcesoPertenece();
        $costo->save();

        Alert::toast('Costo actualizado correctamente.')->success()->flash();

        return redirect()->route(
            'procesos.ordenes_trabajo.procesos.show',
            [$ordenTrabajo, $procesoOrden]
        );
    }

    public function destroy(
        Request $request,
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden,
        MovimientosCosto $costo
    ) {
        $this->validarCostoProceso($ordenTrabajo, $procesoOrden, $costo);

        $costo->delete();

        $mensaje = 'El costo fue eliminado del proceso.';

        /*
         * La confirmacion la resuelve realrashid/sweet-alert con
         * data-confirm-delete, que envia un formulario normal. Si esto
         * devolviera JSON, el navegador mostraria el JSON crudo. El JSON
         * se queda solo para llamadas AJAX reales.
         */
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $mensaje,
            ]);
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
     * El costo de la url tiene que ser de ese proceso y de esa orden.
     *
     * Sin las dos comprobaciones se podria tocar el costo de otro proceso
     * escribiendo su id en la url, y el costo apareceria en un proceso que
     * no lo gasto.
     */
    private function validarCostoProceso(
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden,
        MovimientosCosto $costo
    ): void {
        $this->validarProcesoPertenece($ordenTrabajo, $procesoOrden);

        abort_unless(
            (int) $costo->proceso_orden_id === $procesoOrden->id
                && (int) $costo->orden_trabajo_id === $ordenTrabajo->id,
            404
        );
    }

    private function mensaje(MovimientosCosto $costo, ?float $tipoCambio): string
    {
        $mensaje = 'Costo registrado correctamente: '
            . number_format($costo->costo_total, 2)
            . '.';

        if ($tipoCambio === null) {
            $mensaje .= ' No se encontró tipo de cambio para la moneda y la '
                . 'fecha indicadas; el equivalente en NIO quedó vacío.';
        }

        return $mensaje;
    }
}
