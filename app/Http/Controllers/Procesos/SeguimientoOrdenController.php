<?php

namespace App\Http\Controllers\Procesos;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ValidaCostos;
use App\Models\{EquipoReparacion, OrdenesTrabajo, ProcesoEquipo};
use App\Services\SeguimientoOrdenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SeguimientoOrdenController extends Controller
{
    use ValidaCostos;

    public function __construct()
    {
        $this->middleware('permission:ordenes_trabajo.view');
        $this->middleware('permission:proceso_equipo.view')->except('show');
        $this->middleware('permission:proceso_equipo.edit')->only(['store', 'update', 'destroy', 'ampliar']);
        $this->middleware('permission:movimientos_costo.create')->only('costo');
    }

    public function show(Request $request, OrdenesTrabajo $ordenTrabajo)
    {
        $data = $this->datos($request, $ordenTrabajo);
        if ($request->boolean('summary')) return response()->json(['html' => view('procesos.seguimiento._resumen', ['ordenTrabajo' => $ordenTrabajo, 'seguimiento' => $data])->render()]);
        if ($request->expectsJson()) return response()->json(['data' => $data]);
        return view('procesos.seguimiento.show', ['ordenTrabajo' => $ordenTrabajo, 'seguimiento' => $data]);
    }

    public function datos(Request $request, OrdenesTrabajo $ordenTrabajo): array
    {
        $data = app(SeguimientoOrdenService::class)->datos($ordenTrabajo, $request->user());
        if ($data['can_cost']) {
            $combos = $this->datosDeCombos();
            $data['categories'] = $combos['categorias']->map(fn ($c) => ['id' => $c->id, 'name' => $c->nombre])->values();
            $data['currencies'] = $combos['monedas']->map(fn ($m) => ['id' => $m->id, 'name' => $m->codigo])->values();
        }
        return $data;
    }

    public function store(Request $request, OrdenesTrabajo $ordenTrabajo)
    {
        $r = app(SeguimientoOrdenService::class)->guardar($ordenTrabajo, $this->validar($request));
        return $this->respuesta($request, $ordenTrabajo, $r, 201);
    }

    public function update(Request $request, OrdenesTrabajo $ordenTrabajo, EquipoReparacion $reparacion)
    {
        $this->pertenece($ordenTrabajo, $reparacion);
        $r = app(SeguimientoOrdenService::class)->guardar($ordenTrabajo, $this->validar($request, true), $reparacion);
        return $this->respuesta($request, $ordenTrabajo, $r);
    }

    public function destroy(Request $request, OrdenesTrabajo $ordenTrabajo, EquipoReparacion $reparacion)
    {
        $this->pertenece($ordenTrabajo, $reparacion);
        $datos = $request->validate(['expected_version' => ['required', 'integer', 'min:1']]);
        app(SeguimientoOrdenService::class)->anular($reparacion, $datos['expected_version']);
        return $this->respuesta($request, $ordenTrabajo);
    }

    public function ampliar(Request $request, OrdenesTrabajo $ordenTrabajo, ProcesoEquipo $asignacion)
    {
        abort_unless((int) $asignacion->procesoOrden->orden_trabajo_id === $ordenTrabajo->id, 404);
        $datos = $request->validate(['ended_at' => ['required', 'date'], 'expected_end' => ['present', 'nullable', 'date']]);
        app(SeguimientoOrdenService::class)->ampliar($asignacion, $datos);
        return $this->respuesta($request, $ordenTrabajo, id: $asignacion->id);
    }

    /** El costo es un movimiento normal, una sola vez; el intervalo no genera dinero. */
    public function costo(Request $request, OrdenesTrabajo $ordenTrabajo, EquipoReparacion $reparacion)
    {
        $this->pertenece($ordenTrabajo, $reparacion);
        abort_if($ordenTrabajo->estaCerrada(), 422, 'La OT está cerrada.');
        abort_if($reparacion->asignacion->procesoOrden->estaCerrado(), 422, 'El proceso está cerrado. Reábralo para registrar costos nuevos.');
        // Esta pantalla recibe un importe global, nunca un desglose por cantidad/precio.
        if ($request->exists('total_cost')) $request->merge(['costo_unitario' => $request->input('total_cost')]);
        $request->merge(['cantidad' => 1]);
        $validado = $this->validarCosto($request);
        $costo = DB::transaction(function () use ($validado, $ordenTrabajo, $reparacion) {
            $r = EquipoReparacion::lockForUpdate()->findOrFail($reparacion->id);
            [$costo] = $this->armarCosto($validado, $ordenTrabajo->id, $r->asignacion->proceso_orden_id);
            $costo->reparacion_id = $r->id;
            $costo->validarProcesoPertenece();
            $costo->save();
            return $costo;
        });
        return $this->respuesta($request, $ordenTrabajo, id: $costo->id, status: 201);
    }

    private function validar(Request $request, bool $editar = false): array
    {
        return $request->validate([
            'assignment_id' => [$editar ? 'sometimes' : 'required', 'integer'],
            'started_at' => ['required', 'date'], 'ended_at' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:1000'],
            'repair_location' => ['sometimes', 'nullable', 'in:interna,externa'],
            'work_description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'expected_version' => [$editar ? 'required' : 'sometimes', 'integer', 'min:1'],
        ]);
    }

    private function pertenece(OrdenesTrabajo $orden, EquipoReparacion $r): void
    {
        abort_unless((int) $r->asignacion?->procesoOrden?->orden_trabajo_id === $orden->id, 404);
    }

    private function respuesta(Request $request, OrdenesTrabajo $orden, ?EquipoReparacion $r = null, int $status = 200, ?int $id = null)
    {
        if ($request->expectsJson()) return response()->json(['data' => ['id' => $r?->id ?? $id, 'version' => $r?->version]], $status);
        return redirect()->route('procesos.ordenes_trabajo.seguimiento.show', $orden)->with('success', 'Cambio guardado. El plazo y las horas de horómetro solo cambian cuando usted los modifica.');
    }
}
