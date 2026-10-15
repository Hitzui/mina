<?php

namespace App\Http\Controllers\Procesos\OrdenesTrabajo;

use App\DataTables\ProcesoEquiposDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Concerns\OrdenCerrada;
use App\Http\Controllers\Controller;
use App\Models\Equipo;
use App\Models\OrdenesTrabajo;
use App\Models\ProcesoEquipo;
use App\Models\ProcesosOrden;
use App\Services\UsoEquipoService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * Equipos usados en un proceso, con su periodo de uso.
 *
 * Un equipo puede trabajar en varios procesos seguidos, nunca en el mismo
 * instante. Eso se comprueba en el modelo al guardar, no aqui: la regla
 * necesita ver los otros periodos del mismo equipo y es mejor que viva
 * junto al dato que la protege, para que ningun camino la esquive.
 *
 * La depreciacion se calcula y se guarda al registrar el uso. Es la foto
 * del valor de ese uso: si manana se corrige la vida util del equipo, el
 * costo de este proceso no se mueve.
 */
class ProcesoEquipoController extends Controller
{
    use AuthorizesModule;
    use OrdenCerrada;

    public function __construct()
    {
        $this->authorizeModule('proceso_equipo');
        $this->middleware('permission:proceso_equipo.view')->only(['gestion', 'depreciacion']);
        $this->middleware('permission:proceso_equipo.edit')->only(['trabajo', 'consumir', 'anularConsumo']);
        $this->middleware('permission:movimientos_inventario.create')->only('consumir');
    }

    public function index(
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden,
        ProcesoEquiposDataTable $dataTable
    ) {
        $this->validarProcesoPertenece($ordenTrabajo, $procesoOrden);

        $dataTable->setProcesoOrdenId($procesoOrden->id);
        $dataTable->setOrdenTrabajoId($ordenTrabajo->id);

        return $dataTable->ajax();
    }

    /**
     * El uso de un equipo se registra desde el modal de la pantalla del
     * proceso. Esta ruta se conserva para que la url no de error.
     */
    public function create(OrdenesTrabajo $ordenTrabajo, ProcesosOrden $procesoOrden)
    {
        $this->validarProcesoPertenece($ordenTrabajo, $procesoOrden);

        return redirect()->route(
            'procesos.ordenes_trabajo.procesos.show',
            [$ordenTrabajo, $procesoOrden]
        );
    }

    public function store(Request $request, OrdenesTrabajo $ordenTrabajo, ProcesosOrden $procesoOrden)
    {
        if ($bloqueo = $this->bloquearOrdenCerrada($ordenTrabajo, 'el uso de un equipo')) {
            return $bloqueo;
        }

        $this->validarProcesoPertenece($ordenTrabajo, $procesoOrden);

        if ($bloqueo = $this->bloquearProcesoCerrado($ordenTrabajo, $procesoOrden, 'el uso de un equipo')) {
            return $bloqueo;
        }

        $validated = $this->validarUso($request);

        // El proceso viene de la ruta, nunca del formulario
        $asignacion = new ProcesoEquipo();
        $asignacion->proceso_orden_id = $procesoOrden->id;
        $asignacion = app(UsoEquipoService::class)->guardar($asignacion, $validated);

        Alert::toast(
            'Equipo asignado. '.$asignacion->estadoCosteo().'. Costo registrado: NIO '
            . number_format($asignacion->costo_total_nio, 2)
            . '.'
        )->success()->flash();

        return $this->volverAlProceso($request, $ordenTrabajo, $procesoOrden);
    }

    public function show(
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden,
        ProcesoEquipo $procesoEquipo
    ) {
        $this->validarAsignacionProceso($ordenTrabajo, $procesoOrden, $procesoEquipo);

        $procesoEquipo->load('equipo');

        return response()->json([
            'id' => $procesoEquipo->id,
            'equipo' => $procesoEquipo->equipo?->nombre ?? '—',
            'codigo' => $procesoEquipo->equipo?->codigo ?? '—',
            'proceso' => $procesoOrden->nombre_completo,
            'fecha_inicio' => $procesoEquipo->fecha_inicio?->format('Y-m-d\TH:i'),
            'fecha_fin' => $procesoEquipo->fecha_fin?->format('Y-m-d\TH:i'),
            'dias' => round($procesoEquipo->diasDeUso(), 4),
            'horas_efectivas' => $procesoEquipo->horas_efectivas,
            'estado_costeo' => $procesoEquipo->estadoCosteo(),
            'costo_insumos_nio' => $procesoEquipo->costo_insumos_nio,
            'depreciacion_diaria' => round(
                $procesoEquipo->depreciacion_hora_nio !== null
                    ? $procesoEquipo->depreciacion_hora_nio * 24
                    : (float) $procesoEquipo->equipo?->depreciacionDiaria(),
                4
            ),
            'depreciacion_total' => (float) $procesoEquipo->depreciacion_total,
            'metodo_costo' => $procesoEquipo->metodo_costo,
            'potencia_kw_usada' => $procesoEquipo->potencia_kw_usada,
            'tarifa_kwh_nio' => $procesoEquipo->tarifa_kwh_nio,
            'energia_kwh' => (float) $procesoEquipo->energia_kwh,
            'costo_energia_nio' => (float) $procesoEquipo->costo_energia_nio,
            'costo_total_nio' => $procesoEquipo->costo_uso_nio,
            'observaciones' => $procesoEquipo->observaciones ?? '',
        ]);
    }

    public function edit(
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden,
        ProcesoEquipo $procesoEquipo
    ) {
        $this->validarAsignacionProceso($ordenTrabajo, $procesoOrden, $procesoEquipo);

        $procesoEquipo->load('equipo');

        return response()->json([
            'id' => $procesoEquipo->id,
            'equipo_id' => $procesoEquipo->equipo_id,
            'horas_efectivas' => $procesoEquipo->horas_efectivas,
            'horometro_inicial' => $procesoEquipo->horometro_inicial,
            'horometro_final' => $procesoEquipo->horometro_final,
            'equipo' => $procesoEquipo->equipo?->nombre ?? '—',
            'fecha_inicio' => $procesoEquipo->fecha_inicio?->format('Y-m-d\TH:i'),
            'fecha_fin' => $procesoEquipo->fecha_fin?->format('Y-m-d\TH:i'),
            'observaciones' => $procesoEquipo->observaciones ?? '',
        ]);
    }

    public function update(
        Request $request,
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden,
        ProcesoEquipo $procesoEquipo
    ) {
        $this->validarAsignacionProceso($ordenTrabajo, $procesoOrden, $procesoEquipo);

        $validated = $this->validarUso($request);

        app(UsoEquipoService::class)->guardar($procesoEquipo, $validated);

        Alert::toast('Uso del equipo actualizado correctamente.')->success()->flash();

        return $this->volverAlProceso($request, $ordenTrabajo, $procesoOrden);
    }

    public function destroy(
        Request $request,
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden,
        ProcesoEquipo $procesoEquipo
    ) {
        $this->validarAsignacionProceso($ordenTrabajo, $procesoOrden, $procesoEquipo);

        if ($procesoEquipo->consumos()->withTrashed()->exists() || $procesoEquipo->entregas()->exists()) {
            throw ValidationException::withMessages(['equipo_id' => 'Este uso tiene historial de insumos y debe conservarse.']);
        }
        $procesoEquipo->delete();

        $mensaje = 'El uso del equipo fue eliminado del proceso.';

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
     * Cuanto costaria usar un equipo entre dos fechas.
     *
     * El formulario lo pregunta al elegir el equipo y las fechas, para
     * enseñar la depreciacion antes de guardar y no despues.
     */
    public function depreciacion(
        Request $request,
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden
    ) {
        $this->validarProcesoPertenece($ordenTrabajo, $procesoOrden);

        $validated = $request->validate([
            ...UsoEquipoService::reglasHoras(),
            'equipo_id' => ['required', 'integer', 'exists:equipos,id'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after:fecha_inicio'],
            'proceso_equipo_id' => ['nullable', 'integer'],
        ]);

        $equipo = Equipo::with('asignaciones')->findOrFail($validated['equipo_id']);

        $periodo = isset($validated['proceso_equipo_id'])
            ? ProcesoEquipo::where('proceso_orden_id', $procesoOrden->id)->findOrFail($validated['proceso_equipo_id'])
            : new ProcesoEquipo();
        $periodo->equipo_id = $equipo->id;
        $periodo->fecha_inicio = $validated['fecha_inicio'];
        $periodo->fecha_fin = $validated['fecha_fin'] ?? null;
        $periodo->fill($validated);
        $periodo->validarPeriodo();

        // Se comprueba tambien el solapamiento aqui, para poder avisar
        // antes de que el usuario pulse guardar
        $solapado = null;

        try {
            $periodo->validarSinSolapamiento();
        } catch (ValidationException $e) {
            $solapado = $e->errors()[ProcesoEquipo::FECHA_INICIO][0] ?? null;
        }

        $periodo = app(UsoEquipoService::class)->guardar($periodo, $validated, false);
        $tarifa = $periodo->tarifa_kwh_nio;
        $energiaRequerida = $periodo->potencia_kw_usada > 0;

        return response()->json([
            'codigo' => $equipo->codigo,
            'valor_adquisicion' => (float) $equipo->valor_adquisicion,
            'valor_residual' => (float) $equipo->valor_residual,
            'vida_util_meses' => (int) $equipo->vida_util_meses,
            'dias' => $periodo->diasDeUso(),
            'horas_efectivas' => $periodo->horas_efectivas,
            'estado_costeo' => $periodo->estadoCosteo(),
            'costo_estimado_nio' => $periodo->costo_estimado_nio,
            'recursos_estimados' => $periodo->recursos_estimados,
            'depreciacion_diaria' => round($periodo->depreciacion_hora_nio !== null ? $periodo->depreciacion_hora_nio * 24 : $equipo->depreciacionDiaria(), 4),
            'depreciacion_total' => (float) $periodo->depreciacion_total,
            'metodo_costo' => $equipo->metodo_costo,
            'potencia_kw' => (float) $periodo->potencia_kw_usada,
            'tarifa_kwh_nio' => $tarifa,
            'energia_kwh' => (float) $periodo->energia_kwh,
            'costo_energia_nio' => (float) $periodo->costo_energia_nio,
            'costo_total_nio' => $periodo->costo_uso_nio,
            'disponible' => true,
            'tarifa_configurada' => !$energiaRequerida || $tarifa !== null || !($periodo->horas_efectivas > 0),
            'solapamiento' => $solapado,
        ]);
    }

    /**
     * Reglas del uso de un equipo.
     *
     * El proceso no se valida aqui: viene de la ruta y ya se comprueba
     * que pertenece a la orden. Aceptarlo del formulario permitiria
     * colar un equipo en un proceso de otra orden.
     */
    private function validarUso(Request $request): array
    {
        return $request->validate(
            [
                ...UsoEquipoService::reglasHoras(),
                'equipo_id' => [
                    'required',
                    'integer',
                    Rule::exists('equipos', 'id')
                        ->whereNull('deleted_at')
                        ->where('estado', 1),
                ],
                'fecha_inicio' => [
                    'required',
                    'date',
                ],
                'fecha_fin' => [
                    'nullable',
                    'date',
                    'after:fecha_inicio',
                ],
                'observaciones' => [
                    'nullable',
                    'string',
                    'max:255',
                ],
            ],
            [
                'equipo_id.required' => 'Debe seleccionar el equipo.',
                'equipo_id.exists' => 'El equipo seleccionado no existe o está inactivo.',
                'fecha_inicio.required' => 'Indique cuándo empieza el uso del equipo.',
                'fecha_fin.after' => 'La fecha de fin debe ser posterior a la de inicio.',
            ]
        );
    }

    public function gestion(OrdenesTrabajo $ordenTrabajo, ProcesosOrden $procesoOrden, ProcesoEquipo $procesoEquipo)
    {
        $this->validarAsignacionProceso($ordenTrabajo, $procesoOrden, $procesoEquipo);
        $procesoEquipo->load('equipo.recursos.producto', 'consumos.insumo.producto', 'entregas.producto');
        $lotes = $procesoEquipo->equipo->insumos()->with('producto')->where('disponible', '>', 0)->orderBy('id')->get();
        $title = 'Trabajo y consumos del equipo';

        return view('procesos.procesos_orden.equipos.gestion', compact('ordenTrabajo', 'procesoOrden', 'procesoEquipo', 'lotes', 'title'));
    }

    public function trabajo(Request $request, OrdenesTrabajo $ordenTrabajo, ProcesosOrden $procesoOrden, ProcesoEquipo $procesoEquipo)
    {
        $this->validarAsignacionProceso($ordenTrabajo, $procesoOrden, $procesoEquipo);
        app(UsoEquipoService::class)->guardar($procesoEquipo, $request->validate(UsoEquipoService::reglasHoras()));
        Alert::toast('Trabajo efectivo y costos actualizados.')->success()->flash();
        return back();
    }

    public function consumir(Request $request, OrdenesTrabajo $ordenTrabajo, ProcesosOrden $procesoOrden, ProcesoEquipo $procesoEquipo)
    {
        $this->validarAsignacionProceso($ordenTrabajo, $procesoOrden, $procesoEquipo);
        if ($bloqueo = $this->bloquearOrdenCerrada($ordenTrabajo, 'un consumo de equipo')) return $bloqueo;
        if ($bloqueo = $this->bloquearProcesoCerrado($ordenTrabajo, $procesoOrden, 'un consumo de equipo')) return $bloqueo;
        $datos = $request->validate([
            'tipo' => ['required', Rule::in(['entrega', 'consumo', 'devolucion'])],
            'producto_id' => ['required_if:tipo,entrega', 'nullable', 'integer', 'exists:productos,id'],
            'equipo_insumo_id' => ['required_unless:tipo,entrega', 'nullable', 'integer'],
            'cantidad' => ['required', 'numeric', 'gt:0', 'max:999999', 'decimal:0,3'],
            'fecha' => ['required', 'date_format:Y-m-d'],
        ]);
        app(UsoEquipoService::class)->registrarInsumo($procesoEquipo, $datos);
        Alert::toast('Movimiento del equipo registrado.')->success()->flash();
        return back();
    }

    public function anularConsumo(Request $request, OrdenesTrabajo $ordenTrabajo, ProcesosOrden $procesoOrden, ProcesoEquipo $procesoEquipo, int $consumo)
    {
        $this->validarAsignacionProceso($ordenTrabajo, $procesoOrden, $procesoEquipo);
        app(UsoEquipoService::class)->anularConsumo($procesoEquipo, $consumo);
        Alert::toast('Consumo anulado. El saldo permanece en el equipo.')->success()->flash();
        return back();
    }

    /**
     * Vuelve a la pantalla del proceso.
     *
     * Con una peticion AJAX se responde en JSON para que el modal se
     * cierre solo; si no, se redirige a la pantalla del proceso.
     */
    private function volverAlProceso(
        Request $request,
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden
    ) {
        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

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
     * El uso de la url tiene que ser del proceso de la url.
     */
    private function validarAsignacionProceso(
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden,
        ProcesoEquipo $procesoEquipo
    ): void {
        $this->validarProcesoPertenece($ordenTrabajo, $procesoOrden);

        abort_unless(
            (int) $procesoEquipo->proceso_orden_id === $procesoOrden->id,
            404
        );
    }
}
