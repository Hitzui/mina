<?php

namespace App\Http\Controllers\Procesos\OrdenesTrabajo;

use App\DataTables\TrabajosEmpleadosDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\Empleado;
use App\Models\Moneda;
use App\Models\OrdenesTrabajo;
use App\Models\ProcesosOrden;
use App\Models\TiposCambio;
use App\Models\TiposPagoEmpleado;
use App\Models\TrabajosEmpleado;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\EmpleadosPago;
use RealRashid\SweetAlert\Facades\Alert;

class TrabajosEmpleadoController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        $this->authorizeModule('trabajos_empleado');
    }

    public function index(OrdenesTrabajo $ordenTrabajo, TrabajosEmpleadosDataTable $dataTable)
    {
        $dataTable->setOrdenTrabajoId($ordenTrabajo->id);
        return $dataTable->ajax();
    }

    public function create(OrdenesTrabajo $ordenTrabajo)
    {
        $empleados = Empleado::where('estado', 1)
            ->orderBy('nombre')
            ->get();

        $tiposPago = TiposPagoEmpleado::where('estado', 1)
            ->orderBy('nombre')
            ->get();

        $monedas = Moneda::where('estado', 1)
            ->orderBy('nombre')
            ->get();

        $procesos = ProcesosOrden::where('orden_trabajo_id', $ordenTrabajo->id)
            ->orderBy('id')
            ->get();

        return view('procesos.ordenes_trabajo.trabajos_empleados.create', compact(
            'ordenTrabajo',
            'empleados',
            'tiposPago',
            'monedas',
            'procesos'
        ));
    }

    public function store(Request $request, OrdenesTrabajo $ordenTrabajo)
    {
        $validated = $request->validate([
            'empleado_id' => [
                'required',
                'integer',
                Rule::exists('empleados', 'id')->where('estado', 1),
            ],
            'proceso_orden_id' => [
                'nullable',
                'integer',
                Rule::exists('procesos_orden', 'id')->where(function ($query) use ($ordenTrabajo) {
                    $query->where('orden_trabajo_id', $ordenTrabajo->id);
                }),
            ],
            'tipo_pago_id' => [
                'required',
                'integer',
                Rule::exists('tipos_pago_empleado', 'id')->where('estado', 1),
            ],
            'fecha' => ['required', 'date'],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'hora_fin' => ['nullable', 'date_format:H:i', 'after_or_equal:hora_inicio'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'cantidad' => ['required', 'numeric', 'min:0'],
            'unidad' => ['required', 'string', 'max:20'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $pago = EmpleadosPago::with('moneda')
            ->where('empleado_id', $validated['empleado_id'])
            ->where('tipo_pago_id', $validated['tipo_pago_id'])
            ->where('estado', 1)
            ->whereDate('fecha_inicio', '<=', $validated['fecha'])
            ->where(function ($query) use ($validated) {
                $query->whereNull('fecha_fin')
                    ->orWhereDate('fecha_fin', '>=', $validated['fecha']);
            })
            ->orderByDesc('fecha_inicio')
            ->first();

        if (!$pago) {
            return back()
                ->withInput()
                ->withErrors([
                    'tipo_pago_id' => 'El empleado no tiene una tarifa vigente para el tipo de pago y la fecha seleccionados.',
                ]);
        }

        $tarifa = (float) $pago->tarifa;
        $moneda = $pago->moneda;

        $tipoCambio = 1;
        $advertenciaTipoCambio = false;

        if ($moneda && ! $moneda->es_moneda_base) {
            $tipoCambio = $this->obtenerTipoCambio($pago->moneda_id, $validated['fecha']);

            if ($tipoCambio === null) {
                $advertenciaTipoCambio = true;
                $tipoCambio = 1;
            }
        }

        $cantidad = (float) $validated['cantidad'];

        /*
         * El total se calcula con la regla del tipo de pago: para
         * "por trabajo" o "fijo" la tarifa ES el pago y la cantidad no
         * multiplica. Se resuelve desde el tipo de pago, no desde el
         * nombre, para que serve para cualquier tipo futuro.
         */
        $tipoPago = TiposPagoEmpleado::findOrFail(
            $validated['tipo_pago_id']
        );

        $validated['orden_trabajo_id'] = $ordenTrabajo->id;
        $validated['tarifa'] = $tarifa;
        $validated['moneda_id'] = $pago->moneda_id;
        $validated['tipo_cambio'] = $tipoCambio;
        $validated['total'] = $tipoPago->calcularTotal($cantidad, $tarifa);
        $validated['tarifa_nio'] = round($tarifa * $tipoCambio, 4);
        $validated['total_nio'] = $tipoPago->calcularTotal(
            $cantidad,
            $validated['tarifa_nio']
        );

        $trabajo = TrabajosEmpleado::create($validated);

        $mensaje = 'El trabajo del empleado se registró correctamente.';

        if ($advertenciaTipoCambio) {
            $mensaje .= ' No se encontró tipo de cambio para la moneda seleccionada en la fecha indicada; se utilizó 1.0000 como valor de respaldo.';
        }

        return redirect()
            ->route('procesos.ordenes_trabajo.show', $ordenTrabajo)
            ->with('success', $mensaje);
    }

    public function show(OrdenesTrabajo $ordenTrabajo, TrabajosEmpleado $trabajosEmpleado)
    {
        $this->validarTrabajoOrden($ordenTrabajo, $trabajosEmpleado);

        $trabajosEmpleado->load([
            'empleado',
            'tipo_pago',
            'moneda',
            'proceso_orden',
        ]);

        return response()->json([
            'id' => $trabajosEmpleado->id,
            'empleado' => $trabajosEmpleado->empleado?->nombre ?? '—',
            'fecha' => $trabajosEmpleado->fecha?->format('d/m/Y'),
            'tipo_pago' => $trabajosEmpleado->tipo_pago?->nombre ?? '—',
            'proceso' => $trabajosEmpleado->proceso_orden?->nombre_completo ?? TrabajosEmpleado::ETIQUETA_GENERAL,
            'hora_inicio' => $trabajosEmpleado->hora_inicio?->format('H:i'),
            'hora_fin' => $trabajosEmpleado->hora_fin?->format('H:i'),
            'cantidad' => $trabajosEmpleado->cantidad,
            'unidad' => $trabajosEmpleado->unidad,
            'tarifa' => $trabajosEmpleado->tarifa,
            'moneda' => $trabajosEmpleado->moneda?->codigo ?? '—',
            'tipo_cambio' => $trabajosEmpleado->tipo_cambio,
            'tarifa_nio' => $trabajosEmpleado->tarifa_nio,
            'total' => $trabajosEmpleado->total,
            'total_nio' => $trabajosEmpleado->total_nio,
            'descripcion' => $trabajosEmpleado->descripcion ?? '',
            'observaciones' => $trabajosEmpleado->observaciones ?? '',
        ]);
    }

    public function edit(OrdenesTrabajo $ordenTrabajo, TrabajosEmpleado $trabajosEmpleado)
    {
        $this->validarTrabajoOrden($ordenTrabajo, $trabajosEmpleado);

        $trabajosEmpleado->load(['empleado', 'tipo_pago', 'moneda', 'proceso_orden']);

        return response()->json([
            'id' => $trabajosEmpleado->id,
            'empleado_id' => $trabajosEmpleado->empleado_id,
            'empleado' => $trabajosEmpleado->empleado?->nombre ?? '—',
            'proceso_orden_id' => $trabajosEmpleado->proceso_orden_id,
            'tipo_pago_id' => $trabajosEmpleado->tipo_pago_id,
            'fecha' => $trabajosEmpleado->fecha?->format('Y-m-d'),
            'hora_inicio' => $trabajosEmpleado->hora_inicio?->format('H:i'),
            'hora_fin' => $trabajosEmpleado->hora_fin?->format('H:i'),
            'descripcion' => $trabajosEmpleado->descripcion ?? '',
            'cantidad' => $trabajosEmpleado->cantidad,
            'unidad' => $trabajosEmpleado->unidad,
            'tarifa' => $trabajosEmpleado->tarifa,
            'total' => $trabajosEmpleado->total,
            'tarifa_nio' => $trabajosEmpleado->tarifa_nio,
            'total_nio' => $trabajosEmpleado->total_nio,
            'moneda_id' => $trabajosEmpleado->moneda_id,
            'tipo_cambio' => $trabajosEmpleado->tipo_cambio,

            // Para que el formulario edite con la regla correcta
            'metodo_calculo' => $trabajosEmpleado->tipo_pago?->metodo_calculo
                ?? TiposPagoEmpleado::METODO_CANTIDAD_X_TARIFA,

            'observaciones' => $trabajosEmpleado->observaciones ?? '',
        ]);
    }

    public function update(Request $request, OrdenesTrabajo $ordenTrabajo, TrabajosEmpleado $trabajosEmpleado)
    {
        $this->validarTrabajoOrden($ordenTrabajo, $trabajosEmpleado);

        $validated = $request->validate([
            'empleado_id' => [
                'required',
                'integer',
                Rule::exists('empleados', 'id')->where('estado', 1),
            ],
            'proceso_orden_id' => [
                'nullable',
                'integer',
                Rule::exists('procesos_orden', 'id')->where(function ($query) use ($ordenTrabajo) {
                    $query->where('orden_trabajo_id', $ordenTrabajo->id);
                }),
            ],
            'tipo_pago_id' => [
                'required',
                'integer',
                Rule::exists('tipos_pago_empleado', 'id')->where('estado', 1),
            ],
            'fecha' => ['required', 'date'],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'hora_fin' => ['nullable', 'date_format:H:i', 'after_or_equal:hora_inicio'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'cantidad' => ['required', 'numeric', 'min:0'],
            'unidad' => ['required', 'string', 'max:20'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $pago = EmpleadosPago::with('moneda')
            ->where('empleado_id', $validated['empleado_id'])
            ->where('tipo_pago_id', $validated['tipo_pago_id'])
            ->where('estado', true)
            ->whereDate('fecha_inicio', '<=', $validated['fecha'])
            ->where(function ($query) use ($validated) {
                $query->whereNull('fecha_fin')
                    ->orWhereDate('fecha_fin', '>=', $validated['fecha']);
            })
            ->orderByDesc('fecha_inicio')
            ->first();

        if (!$pago) {
            return back()
                ->withInput()
                ->withErrors([
                    'tipo_pago_id' => 'El empleado no tiene una tarifa vigente para el tipo de pago y fecha seleccionados.'
                ]);
        }

        $monedaId = $pago->moneda_id;
        $tarifa = (float) $pago->tarifa;
        $moneda = $pago->moneda;

        $tipoCambio = 1;
        $advertenciaTipoCambio = false;

        if ($moneda && ! $moneda->es_moneda_base) {
            $tipoCambio = $this->obtenerTipoCambio($monedaId, $validated['fecha']);

            if ($tipoCambio === null) {
                $advertenciaTipoCambio = true;
                $tipoCambio = 1;
            }
        }

        $cantidad = (float) $validated['cantidad'];
        $tarifaNio = round($tarifa * $tipoCambio, 4);

        // Misma regla del tipo de pago que en store()
        $tipoPago = TiposPagoEmpleado::findOrFail(
            $validated['tipo_pago_id']
        );

        $total = $tipoPago->calcularTotal($cantidad, $tarifa);
        $totalNio = $tipoPago->calcularTotal($cantidad, $tarifaNio);

        $validated['orden_trabajo_id'] = $ordenTrabajo->id;
        $validated['moneda_id'] = $monedaId;
        $validated['tarifa'] = $tarifa;
        $validated['total'] = $total;
        $validated['tipo_cambio'] = $tipoCambio;
        $validated['tarifa_nio'] = $tarifaNio;
        $validated['total_nio'] = $totalNio;

        $trabajosEmpleado->update($validated);

        $mensaje = 'El trabajo del empleado se actualizó correctamente.';

        if ($advertenciaTipoCambio) {
            $mensaje .= ' No se encontró tipo de cambio para la moneda seleccionada en la fecha indicada; se utilizó 1.0000 como valor de respaldo.';
        }

        return redirect()
            ->route('procesos.ordenes_trabajo.show', $ordenTrabajo)
            ->with('success', $mensaje);
    }

    public function destroy(
        Request $request,
        OrdenesTrabajo $ordenTrabajo,
        TrabajosEmpleado $trabajosEmpleado
    ) {
        $this->validarTrabajoOrden($ordenTrabajo, $trabajosEmpleado);

        $trabajosEmpleado->delete();

        $mensaje = 'El trabajo del empleado fue eliminado correctamente.';

        /*
         * La confirmacion de borrado la resuelve realrashid/sweet-alert
         * con data-confirm-delete, que envia un formulario normal. Si
         * este metodo devolviera JSON, el navegador mostraria el JSON
         * crudo en pantalla. Por eso responde como los demas
         * controladores: redireccion con aviso.
         *
         * El JSON se mantiene solo para llamadas AJAX reales.
         */
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $mensaje,
            ]);
        }

        Alert::toast($mensaje)->success()->flash();

        return redirect()
            ->route('procesos.ordenes_trabajo.show', $ordenTrabajo);
    }

    private function validarTrabajoOrden(
        OrdenesTrabajo $ordenTrabajo,
        TrabajosEmpleado $trabajosEmpleado
    ): void {
        abort_unless(
            (int) $trabajosEmpleado->orden_trabajo_id === $ordenTrabajo->id,
            404
        );
    }

    /**
     * Obtener el tipo de cambio vigente de una moneda para una fecha dada.
     *
     * Se toma el registro más reciente cuya fecha sea menor o igual a la
     * fecha solicitada. Devuelve null cuando no hay tipo de cambio
     * registrado para esa fecha, para que el llamador pueda aplicar
     * su propia política de respaldo y avisar al usuario.
     */
    private function obtenerTipoCambio(int $monedaId, string $fecha): ?float
    {
        $registro = TiposCambio::query()
            ->where('moneda_id', $monedaId)
            ->whereDate('fecha', '<=', $fecha)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->first();

        if (!$registro || !is_numeric($registro->valor)) {
            return null;
        }

        $valor = (float) $registro->valor;

        return $valor > 0 ? $valor : null;
    }

    public function tarifaVigente(Request $request, OrdenesTrabajo $ordenTrabajo)
    {
        $request->validate([
            'empleado_id' => ['required', 'integer', 'exists:empleados,id'],
            'tipo_pago_id' => ['required', 'integer', 'exists:tipos_pago_empleado,id'],
            'fecha' => ['required', 'date'],
        ]);

        $pago = EmpleadosPago::query()
            ->with(['moneda', 'tipo_pago'])
            ->where('empleado_id', $request->empleado_id)
            ->where('tipo_pago_id', $request->tipo_pago_id)
            ->where('estado', true)
            ->whereDate('fecha_inicio', '<=', $request->fecha)
            ->where(function ($query) use ($request) {
                $query->whereNull('fecha_fin')
                    ->orWhereDate('fecha_fin', '>=', $request->fecha);
            })
            ->orderByDesc('fecha_inicio')
            ->first();

        if (!$pago) {
            return response()->json([
                'message' => 'El empleado no tiene una tarifa vigente para el tipo de pago y fecha seleccionados.'
            ], 404);
        }

        $tarifa = (float) $pago->tarifa;
        $codigoMoneda = strtoupper($pago->moneda?->codigo ?? '');

        $tipoCambio = 1;
        $tarifaNio = $tarifa;
        $tipoCambioEncontrado = true;

        /*
         * La moneda base (es_moneda_base) no necesita conversión.
         * No se compara el código porque no es 'NIO': en la base de
         * datos los códigos son '001' (córdoba) y '002' (dólar).
         */
        if (! $pago->moneda?->es_moneda_base) {
            $tipoCambio = $this->obtenerTipoCambio($pago->moneda_id, $request->fecha);

            if ($tipoCambio !== null) {
                $tarifaNio = round($tarifa * $tipoCambio, 4);
            } else {
                $tipoCambioEncontrado = false;
                $tipoCambio = 1;
                $tarifaNio = $tarifa;
            }
        }

        return response()->json([
            'tarifa' => $tarifa,
            'moneda_id' => $pago->moneda_id,
            'moneda' => $codigoMoneda ?: '—',
            'tipo_cambio' => $tipoCambio,
            'tarifa_nio' => $tarifaNio,
            'tipo_cambio_encontrado' => $tipoCambioEncontrado,
            'fecha_inicio' => $pago->fecha_inicio?->format('d/m/Y'),
            'fecha_fin' => $pago->fecha_fin?->format('d/m/Y'),

            /*
             * El navegador necesita la regla para previsualizar el
             * total. Es solo una ayuda visual: al guardar, el servidor
             * la vuelve a aplicar y es la que manda.
             */
            'metodo_calculo' => $pago->tipo_pago?->metodo_calculo
                ?? TiposPagoEmpleado::METODO_CANTIDAD_X_TARIFA,
        ]);
    }
}
