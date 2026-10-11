<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcuerdosOrden;
use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\Cotizacion;
use App\Models\Etapa;
use App\Models\Empleado;
use App\Models\Equipo;
use App\Models\EmpleadosPago;
use App\Models\Liquidacione;
use App\Models\MetodosPagos;
use App\Models\Moneda;
use App\Models\MovimientoCaja;
use App\Models\MovimientosInventario;
use App\Models\OrdenesTrabajo;
use App\Models\Producto;
use App\Models\ProcesosOrden;
use App\Models\ProcesoEquipo;
use App\Models\TiposProduccion;
use App\Models\TiposPagoEmpleado;
use App\Models\TiposCambio;
use App\Models\TrabajosEmpleado;
use App\Models\UnidadMedida;
use App\Services\FinalizarOrdenTrabajo;
use App\Services\CodigoOrdenTrabajo;
use App\Services\CodigoProcesoOrden;
use App\Services\PanelDelTaller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;

class MobileDataController extends Controller
{
    public function clients(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $paginator = Cliente::query()
            ->select(['id', 'nombre', 'telefono', 'direccion', 'estado'])
            ->when(isset($filters['q']), fn ($query) => $query->where(function ($nested) use ($filters) {
                $term = '%'.$filters['q'].'%';
                $nested->where('nombre', 'like', $term)
                    ->orWhere('telefono', 'like', $term)
                    ->orWhere('direccion', 'like', $term);
            }))
            ->orderBy('nombre')
            ->orderBy('id')
            ->paginate($filters['per_page'] ?? 20);

        return $this->paginated($paginator, fn (Cliente $client) => [
            'id' => $client->id,
            'name' => $client->nombre,
            'phone' => $client->telefono,
            'address' => $client->direccion,
            'is_active' => (bool) $client->estado,
        ]);
    }

    public function employees(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $paginator = Empleado::query()->with('tipo_empleado')
            ->when(isset($filters['q']), fn ($query) => $query->where(function ($nested) use ($filters) {
                $term = '%'.$filters['q'].'%';
                $nested->where('codigo', 'like', $term)
                    ->orWhere('nombre', 'like', $term)
                    ->orWhere('telefono', 'like', $term);
            }))
            ->orderBy('nombre')->orderBy('id')
            ->paginate($filters['per_page'] ?? 30);

        return $this->paginated($paginator, fn (Empleado $employee) => [
            'id' => $employee->id,
            'code' => $employee->codigo,
            'name' => $employee->nombre,
            'phone' => $employee->telefono,
            'employee_type' => $employee->tipo_empleado?->nombre,
            'hire_date' => $employee->fecha_ingreso?->toDateString(),
            'is_active' => (bool) $employee->estado,
        ]);
    }

    public function employeeWorkOptions(string $orderId, string $processId): JsonResponse
    {
        $order = OrdenesTrabajo::query()->findOrFail($orderId);
        $process = ProcesosOrden::query()->where('orden_trabajo_id', $order->id)->findOrFail($processId);
        if ($order->estaCerrada() || $process->estaCerrado()) {
            return response()->json(['message' => 'No se pueden registrar trabajos en una orden o proceso cerrado.'], 422);
        }

        return response()->json(['data' => [
            'employees' => Empleado::query()->where('estado', true)->orderBy('nombre')->get(['id', 'codigo', 'nombre'])
                ->map(fn (Empleado $employee) => ['id' => $employee->id, 'code' => $employee->codigo, 'name' => $employee->nombre])
                ->values(),
            'payment_types' => TiposPagoEmpleado::query()->where('estado', true)->orderBy('nombre')->get(['id', 'nombre', 'metodo_calculo'])
                ->map(fn (TiposPagoEmpleado $type) => [
                    'id' => $type->id,
                    'name' => $type->nombre,
                    'calculation_method' => $type->metodo_calculo ?? TiposPagoEmpleado::METODO_CANTIDAD_X_TARIFA,
                ])->values(),
            'units' => UnidadMedida::query()->where('estado', true)->orderBy('nombre')->get(['id', 'nombre'])
                ->map(fn (UnidadMedida $unit) => ['id' => $unit->id, 'name' => $unit->nombre])
                ->values(),
        ]]);
    }

    public function employeeWorkRate(Request $request, string $orderId, string $processId): JsonResponse
    {
        $order = OrdenesTrabajo::query()->findOrFail($orderId);
        $process = ProcesosOrden::query()->where('orden_trabajo_id', $order->id)->findOrFail($processId);
        if ($order->estaCerrada() || $process->estaCerrado()) {
            return response()->json(['message' => 'No se pueden registrar trabajos en una orden o proceso cerrado.'], 422);
        }

        $filters = $request->validate([
            'empleado_id' => ['required', 'integer', Rule::exists('empleados', 'id')->where('estado', true)],
            'tipo_pago_id' => ['required', 'integer', Rule::exists('tipos_pago_empleado', 'id')->where('estado', true)],
            'fecha' => ['required', 'date'],
        ]);
        $pay = $this->employeePayForDate((int) $filters['empleado_id'], (int) $filters['tipo_pago_id'], $filters['fecha']);
        if (! $pay) {
            return response()->json(['message' => 'El empleado no tiene una tarifa vigente para el tipo de pago y la fecha seleccionados.'], 422);
        }

        $exchangeRate = 1.0;
        if ($pay->moneda && ! $pay->moneda->es_moneda_base) {
            $exchangeRate = TiposCambio::delDia($pay->moneda_id, $filters['fecha']);
            if ($exchangeRate === null) {
                return response()->json(['message' => $this->missingEmployeeExchangeRateMessage($pay->moneda, $filters['fecha'])], 422);
            }
        }
        $rate = (float) $pay->tarifa;

        return response()->json(['data' => [
            'rate' => $rate,
            'currency' => $pay->moneda?->codigo ?? '—',
            'exchange_rate' => $exchangeRate,
            'rate_nio' => round($rate * $exchangeRate, 4),
            'calculation_method' => $pay->tipo_pago?->metodo_calculo ?? TiposPagoEmpleado::METODO_CANTIDAD_X_TARIFA,
            'valid_from' => $pay->fecha_inicio?->toDateString(),
            'valid_until' => $pay->fecha_fin?->toDateString(),
        ]]);
    }

    public function storeEmployeeWork(Request $request, string $orderId, string $processId): JsonResponse
    {
        $order = OrdenesTrabajo::query()->findOrFail($orderId);
        $process = ProcesosOrden::query()->where('orden_trabajo_id', $order->id)->findOrFail($processId);
        if ($order->estaCerrada() || $process->estaCerrado()) {
            return response()->json(['message' => 'No se pueden registrar trabajos en una orden o proceso cerrado.'], 422);
        }

        $validated = $request->validate([
            'empleado_id' => ['required', 'integer', Rule::exists('empleados', 'id')->where('estado', true)],
            'tipo_pago_id' => ['required', 'integer', Rule::exists('tipos_pago_empleado', 'id')->where('estado', true)],
            'fecha' => ['required', 'date'],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'hora_fin' => ['nullable', 'date_format:H:i', 'after_or_equal:hora_inicio'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'cantidad' => ['required', 'numeric', 'min:0'],
            'unidad_id' => ['required', 'integer', Rule::exists('unidades_medida', 'id')->where('estado', true)],
            'observaciones' => ['nullable', 'string'],
        ]);

        $pay = $this->employeePayForDate((int) $validated['empleado_id'], (int) $validated['tipo_pago_id'], $validated['fecha']);
        if (! $pay) {
            return response()->json(['message' => 'El empleado no tiene una tarifa vigente para el tipo de pago y la fecha seleccionados.'], 422);
        }
        $exchangeRate = 1.0;
        if ($pay->moneda && ! $pay->moneda->es_moneda_base) {
            $exchangeRate = TiposCambio::delDia($pay->moneda_id, $validated['fecha']);
            if ($exchangeRate === null) {
                return response()->json(['message' => $this->missingEmployeeExchangeRateMessage($pay->moneda, $validated['fecha'])], 422);
            }
        }

        $unit = UnidadMedida::query()->findOrFail($validated['unidad_id']);
        $paymentType = TiposPagoEmpleado::query()->findOrFail($validated['tipo_pago_id']);
        $rate = (float) $pay->tarifa;
        $rateNio = round($rate * $exchangeRate, 4);
        $quantity = (float) $validated['cantidad'];
        $work = TrabajosEmpleado::query()->create([
            TrabajosEmpleado::EMPLEADO_ID => $validated['empleado_id'],
            TrabajosEmpleado::PROCESO_ORDEN_ID => $process->id,
            TrabajosEmpleado::TIPO_PAGO_ID => $paymentType->id,
            TrabajosEmpleado::FECHA => $validated['fecha'],
            TrabajosEmpleado::HORA_INICIO => $validated['hora_inicio'] ?? null,
            TrabajosEmpleado::HORA_FIN => $validated['hora_fin'] ?? null,
            TrabajosEmpleado::DESCRIPCION => $validated['descripcion'] ?? null,
            TrabajosEmpleado::CANTIDAD => $quantity,
            TrabajosEmpleado::UNIDAD_ID => $unit->id,
            TrabajosEmpleado::UNIDAD => $unit->nombre,
            TrabajosEmpleado::TARIFA => $rate,
            TrabajosEmpleado::MONEDA_ID => $pay->moneda_id,
            TrabajosEmpleado::TIPO_CAMBIO => $exchangeRate,
            TrabajosEmpleado::TARIFA_NIO => $rateNio,
            TrabajosEmpleado::TOTAL => $paymentType->calcularTotal($quantity, $rate),
            TrabajosEmpleado::TOTAL_NIO => $paymentType->calcularTotal($quantity, $rateNio),
            TrabajosEmpleado::OBSERVACIONES => $validated['observaciones'] ?? null,
        ]);
        $work->load(['empleado', 'tipo_pago', 'moneda']);

        return response()->json(['data' => [
            'id' => $work->id,
            'employee' => $work->empleado?->nombre,
            'payment_type' => $work->tipo_pago?->nombre,
            'date' => $work->fecha?->toDateString(),
            'description' => $work->descripcion,
            'quantity' => $work->cantidad,
            'unit' => $work->unidad,
            'currency' => $work->moneda?->codigo,
            'rate' => $work->tarifa,
            'total' => $work->total,
            'total_nio' => $work->total_nio,
        ]], 201);
    }

    public function processEquipmentOptions(string $orderId, string $processId): JsonResponse
    {
        [$order, $process] = $this->mobileProcessContext($orderId, $processId);
        $this->assertProcessCanReceiveEquipment($order, $process);

        $equipment = Equipo::query()->where('estado', 1)->orderBy('codigo')->get();

        return response()->json(['data' => [
            'equipment' => $equipment->map(fn (Equipo $item) => [
                'id' => $item->id,
                'code' => $item->codigo,
                'name' => $item->nombre,
                'acquisition_value' => (float) $item->valor_adquisicion,
                'residual_value' => (float) $item->valor_residual,
                'useful_life_months' => (int) $item->vida_util_meses,
            ])->values(),
            'default_start' => ($process->fecha_inicio ?? now())->format('Y-m-d\\TH:i'),
        ]]);
    }

    public function processEquipmentDepreciation(Request $request, string $orderId, string $processId): JsonResponse
    {
        [$order, $process] = $this->mobileProcessContext($orderId, $processId);
        $this->assertProcessCanReceiveEquipment($order, $process);
        $validated = $request->validate([
            'equipo_id' => ['required', 'integer', Rule::exists('equipos', 'id')->whereNull('deleted_at')->where('estado', 1)],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after:fecha_inicio'],
        ]);

        $equipment = Equipo::with('asignaciones')->findOrFail($validated['equipo_id']);
        $period = new ProcesoEquipo();
        $period->equipo_id = $equipment->id;
        $period->fecha_inicio = $validated['fecha_inicio'];
        $period->fecha_fin = $validated['fecha_fin'] ?? null;
        $overlap = null;
        try {
            $period->validarSinSolapamiento();
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $overlap = $exception->errors()[ProcesoEquipo::FECHA_INICIO][0] ?? 'El periodo se cruza con otro uso del equipo.';
        }
        $period->calcularDepreciacion();

        return response()->json(['data' => [
            'code' => $equipment->codigo,
            'acquisition_value' => (float) $equipment->valor_adquisicion,
            'residual_value' => (float) $equipment->valor_residual,
            'useful_life_months' => (int) $equipment->vida_util_meses,
            'days' => $period->diasDeUso(),
            'daily_depreciation' => round($equipment->depreciacionDiaria(), 4),
            'total_depreciation' => (float) $period->depreciacion_total,
            'available_value' => $equipment->valorPorDepreciar() > 0,
            'overlap' => $overlap,
        ]]);
    }

    public function storeProcessEquipment(Request $request, string $orderId, string $processId): JsonResponse
    {
        [$order, $process] = $this->mobileProcessContext($orderId, $processId);
        $this->assertProcessCanReceiveEquipment($order, $process);
        $validated = $request->validate([
            'equipo_id' => ['required', 'integer', Rule::exists('equipos', 'id')->whereNull('deleted_at')->where('estado', 1)],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after:fecha_inicio'],
            'observaciones' => ['nullable', 'string', 'max:255'],
        ], [
            'equipo_id.exists' => 'El equipo seleccionado no existe o está inactivo.',
            'fecha_inicio.required' => 'Indique cuándo empieza el uso del equipo.',
            'fecha_fin.after' => 'La fecha de fin debe ser posterior a la de inicio.',
        ]);

        $assignment = new ProcesoEquipo();
        $assignment->fill($validated);
        $assignment->proceso_orden_id = $process->id;
        $assignment->validarPeriodo();
        $assignment->validarSinSolapamiento();
        $assignment->calcularDepreciacion();
        $assignment->save();
        $assignment->load('equipo');

        return response()->json(['data' => [
            'id' => $assignment->id,
            'equipment_id' => $assignment->equipo_id,
            'equipment_code' => $assignment->equipo?->codigo,
            'equipment_name' => $assignment->equipo?->nombre,
            'start_date' => $assignment->fecha_inicio?->toISOString(),
            'end_date' => $assignment->fecha_fin?->toISOString(),
            'days' => $assignment->diasDeUso(),
            'daily_depreciation' => round((float) $assignment->equipo?->depreciacionDiaria(), 4),
            'total_depreciation' => (float) $assignment->depreciacion_total,
            'observations' => $assignment->observaciones,
        ]], 201);
    }

    private function mobileProcessContext(string $orderId, string $processId): array
    {
        $order = OrdenesTrabajo::query()->findOrFail($orderId);
        $process = ProcesosOrden::query()->where('orden_trabajo_id', $order->id)->findOrFail($processId);

        return [$order, $process];
    }

    private function assertProcessCanReceiveEquipment(OrdenesTrabajo $order, ProcesosOrden $process): void
    {
        abort_if($order->estaCerrada() || $process->estaCerrado(), 422, 'No se pueden asignar equipos a una orden o proceso cerrado.');
    }

    public function dashboard(Request $request): JsonResponse
    {
        $periodo = $request->validate(['periodo' => ['nullable', 'in:mes,anio,todo']])['periodo'] ?? 'mes';
        $panel = new PanelDelTaller($periodo);

        return response()->json(['data' => [
            'period' => ['key' => $periodo, 'label' => $panel->nombrePeriodo()],
            'orders_by_status' => $panel->ordenesPorEstado(),
            'processes_by_status' => $panel->procesosPorEstado(),
            'money' => $panel->dinero(),
            'gold' => $panel->oro(),
            'monthly_series' => $panel->serieDeDoceMeses(),
            'open_orders' => $panel->ordenesAbiertas(),
            'overdue_processes' => $panel->procesosAtrasados(),
            'recent_orders' => $panel->ultimasOrdenes(),
        ]]);
    }

    public function catalogs(Request $request): JsonResponse
    {
        $user = $request->user();
        $catalogs = [];

        if ($user->can('configuracion.monedas.view')) {
            $catalogs['currencies'] = Moneda::query()->where('estado', true)->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre', 'simbolo', 'es_moneda_base']);
        }
        if ($user->can('configuracion.metodos_pago.view')) {
            $catalogs['payment_methods'] = MetodosPagos::query()->paraRegistrar()->get(['id', 'nombre']);
        }
        if ($user->can('configuracion.unidades_medida.view')) {
            $catalogs['units'] = UnidadMedida::query()->where('estado', true)->orderBy('nombre')->get(['id', 'nombre']);
        }
        if ($user->can('etapas.view')) {
            $catalogs['stages'] = DB::table('etapas')->whereNull('deleted_at')->orderBy('orden')->orderBy('nombre')
                ->get(['id', 'nombre', 'descripcion', 'orden', 'estado']);
        }
        if ($user->can('configuracion.tipos_produccion.view')) {
            $catalogs['production_types'] = TiposProduccion::query()->where('estado', true)->orderBy('nombre')->get(['id', 'nombre']);
        }
        if ($user->can('productos.view')) {
            $catalogs['products'] = Producto::query()->where('estado', true)->orderBy('nombre')->get(['id', 'codigo', 'nombre', 'unidad_medida', 'stock_minimo']);
        }
        if ($user->can('configuracion.cajas.view')) {
            $caja = $user->caja;
            $catalogs['cash_register'] = $caja && $caja->estado ? ['id' => $caja->id, 'name' => $caja->nombre] : null;
        }

        return response()->json(['data' => $catalogs]);
    }

    public function orders(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'integer', 'between:0,3'],
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $paginator = OrdenesTrabajo::query()->with('cliente')
            ->when(isset($filters['q']), fn ($q) => $q->where('codigo', 'like', '%'.$filters['q'].'%'))
            ->when(isset($filters['estado']), fn ($q) => $q->where('estado', $filters['estado']))
            ->when(isset($filters['cliente_id']), fn ($q) => $q->where('cliente_id', $filters['cliente_id']))
            ->when(isset($filters['fecha_desde']), fn ($q) => $q->whereDate('fecha', '>=', $filters['fecha_desde']))
            ->when(isset($filters['fecha_hasta']), fn ($q) => $q->whereDate('fecha', '<=', $filters['fecha_hasta']))
            ->orderByDesc('fecha')->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 20);

        return $this->paginated($paginator, fn ($order) => $this->orderSummary($order));
    }

    public function orderFormOptions(): JsonResponse
    {
        return response()->json(['data' => [
            'clients' => Cliente::query()->where('estado', true)->orderBy('nombre')
                ->get(['id', 'nombre'])
                ->map(fn (Cliente $client) => ['id' => $client->id, 'name' => $client->nombre])
                ->values(),
            'units' => UnidadMedida::query()->where('estado', true)->orderBy('nombre')
                ->get(['id', 'nombre'])
                ->map(fn (UnidadMedida $unit) => ['id' => $unit->id, 'name' => $unit->nombre])
                ->values(),
        ]]);
    }

    public function storeOrder(Request $request, CodigoOrdenTrabajo $codigoOrden): JsonResponse
    {
        $validated = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
            'fecha' => ['required', 'date'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'peso_mineral' => ['required', 'numeric', 'gt:0'],
            'unidad_peso_id' => ['required', 'integer', 'exists:unidades_medida,id,estado,1'],
        ]);

        $unit = UnidadMedida::query()->findOrFail($validated['unidad_peso_id']);
        $order = OrdenesTrabajo::query()->create([
            OrdenesTrabajo::CODIGO => $codigoOrden->generar(),
            OrdenesTrabajo::CLIENTE_ID => $validated['cliente_id'],
            OrdenesTrabajo::FECHA => $validated['fecha'],
            OrdenesTrabajo::DESCRIPCION => $validated['descripcion'] ?? null,
            OrdenesTrabajo::PESO_MINERAL => $validated['peso_mineral'],
            OrdenesTrabajo::UNIDAD_PESO => $unit->nombre,
            OrdenesTrabajo::UNIDAD_PESO_ID => $unit->id,
            OrdenesTrabajo::ESTADO => OrdenesTrabajo::ESTADO_PENDIENTE,
        ]);

        $order->load('cliente');

        return response()->json(['data' => $this->orderSummary($order)], 201);
    }

    public function orderEditFormOptions(string $orderId): JsonResponse
    {
        $order = OrdenesTrabajo::query()->with('cliente')->findOrFail($orderId);
        $hasProcesses = $order->procesos_ordenes()->exists();
        return response()->json(['data' => [
            'order' => [
                'id' => $order->id,
                'code' => $order->codigo,
                'client_id' => $order->cliente_id,
                'client_name' => $order->cliente?->nombre,
                'date' => $order->fecha?->toDateString(),
                'description' => $order->descripcion,
                'mineral_weight' => (float) $order->peso_mineral,
                'weight_unit_id' => $order->unidad_peso_id,
                'weight_unit' => $order->unidad_peso,
                'status' => $order->estadoTexto(),
                'status_code' => $order->estado,
            ],
            'has_processes' => $hasProcesses,
            'clients' => Cliente::query()
                ->where(fn ($query) => $query->where('estado', true)->orWhere('id', $order->cliente_id))
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'estado'])
                ->map(fn (Cliente $client) => [
                    'id' => $client->id,
                    'name' => $client->nombre,
                    'is_active' => (bool) $client->estado,
                ])->values(),
            'units' => UnidadMedida::query()->where('estado', true)->orderBy('nombre')
                ->get(['id', 'nombre'])
                ->map(fn (UnidadMedida $unit) => ['id' => $unit->id, 'name' => $unit->nombre])
                ->values(),
        ]]);
    }

    public function updateOrder(Request $request, string $orderId): JsonResponse
    {
        $order = OrdenesTrabajo::query()->findOrFail($orderId);
        $hasProcesses = $order->procesos_ordenes()->exists();

        if ($hasProcesses) {
            $validated = $request->validate([
                'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
                'descripcion' => ['nullable', 'string', 'max:1000'],
            ]);
            $values = [
                OrdenesTrabajo::CLIENTE_ID => $validated['cliente_id'],
                OrdenesTrabajo::DESCRIPCION => $validated['descripcion'] ?? null,
            ];
        } else {
            $validated = $request->validate([
                'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
                'fecha' => ['required', 'date'],
                'descripcion' => ['nullable', 'string', 'max:1000'],
                'peso_mineral' => ['required', 'numeric', 'gt:0'],
                'unidad_peso_id' => ['required', 'integer', 'exists:unidades_medida,id,estado,1'],
            ]);
            $unit = UnidadMedida::query()->findOrFail($validated['unidad_peso_id']);
            $values = [
                OrdenesTrabajo::CLIENTE_ID => $validated['cliente_id'],
                OrdenesTrabajo::FECHA => $validated['fecha'],
                OrdenesTrabajo::DESCRIPCION => $validated['descripcion'] ?? null,
                OrdenesTrabajo::PESO_MINERAL => $validated['peso_mineral'],
                OrdenesTrabajo::UNIDAD_PESO => $unit->nombre,
                OrdenesTrabajo::UNIDAD_PESO_ID => $unit->id,
            ];
        }

        $order->update($values);
        $order->load('cliente');

        return response()->json(['data' => $this->orderSummary($order)]);
    }

    public function orderProcessFormOptions(string $orderId): JsonResponse
    {
        $order = OrdenesTrabajo::query()->findOrFail($orderId);
        abort_if($order->estaCerrada(), 422, 'No se pueden agregar procesos a una orden cerrada.');

        return response()->json(['data' => [
            'stages' => Etapa::query()->where('estado', true)->orderBy('orden')->orderBy('nombre')
                ->get(['id', 'nombre', 'orden'])
                ->map(fn (Etapa $stage) => [
                    'id' => $stage->id,
                    'name' => $stage->nombre,
                    'order' => $stage->orden,
                ])->values(),
        ]]);
    }

    public function storeOrderProcess(
        Request $request,
        string $orderId,
        CodigoProcesoOrden $codigoProceso
    ): JsonResponse {
        $order = OrdenesTrabajo::query()->findOrFail($orderId);
        if ($order->estaCerrada()) {
            return response()->json(['message' => 'No se pueden agregar procesos a una orden cerrada.'], 422);
        }

        $validated = $request->validate([
            'etapa_id' => [
                'required',
                'integer',
                Rule::exists('etapas', 'id')->whereNull('deleted_at')->where('estado', true),
            ],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'peso_entrada' => ['nullable', 'numeric', 'gte:0', 'decimal:0,3', 'lte:999999999.999'],
            'peso_salida' => ['nullable', 'numeric', 'gte:0', 'decimal:0,3', 'lte:999999999.999'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $stage = Etapa::query()->findOrFail($validated['etapa_id']);
        $process = ProcesosOrden::query()->create([
            ProcesosOrden::ORDEN_TRABAJO_ID => $order->id,
            ProcesosOrden::ETAPA_ID => $stage->id,
            ProcesosOrden::CODIGO => $codigoProceso->generar($order),
            ProcesosOrden::FECHA_INICIO => $validated['fecha_inicio'] ?? null,
            ProcesosOrden::FECHA_FIN => $validated['fecha_fin'] ?? null,
            ProcesosOrden::PESO_ENTRADA => $validated['peso_entrada'] ?? null,
            ProcesosOrden::PESO_SALIDA => $validated['peso_salida'] ?? null,
            ProcesosOrden::OBSERVACIONES => $validated['observaciones'] ?? null,
            ProcesosOrden::ESTADO => ProcesosOrden::ESTADO_PENDIENTE,
        ]);
        $process->load('etapa');

        return response()->json(['data' => [
            'id' => $process->id,
            'code' => $process->codigo,
            'stage' => $process->etapa?->nombre,
            'status' => $process->estadoTexto(),
            'start_date' => $process->fecha_inicio?->toISOString(),
            'end_date' => $process->fecha_fin?->toISOString(),
            'input_weight' => $process->peso_entrada,
            'output_weight' => $process->peso_salida,
            'observations' => $process->observaciones,
        ]], 201);
    }

    public function order(string $orderId, Request $request): JsonResponse
    {
        $order = OrdenesTrabajo::query()->with('cliente')->findOrFail($orderId);
        $mayViewFullProcessCosts = $request->user()->can('movimientos_costo.view')
            && $request->user()->can('trabajos_empleado.view')
            && $request->user()->can('proceso_equipo.view')
            && $request->user()->can('movimientos_inventario.view');
        $details = [];

        if ($request->user()->can('procesos_orden.view')) {
            $details['processes'] = $order->procesos_ordenes()->with('etapa')->orderBy('id')->get()
                ->map(function ($process) use ($mayViewFullProcessCosts) {
                    $data = [
                        'id' => $process->id,
                        'code' => $process->codigo,
                        'stage' => $process->etapa?->nombre,
                        'status' => $process->estadoTexto(),
                        'start_date' => $process->fecha_inicio?->toISOString(),
                        'end_date' => $process->fecha_fin?->toISOString(),
                        'input_weight' => $process->peso_entrada,
                        'output_weight' => $process->peso_salida,
                        'observations' => $process->observaciones,
                    ];
                    if ($mayViewFullProcessCosts) {
                        $data['cost_total_nio'] = round($process->costoTotal, 2);
                        $data['cost_breakdown'] = $process->costosDesglosados()->values();
                    }

                    return $data;
                })->values();
        }

        if ($request->user()->can('movimientos_costo.view')) {
            $details['costs'] = $order->movimientos_costos_where_orden_trabajo()
                ->with(['categoria_costo', 'moneda', 'proceso_orden.etapa'])->orderBy('fecha')->get()
                ->map(fn ($cost) => [
                    'id' => $cost->id,
                    'date' => $cost->fecha?->toDateString(),
                    'process' => $cost->proceso_orden?->nombre_completo ?? null,
                    'category' => $cost->categoria_costo?->nombre,
                    'description' => $cost->descripcion,
                    'quantity' => $cost->cantidad,
                    'currency' => $cost->moneda?->codigo,
                    'total' => $cost->costo_total,
                    'total_nio' => $cost->costo_total_nio,
                ])->values();
        }

        if ($request->user()->can('ingresos.view')) {
            $details['incomes'] = $order->ingresos_where_orden_trabajo()->with(['tipo_ingreso', 'moneda'])->orderBy('fecha')->get()
                ->map(fn ($income) => [
                    'id' => $income->id,
                    'date' => $income->fecha?->toDateString(),
                    'type' => $income->tipo_ingreso?->nombre,
                    'description' => $income->descripcion,
                    'currency' => $income->moneda?->codigo,
                    'total' => $income->total,
                    'total_nio' => $income->total_nio,
                ])->values();
        }

        if ($request->user()->can('acuerdos_orden.view')) {
            $details['agreements'] = $order->acuerdos_ordens_where_orden_trabajo()
                ->with(['tipo_participacion', 'moneda'])
                ->orderByDesc(AcuerdosOrden::CREATED_AT)
                ->get()
                ->map(fn ($agreement) => [
                    'id' => $agreement->id,
                    'participation_type' => $agreement->tipo_participacion?->nombre,
                    'modality' => $agreement->modalidad(),
                    'client_percentage' => $agreement->porcentaje_cliente,
                    'company_percentage' => $agreement->porcentaje_empresa,
                    'service_fee' => $agreement->tarifa_servicio,
                    'currency' => $agreement->moneda?->codigo,
                    'observations' => $agreement->observaciones,
                    'active' => (bool) $agreement->estado,
                ])->values();
        }

        if ($request->user()->can('liquidaciones.view')) {
            $details['liquidations'] = $order->liquidaciones_where_orden_trabajo()
                ->orderByDesc(Liquidacione::FECHA)
                ->orderByDesc(Liquidacione::ID)
                ->get()
                ->map(fn ($liquidation) => [
                    'id' => $liquidation->id,
                    'date' => $liquidation->fecha?->toDateString(),
                    'recovered_grams' => $liquidation->gramos_recuperados,
                    'client_grams' => $liquidation->gramos_cliente,
                    'company_grams' => $liquidation->gramos_empresa,
                    'client_percentage' => $liquidation->porcentaje_cliente,
                    'company_percentage' => $liquidation->porcentaje_empresa,
                    'observations' => $liquidation->observaciones,
                ])->values();
        }

        if ($request->user()->can('producciones.view')) {
            $details['production'] = $order->producciones_where_orden_trabajo()->with(['tipo_produccion', 'proceso_orden'])
                ->orderBy('fecha')->get()->map(fn ($row) => [
                    'id' => $row->id,
                    'date' => $row->fecha?->toDateString(),
                    'process' => $row->proceso_orden?->codigo,
                    'type' => $row->tipo_produccion?->nombre,
                    'description' => $row->descripcion,
                    'quantity' => $row->cantidad,
                    'unit' => $row->unidad_medida,
                ])->values();
        }

        if ($request->user()->can('trabajos_empleado.view')) {
            $details['employee_work'] = $order->trabajos_empleados()->with(['empleado', 'proceso_orden', 'tipo_pago', 'moneda'])
                ->orderBy('fecha')->get()->map(fn ($work) => [
                    'id' => $work->id,
                    'date' => $work->fecha?->toDateString(),
                    'employee' => $work->empleado?->nombre,
                    'process' => $work->proceso_orden?->codigo,
                    'payment_type' => $work->tipo_pago?->nombre,
                    'description' => $work->descripcion,
                    'quantity' => $work->cantidad,
                    'unit' => $work->unidad,
                    'currency' => $work->moneda?->codigo,
                    'total' => $work->total,
                    'total_nio' => $work->total_nio,
                ])->values();
        }

        if ($request->user()->can('proceso_equipo.view')) {
            $details['equipment'] = ProcesoEquipo::query()
                ->with(['equipo', 'procesoOrden'])
                ->whereHas('procesoOrden', fn ($query) => $query->where('orden_trabajo_id', $order->id))
                ->orderBy('fecha_inicio')
                ->get()
                ->map(fn (ProcesoEquipo $assignment) => [
                    'id' => $assignment->id,
                    'process' => $assignment->procesoOrden?->codigo,
                    'equipment_id' => $assignment->equipo_id,
                    'equipment_code' => $assignment->equipo?->codigo,
                    'equipment_name' => $assignment->equipo?->nombre,
                    'start_date' => $assignment->fecha_inicio?->toISOString(),
                    'end_date' => $assignment->fecha_fin?->toISOString(),
                    'days' => $assignment->diasDeUso(),
                    'daily_depreciation' => round((float) $assignment->equipo?->depreciacionDiaria(), 4),
                    'total_depreciation' => (float) $assignment->depreciacion_total,
                    'observations' => $assignment->observaciones,
                ])->values();
        }

        return response()->json(['data' => [
            ...$this->orderSummary($order),
            ...$details,
        ]]);
    }

    public function finalizeOrder(string $orderId, FinalizarOrdenTrabajo $finalizador): JsonResponse
    {
        $order = OrdenesTrabajo::query()->findOrFail($orderId);
        $result = $finalizador->ejecutar($order);

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json([
            'data' => [
                'id' => $order->id,
                'status' => OrdenesTrabajo::ESTADO_FINALIZADA,
                'status_text' => OrdenesTrabajo::ESTADOS[OrdenesTrabajo::ESTADO_FINALIZADA]['texto'],
                'message' => $result['message'],
            ],
        ]);
    }

    public function cashSummary(Request $request): JsonResponse
    {
        $cashRegister = $request->user()->caja;
        abort_unless($cashRegister && $cashRegister->estado, 403, 'El usuario no tiene una caja activa asignada.');
        $cashRegisterId = (int) $cashRegister->id;

        $balances = MovimientoCaja::query()
            ->join('monedas', 'monedas.id', '=', 'movimientos_caja.moneda_id')
            ->join('metodos_pago', 'metodos_pago.id', '=', 'movimientos_caja.metodo_pago_id')
            ->where('movimientos_caja.caja_id', $cashRegisterId)
            ->groupBy('metodos_pago.id', 'metodos_pago.nombre', 'monedas.id', 'monedas.codigo')
            ->selectRaw('metodos_pago.nombre as payment_method, monedas.codigo as currency')
            ->selectRaw('ROUND(SUM(CASE WHEN movimientos_caja.tipo = ? THEN movimientos_caja.monto ELSE -movimientos_caja.monto END), 2) as balance', [MovimientoCaja::ENTRADA])
            ->orderBy('metodos_pago.nombre')->orderBy('monedas.codigo')->get();

        return response()->json(['data' => [
            'cash_register' => ['id' => $cashRegister->id, 'name' => $cashRegister->nombre],
            'balances' => $balances,
        ]]);
    }

    public function cashMovements(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $cashRegister = $request->user()->caja;
        abort_unless($cashRegister && $cashRegister->estado, 403, 'El usuario no tiene una caja activa asignada.');
        $cashRegisterId = (int) $cashRegister->id;

        $paginator = MovimientoCaja::query()->with(['metodoPago', 'moneda'])
            ->where('caja_id', $cashRegisterId)
            ->when(isset($filters['fecha_desde']), fn ($q) => $q->whereDate('fecha', '>=', $filters['fecha_desde']))
            ->when(isset($filters['fecha_hasta']), fn ($q) => $q->whereDate('fecha', '<=', $filters['fecha_hasta']))
            ->orderByDesc('fecha')->orderByDesc('id')->paginate($filters['per_page'] ?? 20);

        return $this->paginated($paginator, fn ($movement) => [
            'id' => $movement->id,
            'code' => $movement->codigo,
            'date' => $movement->fecha?->toDateString(),
            'type' => $movement->tipo,
            'origin' => $movement->origen,
            'payment_method' => $movement->metodoPago?->nombre,
            'currency' => $movement->moneda?->codigo,
            'amount' => $movement->monto,
            'description' => $movement->descripcion,
            'reference' => $movement->referencia,
            'transfer_id' => $movement->transferencia_id,
        ]);
    }

    public function collections(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'estado' => ['nullable', 'integer', 'between:0,3'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $cashRegister = $request->user()->caja;
        abort_unless($cashRegister && $cashRegister->estado, 403, 'El usuario no tiene una caja activa asignada.');

        $paginator = Cobro::query()->with(['cliente', 'metodo_pago', 'moneda', 'detalles'])
            ->where('caja_id', $cashRegister->id)
            ->when(isset($filters['estado']), fn ($q) => $q->where('estado', $filters['estado']))
            ->when(isset($filters['fecha_desde']), fn ($q) => $q->whereDate('fecha', '>=', $filters['fecha_desde']))
            ->when(isset($filters['fecha_hasta']), fn ($q) => $q->whereDate('fecha', '<=', $filters['fecha_hasta']))
            ->orderByDesc('fecha')->orderByDesc('id')->paginate($filters['per_page'] ?? 20);

        return $this->paginated($paginator, fn ($collection) => [
            'id' => $collection->id,
            'code' => $collection->codigo,
            'date' => $collection->fecha?->toDateString(),
            'client' => ['id' => $collection->cliente?->id, 'name' => $collection->cliente?->nombre],
            'status' => Cobro::ESTADOS[$collection->estado]['texto'] ?? 'Desconocido',
            'status_code' => $collection->estado,
            'payment_method' => $collection->metodo_pago?->nombre,
            'currency' => $collection->moneda?->codigo,
            'amount' => $collection->montoCalculado,
            'reference' => $collection->referencia,
        ]);
    }

    public function collection(string $collectionId, Request $request): JsonResponse
    {
        $cashRegister = $request->user()->caja;
        abort_unless($cashRegister && $cashRegister->estado, 403, 'El usuario no tiene una caja activa asignada.');

        $collection = Cobro::query()->with(['cliente', 'caja', 'metodo_pago', 'moneda', 'detalles.ingreso.orden_trabajo'])
            ->where('caja_id', $cashRegister->id)->findOrFail($collectionId);

        return response()->json(['data' => [
            'id' => $collection->id,
            'code' => $collection->codigo,
            'date' => $collection->fecha?->toDateString(),
            'client' => ['id' => $collection->cliente?->id, 'name' => $collection->cliente?->nombre],
            'cash_register' => ['id' => $collection->caja?->id, 'name' => $collection->caja?->nombre],
            'status' => Cobro::ESTADOS[$collection->estado]['texto'] ?? 'Desconocido',
            'status_code' => $collection->estado,
            'payment_method' => $collection->metodo_pago?->nombre,
            'currency' => $collection->moneda?->codigo,
            'amount' => $collection->montoCalculado,
            'reference' => $collection->referencia,
            'observations' => $collection->observaciones,
            'details' => $collection->detalles->map(fn ($detail) => [
                'income_id' => $detail->ingreso?->id,
                'order_code' => $detail->ingreso?->orden_trabajo?->codigo,
                'description' => $detail->ingreso?->descripcion,
                'amount' => $detail->monto,
                'observations' => $detail->observaciones,
            ])->values(),
        ]]);
    }

    public function inventoryProducts(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $paginator = Producto::query()->with(['inventario', 'unidadMedida'])
            ->where('estado', true)
            ->when(isset($filters['q']), fn ($q) => $q->where(fn ($nested) => $nested->where('codigo', 'like', '%'.$filters['q'].'%')->orWhere('nombre', 'like', '%'.$filters['q'].'%')))
            ->orderBy('nombre')->paginate($filters['per_page'] ?? 30);

        return $this->paginated($paginator, fn ($product) => [
            'id' => $product->id,
            'code' => $product->codigo,
            'name' => $product->nombre,
            'unit' => $product->unidadMedida?->nombre ?? $product->unidad_medida,
            'stock_minimum' => $product->stock_minimo,
            'stock' => $product->existencia,
            'average_cost' => $product->costo_promedio,
            'inventory_value' => $product->valor_inventario,
            'below_minimum' => $product->estaPorDebajoDelMinimo(),
        ]);
    }

    public function inventoryMovements(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'producto_id' => ['nullable', 'integer', 'exists:productos,id'],
            'orden_trabajo_id' => ['nullable', 'integer', 'exists:ordenes_trabajo,id'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $paginator = MovimientosInventario::query()->with(['producto', 'moneda', 'orden_trabajo', 'proceso_orden'])
            ->when(isset($filters['producto_id']), fn ($q) => $q->where('producto_id', $filters['producto_id']))
            ->when(isset($filters['orden_trabajo_id']), fn ($q) => $q->where('orden_trabajo_id', $filters['orden_trabajo_id']))
            ->when(isset($filters['fecha_desde']), fn ($q) => $q->whereDate('fecha', '>=', $filters['fecha_desde']))
            ->when(isset($filters['fecha_hasta']), fn ($q) => $q->whereDate('fecha', '<=', $filters['fecha_hasta']))
            ->orderByDesc('fecha')->orderByDesc('id')->paginate($filters['per_page'] ?? 30);

        return $this->paginated($paginator, fn ($movement) => [
            'id' => $movement->id,
            'date' => $movement->fecha?->toISOString(),
            'product' => ['id' => $movement->producto?->id, 'code' => $movement->producto?->codigo, 'name' => $movement->producto?->nombre],
            'type' => $movement->tipoNormalizado(),
            'quantity' => $movement->cantidad,
            'currency' => $movement->moneda?->codigo,
            'unit_cost' => $movement->costo_unitario,
            'total_cost' => $movement->costo_total,
            'total_cost_nio' => $movement->costo_total_nio,
            'order_code' => $movement->orden_trabajo?->codigo,
            'process_code' => $movement->proceso_orden?->codigo,
            'reference' => $movement->referencia,
            'notes' => $movement->observaciones,
        ]);
    }

    public function quotes(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'estado' => ['nullable', 'in:borrador,enviada,aceptada,rechazada,vencida'],
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $paginator = Cotizacion::query()->with(['cliente', 'moneda', 'tipoParticipacion'])
            ->when(isset($filters['estado']) && $filters['estado'] === Cotizacion::ESTADO_VENCIDA, fn ($q) => $q
                ->whereIn('estado', [Cotizacion::ESTADO_BORRADOR, Cotizacion::ESTADO_ENVIADA])
                ->whereDate('fecha_vencimiento', '<', today()))
            ->when(isset($filters['estado']) && $filters['estado'] !== Cotizacion::ESTADO_VENCIDA, fn ($q) => $q->where('estado', $filters['estado'])
                ->when(in_array($filters['estado'], [Cotizacion::ESTADO_BORRADOR, Cotizacion::ESTADO_ENVIADA], true), fn ($q) => $q->whereDate('fecha_vencimiento', '>=', today())))
            ->when(isset($filters['cliente_id']), fn ($q) => $q->where('cliente_id', $filters['cliente_id']))
            ->when(isset($filters['q']), fn ($q) => $q->where('codigo', 'like', '%'.$filters['q'].'%'))
            ->orderByDesc('fecha')->orderByDesc('id')->paginate($filters['per_page'] ?? 20);

        return $this->paginated($paginator, fn ($quote) => $this->quoteData($quote));
    }

    public function quote(string $quoteId): JsonResponse
    {
        $quote = Cotizacion::query()->with(['cliente', 'moneda', 'tipoParticipacion', 'ordenTrabajo'])->findOrFail($quoteId);

        return response()->json(['data' => $this->quoteData($quote) + [
            'observations' => $quote->observaciones,
            'order_code' => $quote->ordenTrabajo?->codigo,
        ]]);
    }

    private function orderSummary(OrdenesTrabajo $order): array
    {
        return [
            'id' => $order->id,
            'code' => $order->codigo,
            'date' => $order->fecha?->toDateString(),
            'client' => ['id' => $order->cliente?->id, 'name' => $order->cliente?->nombre],
            'description' => $order->descripcion,
            'mineral_weight' => $order->peso_mineral,
            'weight_unit' => $order->unidad_peso,
            'status' => $order->estadoTexto(),
            'status_code' => $order->estado,
        ];
    }

    private function employeePayForDate(int $employeeId, int $paymentTypeId, string $date): ?EmpleadosPago
    {
        return EmpleadosPago::query()->with(['moneda', 'tipo_pago'])
            ->where('empleado_id', $employeeId)
            ->where('tipo_pago_id', $paymentTypeId)
            ->where('estado', true)
            ->whereDate('fecha_inicio', '<=', $date)
            ->where(fn ($query) => $query->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $date))
            ->orderByDesc('fecha_inicio')
            ->first();
    }

    private function missingEmployeeExchangeRateMessage(Moneda $currency, string $date): string
    {
        return sprintf(
            'No hay tipo de cambio registrado para %s el %s. Primero ingréselo en Configuración > Tipos de cambio.',
            $currency->codigo,
            Carbon::parse($date)->format('d/m/Y'),
        );
    }

    private function quoteData(Cotizacion $quote): array
    {
        return [
            'id' => $quote->id,
            'code' => $quote->codigo,
            'date' => $quote->fecha?->toDateString(),
            'expiration_date' => $quote->fecha_vencimiento?->toDateString(),
            'client' => ['id' => $quote->cliente?->id, 'name' => $quote->cliente?->nombre],
            'description' => $quote->descripcion,
            'mineral_weight' => $quote->peso_mineral,
            'weight_unit' => $quote->unidad_peso,
            'status' => Cotizacion::ESTADOS[$quote->estadoActual()] ?? 'Desconocido',
            'participation_type' => $quote->tipoParticipacion?->nombre,
            'client_percentage' => $quote->porcentaje_cliente,
            'company_percentage' => $quote->porcentaje_empresa,
            'service_fee' => $quote->tarifa_servicio,
            'currency' => $quote->moneda?->codigo,
        ];
    }

    private function paginated($paginator, callable $map): JsonResponse
    {
        return response()->json([
            'data' => $paginator->getCollection()->map($map)->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
