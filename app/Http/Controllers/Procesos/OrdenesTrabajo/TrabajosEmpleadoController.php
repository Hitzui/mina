<?php

namespace App\Http\Controllers\Procesos\OrdenesTrabajo;

use App\DataTables\TrabajosEmpleadosDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\Empleado;
use App\Models\EmpleadosPago;
use App\Models\Moneda;
use App\Models\OrdenesTrabajo;
use App\Models\ProcesosOrden;
use App\Models\TiposCambio;
use App\Models\TiposPagoEmpleado;
use App\Models\TrabajosEmpleado;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * Trabajos de los empleados dentro de un proceso de la orden.
 *
 * El trabajo cuelga del proceso, no de la orden: la orden se alcanza a
 * traves del proceso y no se guarda por separado, para que los dos datos
 * no puedan quedar desincronizados. Por eso todas las rutas llevan el
 * proceso, y la orden se deduce de el.
 */
class TrabajosEmpleadoController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        $this->authorizeModule('trabajos_empleado');
    }

    public function index(
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden,
        TrabajosEmpleadosDataTable $dataTable
    ) {
        $this->validarProcesoPertenece($ordenTrabajo, $procesoOrden);

        $dataTable->setProcesoOrdenId($procesoOrden->id);

        return $dataTable->ajax();
    }

    /**
     * El trabajo se registra desde el modal de la pantalla del proceso.
     * Esta ruta se conserva para que la url no de error, pero lleva ahi.
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
        $this->validarProcesoPertenece($ordenTrabajo, $procesoOrden);

        $validated = $this->validarTrabajo($request);

        $tarifa = $this->tarifaVigentePara(
            $validated['empleado_id'],
            $validated['tipo_pago_id'],
            $validated['fecha']
        );

        if ($tarifa === null) {
            return back()
                ->withInput()
                ->withErrors([
                    'tipo_pago_id' => 'El empleado no tiene una tarifa vigente para el tipo de pago y la fecha seleccionados.',
                ]);
        }

        $tipoCambio = 1;
        $advertenciaTipoCambio = false;

        if ($tarifa['moneda'] && ! $tarifa['moneda']->es_moneda_base) {
            $tipoCambio = $this->obtenerTipoCambio($tarifa['moneda_id'], $validated['fecha']);

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
         * nombre, para que sirva para cualquier tipo futuro.
         */
        $tipoPago = TiposPagoEmpleado::findOrFail($validated['tipo_pago_id']);

        $tarifaNio = round($tarifa['tarifa'] * $tipoCambio, 4);

        // El proceso viene de la ruta, nunca del formulario
        $validated['proceso_orden_id'] = $procesoOrden->id;
        $validated['tarifa'] = $tarifa['tarifa'];
        $validated['moneda_id'] = $tarifa['moneda_id'];
        $validated['tipo_cambio'] = $tipoCambio;
        $validated['total'] = $tipoPago->calcularTotal($cantidad, $tarifa['tarifa']);
        $validated['tarifa_nio'] = $tarifaNio;
        $validated['total_nio'] = $tipoPago->calcularTotal($cantidad, $tarifaNio);

        TrabajosEmpleado::create($validated);

        $mensaje = 'El trabajo del empleado se registró correctamente.';

        if ($advertenciaTipoCambio) {
            $mensaje .= ' No se encontró tipo de cambio para la moneda seleccionada en la fecha indicada; se utilizó 1.0000 como valor de respaldo.';
        }

        return redirect()
            ->route('procesos.ordenes_trabajo.procesos.show', [$ordenTrabajo, $procesoOrden])
            ->with('success', $mensaje);
    }

    public function show(
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden,
        TrabajosEmpleado $trabajosEmpleado
    ) {
        $this->validarTrabajoProceso($ordenTrabajo, $procesoOrden, $trabajosEmpleado);

        $trabajosEmpleado->load(['empleado', 'tipo_pago', 'moneda']);

        return response()->json([
            'id' => $trabajosEmpleado->id,
            'empleado' => $trabajosEmpleado->empleado?->nombre ?? '—',
            'fecha' => $trabajosEmpleado->fecha?->format('d/m/Y'),
            'tipo_pago' => $trabajosEmpleado->tipo_pago?->nombre ?? '—',
            'proceso' => $procesoOrden->nombre_completo,
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

    public function edit(
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden,
        TrabajosEmpleado $trabajosEmpleado
    ) {
        $this->validarTrabajoProceso($ordenTrabajo, $procesoOrden, $trabajosEmpleado);

        $trabajosEmpleado->load(['empleado', 'tipo_pago', 'moneda']);

        return response()->json([
            'id' => $trabajosEmpleado->id,
            'empleado_id' => $trabajosEmpleado->empleado_id,
            'empleado' => $trabajosEmpleado->empleado?->nombre ?? '—',
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

    public function update(
        Request $request,
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden,
        TrabajosEmpleado $trabajosEmpleado
    ) {
        $this->validarTrabajoProceso($ordenTrabajo, $procesoOrden, $trabajosEmpleado);

        $validated = $this->validarTrabajo($request);

        $tarifa = $this->tarifaVigentePara(
            $validated['empleado_id'],
            $validated['tipo_pago_id'],
            $validated['fecha']
        );

        if ($tarifa === null) {
            return back()
                ->withInput()
                ->withErrors([
                    'tipo_pago_id' => 'El empleado no tiene una tarifa vigente para el tipo de pago y fecha seleccionados.'
                ]);
        }

        $tipoCambio = 1;
        $advertenciaTipoCambio = false;

        if ($tarifa['moneda'] && ! $tarifa['moneda']->es_moneda_base) {
            $tipoCambio = $this->obtenerTipoCambio($tarifa['moneda_id'], $validated['fecha']);

            if ($tipoCambio === null) {
                $advertenciaTipoCambio = true;
                $tipoCambio = 1;
            }
        }

        $cantidad = (float) $validated['cantidad'];
        $tarifaNio = round($tarifa['tarifa'] * $tipoCambio, 4);

        // Misma regla del tipo de pago que en store()
        $tipoPago = TiposPagoEmpleado::findOrFail($validated['tipo_pago_id']);

        $validated['moneda_id'] = $tarifa['moneda_id'];
        $validated['tarifa'] = $tarifa['tarifa'];
        $validated['total'] = $tipoPago->calcularTotal($cantidad, $tarifa['tarifa']);
        $validated['tipo_cambio'] = $tipoCambio;
        $validated['tarifa_nio'] = $tarifaNio;
        $validated['total_nio'] = $tipoPago->calcularTotal($cantidad, $tarifaNio);

        $trabajosEmpleado->update($validated);

        $mensaje = 'El trabajo del empleado se actualizó correctamente.';

        if ($advertenciaTipoCambio) {
            $mensaje .= ' No se encontró tipo de cambio para la moneda seleccionada en la fecha indicada; se utilizó 1.0000 como valor de respaldo.';
        }

        return redirect()
            ->route('procesos.ordenes_trabajo.procesos.show', [$ordenTrabajo, $procesoOrden])
            ->with('success', $mensaje);
    }

    public function destroy(
        Request $request,
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden,
        TrabajosEmpleado $trabajosEmpleado
    ) {
        $this->validarTrabajoProceso($ordenTrabajo, $procesoOrden, $trabajosEmpleado);

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

        return redirect()->route(
            'procesos.ordenes_trabajo.procesos.show',
            [$ordenTrabajo, $procesoOrden]
        );
    }

    /**
     * Tarifa vigente de un empleado para un tipo de pago en una fecha.
     *
     * Devuelve null cuando no hay, para que cada llamador decida como
     * avisar. Se centraliza aqui porque store() y update() la buscan
     * igual y antes estaba duplicada.
     *
     * @return array{tarifa: float, moneda_id: int, moneda: ?Moneda}|null
     */
    private function tarifaVigentePara(int $empleadoId, int $tipoPagoId, string $fecha): ?array
    {
        $pago = EmpleadosPago::with('moneda')
            ->where('empleado_id', $empleadoId)
            ->where('tipo_pago_id', $tipoPagoId)
            ->where('estado', true)
            ->whereDate('fecha_inicio', '<=', $fecha)
            ->where(function ($query) use ($fecha) {
                $query->whereNull('fecha_fin')
                    ->orWhereDate('fecha_fin', '>=', $fecha);
            })
            ->orderByDesc('fecha_inicio')
            ->first();

        if (!$pago) {
            return null;
        }

        return [
            'tarifa' => (float) $pago->tarifa,
            'moneda_id' => $pago->moneda_id,
            'moneda' => $pago->moneda,
        ];
    }

    private function validarTrabajo(Request $request): array
    {
        /*
         * El proceso no se valida aqui: viene de la ruta y ya se
         * comprueba que pertenece a la orden. Aceptarlo del formulario
         * permitiria colar un trabajo en un proceso de otra orden.
         */
        return $request->validate([
            'empleado_id' => [
                'required',
                'integer',
                Rule::exists('empleados', 'id')->where('estado', 1),
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
    }

    /**
     * Obtener el tipo de cambio vigente de una moneda para una fecha dada.
     *
     * La regla vive en TiposCambio::vigentePara() porque tambien la
     * necesitan los costos de la orden; antes estaba duplicada aqui y
     * cualquier retoque a una dejaba a la otra desfasada, que es
     * exactamente lo que la documentacion pide evitar al centralizar el
     * calculo de equivalentes NIO.
     *
     * Se devuelve null cuando no hay tipo de cambio para esa fecha, para
     * que el llamador aplique su propia politica de respaldo y avise.
     */
    private function obtenerTipoCambio(int $monedaId, string $fecha): ?float
    {
        return TiposCambio::vigentePara($monedaId, $fecha);
    }

    public function tarifaVigente(
        Request $request,
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden
    ) {
        $this->validarProcesoPertenece($ordenTrabajo, $procesoOrden);

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

        if ($pago->moneda && ! $pago->moneda->es_moneda_base) {
            $obtenido = $this->obtenerTipoCambio($pago->moneda_id, $request->fecha);

            if ($obtenido === null) {
                $tipoCambioEncontrado = false;
            } else {
                $tipoCambio = $obtenido;
                $tarifaNio = round($tarifa * $tipoCambio, 4);
            }
        }

        return response()->json([
            'tarifa' => $tarifa,
            'moneda' => $codigoMoneda ?: '—',
            'tipo_cambio' => $tipoCambio,
            'tarifa_nio' => $tarifaNio,
            'tipo_cambio_encontrado' => $tipoCambioEncontrado,
            'metodo_calculo' => $pago->tipo_pago?->metodo_calculo
                ?? TiposPagoEmpleado::METODO_CANTIDAD_X_TARIFA,
            'fecha_inicio' => $pago->fecha_inicio?->format('d/m/Y'),
            'fecha_fin' => $pago->fecha_fin?->format('d/m/Y'),
        ]);
    }

    /**
     * El proceso de la URL tiene que ser de la orden de la URL.
     *
     * Sin esto se podria abrir el proceso de una orden escribiendo la de
     * otra en la direccion, y el trabajo se guardaria en una orden
     * distinta a la que se cree ver.
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
     * El trabajo de la URL tiene que ser del proceso de la URL.
     */
    private function validarTrabajoProceso(
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden,
        TrabajosEmpleado $trabajosEmpleado
    ): void {
        $this->validarProcesoPertenece($ordenTrabajo, $procesoOrden);

        abort_unless(
            (int) $trabajosEmpleado->proceso_orden_id === $procesoOrden->id,
            404
        );
    }

    private function empleadosActivos()
    {
        return Empleado::where('estado', 1)->orderBy('nombre')->get();
    }

    private function tiposPagoActivos()
    {
        return TiposPagoEmpleado::where('estado', 1)->orderBy('nombre')->get();
    }

    private function monedasActivas()
    {
        return Moneda::where('estado', 1)->orderBy('nombre')->get();
    }
}
