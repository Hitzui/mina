<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcuerdosOrden;
use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\Cotizacion;
use App\Models\Liquidacione;
use App\Models\MetodosPagos;
use App\Models\Moneda;
use App\Models\MovimientoCaja;
use App\Models\MovimientosInventario;
use App\Models\OrdenesTrabajo;
use App\Models\Producto;
use App\Models\TiposProduccion;
use App\Models\UnidadMedida;
use App\Services\PanelDelTaller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        return response()->json(['data' => [
            ...$this->orderSummary($order),
            ...$details,
        ]]);
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
