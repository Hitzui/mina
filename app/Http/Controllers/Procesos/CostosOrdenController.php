<?php

namespace App\Http\Controllers\Procesos;

use App\DataTables\CostosOrdenDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Concerns\ValidaCostos;
use App\Http\Controllers\Controller;
use App\Models\MovimientosCosto;
use App\Models\OrdenesTrabajo;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * Costos de la orden que no son de un proceso concreto.
 *
 * Son los que se llevan con proceso_orden_id en NULL: alquiler, transporte,
 * un insumo que no corresponde a un proceso. Los que si son de un proceso se
 * registran desde la pantalla de ese proceso, no desde aqui, para que un
 * mismo costo no se pueda cargar en los dos sitios.
 */
class CostosOrdenController extends Controller
{
    use AuthorizesModule;
    use ValidaCostos;

    public function __construct()
    {
        $this->authorizeModule('movimientos_costo');
    }

    public function index(OrdenesTrabajo $ordenTrabajo, CostosOrdenDataTable $dataTable)
    {
        $dataTable->setOrdenTrabajoId($ordenTrabajo->id);

        return $dataTable->ajax();
    }

    /**
     * El costo se registra desde el modal de la pantalla de la orden.
     * Esta ruta se conserva para que la url no de error.
     */
    public function create(OrdenesTrabajo $ordenTrabajo)
    {
        return redirect()->route(
            'procesos.ordenes_trabajo.show',
            $ordenTrabajo
        );
    }

    public function store(Request $request, OrdenesTrabajo $ordenTrabajo)
    {
        $validado = $this->validarCosto($request);

        // null: es un gasto de la orden, no de un proceso
        [$costo, $tipoCambio] = $this->armarCosto(
            $validado,
            $ordenTrabajo->id,
            null
        );

        $costo->validarProcesoPertenece();
        $costo->save();

        return redirect()
            ->route('procesos.ordenes_trabajo.show', $ordenTrabajo)
            ->with('success', $this->mensaje($costo, $tipoCambio));
    }

    public function show(OrdenesTrabajo $ordenTrabajo, MovimientosCosto $costo)
    {
        abort_unless($this->esDeEstaPantalla($ordenTrabajo, $costo), 404);

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

    public function edit(OrdenesTrabajo $ordenTrabajo, MovimientosCosto $costo)
    {
        abort_unless($this->esDeEstaPantalla($ordenTrabajo, $costo), 404);

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
        MovimientosCosto $costo
    ) {
        abort_unless($this->esDeEstaPantalla($ordenTrabajo, $costo), 404);

        $validado = $this->validarCosto($request, $costo->id);

        // Se completa el mismo movimiento, no uno nuevo: si se creara
        // otro, save() insertaria una fila nueva y la vieja se quedaria
        // colgando sin que nadie la borrara
        [$costo, $tipoCambio] = $this->armarCosto(
            $validado,
            $ordenTrabajo->id,
            null,
            $costo
        );

        $costo->validarProcesoPertenece();
        $costo->save();

        Alert::toast('Costo actualizado correctamente.')->success()->flash();

        return redirect()->route(
            'procesos.ordenes_trabajo.show',
            $ordenTrabajo
        );
    }

    public function destroy(
        Request $request,
        OrdenesTrabajo $ordenTrabajo,
        MovimientosCosto $costo
    ) {
        abort_unless($this->esDeEstaPantalla($ordenTrabajo, $costo), 404);

        $costo->delete();

        $mensaje = 'El costo fue eliminado de la orden.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $mensaje,
            ]);
        }

        Alert::toast($mensaje)->success()->flash();

        return redirect()->route(
            'procesos.ordenes_trabajo.show',
            $ordenTrabajo
        );
    }

    /**
     * El movimiento tiene que ser de esta orden y no ser de ningun
     * proceso. Sin esto se podria borrar el costo de un proceso escribiendo
     * la url de la orden.
     */
    private function esDeEstaPantalla(
        OrdenesTrabajo $ordenTrabajo,
        MovimientosCosto $costo
    ): bool {
        return (int) $costo->orden_trabajo_id === $ordenTrabajo->id
            && $costo->proceso_orden_id === null;
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
