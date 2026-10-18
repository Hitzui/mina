<?php

namespace App\Http\Controllers\Procesos;

use App\Http\Controllers\Controller;
use App\Models\{LamaLote, Moneda, OrdenesTrabajo, Recuperaciones};
use App\Services\{LamaService, ValoracionOroService};
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class LamaController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:ordenes_trabajo.view');
        $this->middleware('permission:ordenes_trabajo.edit')->only(['store', 'update', 'destroy', 'iniciar']);
        $this->middleware('permission:ordenes_trabajo.create')->only('iniciar');
        $this->middleware('permission:recuperaciones.create')->only('recuperar');
        $this->middleware('permission:valoraciones_oro.create|valoraciones_oro.edit')->only('valorar');
    }

    public function index()
    {
        return view('procesos.lamas.index', ['title' => 'Lama y tratamientos',
            'lotes' => LamaLote::with(['origen.cliente', 'tratamiento'])->orderByDesc('id')->paginate(25)]);
    }

    public function show(Request $request, OrdenesTrabajo $ordenTrabajo, LamaService $service)
    {
        $data = $service->datos($ordenTrabajo, $request->user());
        if ($request->expectsJson()) return response()->json(['data' => $data]);
        return view('procesos.lamas.gestion', ['title' => 'Lama · '.$ordenTrabajo->codigo, 'ordenTrabajo' => $ordenTrabajo, 'data' => $data]);
    }

    public function store(Request $request, OrdenesTrabajo $ordenTrabajo, LamaService $service)
    {
        $service->registrar($ordenTrabajo, $request->validate(LamaService::reglasLote()));
        return $this->volver($request, $ordenTrabajo, 'Lote registrado con cantidad estimada y condiciones del acuerdo.');
    }

    public function update(Request $request, OrdenesTrabajo $ordenTrabajo, LamaLote $lote, LamaService $service)
    {
        $this->pertenece($ordenTrabajo, $lote);
        $datos = $request->validate([
            'cantidad_estimada' => LamaService::reglasLote()['cantidad_estimada'],
            'ubicacion' => ['required', 'string', 'max:200'], 'observaciones' => ['nullable', 'string', 'max:2000'],
        ]);
        $service->corregir($lote, $datos);
        return $this->volver($request, $ordenTrabajo, 'Cantidad estimada y ubicación corregidas.');
    }

    public function destroy(Request $request, OrdenesTrabajo $ordenTrabajo, LamaLote $lote, LamaService $service)
    {
        $this->pertenece($ordenTrabajo, $lote);
        $service->anular($lote);
        return $this->volver($request, $ordenTrabajo, 'Lote anulado. Puede registrarlo nuevamente con los datos correctos.');
    }

    public function iniciar(Request $request, OrdenesTrabajo $ordenTrabajo, LamaLote $lote, LamaService $service)
    {
        $this->pertenece($ordenTrabajo, $lote);
        $datos = $request->validate(['fecha' => ['required', 'date']]);
        $nueva = $service->iniciar($lote, $datos['fecha']);
        if ($request->expectsJson()) return response()->json(['data' => $service->datos($nueva, $request->user()), 'treatment_id' => $nueva->id], 201);
        Alert::toast('OT de tratamiento creada. Agregue sus procesos, trabajo y consumos.')->success()->flash();
        return redirect()->route('procesos.ordenes_trabajo.lama.show', $nueva);
    }

    public function recuperar(Request $request, OrdenesTrabajo $ordenTrabajo)
    {
        abort_unless($ordenTrabajo->lama_lote_id, 422, 'Abra la OT de tratamiento para registrar el oro que produjo la lama.');
        $request->validate(['fecha' => ['required', 'date', 'after_or_equal:'.$ordenTrabajo->fecha->toDateString()]]);
        $request->merge(['orden_trabajo_id' => $ordenTrabajo->id]);
        app(RecuperacionController::class)->store($request);
        return $this->volver($request, $ordenTrabajo, 'Recuperación registrada. Ahora puede valorar el oro.');
    }

    public function valorar(Request $request, OrdenesTrabajo $ordenTrabajo, ValoracionOroService $service)
    {
        abort_unless($ordenTrabajo->lama_lote_id, 422, 'Valore el oro desde la OT de tratamiento.');
        $id = $request->validate(['recuperacion_id' => ['required', 'integer']])['recuperacion_id'];
        $recuperacion = Recuperaciones::with('valoracion')->where('orden_trabajo_id', $ordenTrabajo->id)->findOrFail($id);
        $request->validate(['fecha' => ['required', 'date', 'after_or_equal:'.$recuperacion->fecha->toDateString()]]);
        $existente = $recuperacion->valoracion;
        abort_unless($request->user()->can($existente ? 'valoraciones_oro.edit' : 'valoraciones_oro.create'), 403);
        $base = Moneda::base();
        abort_unless($base, 422, 'Configure la moneda base antes de valorar.');
        $request->merge(['moneda_id' => $base->id]);
        if ($existente) {
            app(ValoracionOroController::class)->update($request, $existente, $service);
        } else {
            app(ValoracionOroController::class)->store($request, $service);
        }
        return $this->volver($request, $ordenTrabajo, 'Oro valorado. El reparto se muestra según las condiciones históricas del lote.');
    }

    private function pertenece(OrdenesTrabajo $orden, LamaLote $lote): void
    {
        abort_unless($lote->orden_origen_id == $orden->id, 404);
    }

    private function volver(Request $request, OrdenesTrabajo $orden, string $mensaje)
    {
        if ($request->expectsJson()) return response()->json(['data' => app(LamaService::class)->datos($orden, $request->user())]);
        Alert::toast($mensaje)->success()->flash();
        return redirect()->route('procesos.ordenes_trabajo.lama.show', $orden);
    }
}
