<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Cajas, Cobro, DetalleCobro, Ingreso, MetodosPagos, Moneda, OrdenesTrabajo, User};
use App\Services\MovimientoCajaService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MobileCollectionController extends Controller
{
    public static function settings(Request $request): array
    {
        $caja = $request->user()->caja;

        return [
            'cash_register' => $caja && $caja->estado ? ['id' => $caja->id, 'name' => $caja->nombre] : null,
            'can_create' => $request->user()->can('cobros.create'),
            'payment_methods' => MetodosPagos::paraRegistrar()->get()->map(fn ($m) => ['id' => $m->id, 'name' => $m->nombre])->values(),
            'currencies' => Moneda::where('estado', true)->orderBy('codigo')->get()->map(fn ($m) => ['id' => $m->id, 'name' => $m->codigo])->values(),
        ];
    }

    public function show(Request $request, string $orderId): JsonResponse
    {
        $order = OrdenesTrabajo::with('cliente')->findOrFail($orderId);
        $incomes = Ingreso::with('moneda')->where('orden_trabajo_id', $order->id)->orderBy('id')->get();
        $paid = DB::table('detalle_cobros as d')->join('cobros as c', 'c.id', '=', 'd.cobro_id')
            ->whereNull('d.deleted_at')->whereNull('c.deleted_at')->where('c.estado', Cobro::ESTADO_FINALIZADA)
            ->whereIn('d.ingreso_id', $incomes->modelKeys())->get(['d.ingreso_id', 'd.monto', 'c.moneda_id']);
        $history = Cobro::with(['moneda', 'metodo_pago', 'detalles'])
            ->where('caja_id', $request->user()->caja_id)
            ->whereHas('detalles.ingreso', fn ($q) => $q->where('orden_trabajo_id', $order->id))
            ->orderByDesc('fecha')->orderByDesc('id')->get();

        return response()->json(['data' => [
            ...self::settings($request),
            'order_id' => $order->id, 'order_code' => $order->codigo,
            'client_id' => $order->cliente_id, 'client_name' => $order->cliente?->nombre,
            'status_code' => $order->estado,
            'incomes' => $incomes->map(function ($income) use ($paid) {
                $lines = $paid->where('ingreso_id', $income->id);
                $mixed = $lines->contains(fn ($line) => (int) $line->moneda_id !== (int) $income->moneda_id);
                $amount = round((float) $lines->where('moneda_id', $income->moneda_id)->sum('monto'), 2);

                return ['id' => $income->id, 'description' => $income->descripcion ?: 'Ingreso #'.$income->id,
                    'currency_id' => $income->moneda_id, 'currency' => $income->moneda?->codigo,
                    'total' => (float) $income->total, 'confirmed' => $amount, 'mixed_currency' => $mixed];
            })->values(),
            'history' => $history->map(fn ($c) => [
                'id' => $c->id, 'code' => $c->codigo, 'date' => $c->fecha?->toDateString(),
                'currency' => $c->moneda?->codigo, 'payment_method' => $c->metodo_pago?->nombre,
                'amount' => (float) $c->detalles->whereIn('ingreso_id', $incomes->modelKeys())->sum('monto'),
                'status' => $c->estadoTexto(), 'reference' => $c->referencia,
            ])->values(),
        ]]);
    }

    public function store(Request $request, string $orderId, MovimientoCajaService $movimientos): JsonResponse
    {
        $data = $request->validate([
            'operation_key' => ['required', 'string', 'max:150'],
            'cash_register_id' => ['required', 'integer'],
            'client_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'currency_id' => ['required', 'integer'],
            'payment_method_id' => ['required', 'integer'],
            'reference' => ['nullable', 'string', 'max:100'],
            'observations' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.income_id' => ['required', 'integer', 'distinct'],
            'lines.*.amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999999.99'],
        ]);
        $hash = hash('sha256', json_encode([$orderId, $data], JSON_THROW_ON_ERROR));

        $result = DB::transaction(function () use ($request, $orderId, $data, $hash, $movimientos) {
            // También serializa reintentos simultáneos del mismo dispositivo/usuario.
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $previous = DB::table('mobile_sync_operations')->where('user_id', $request->user()->id)
                ->where('operation_key', $data['operation_key'])->first();
            if ($previous) {
                $stored = json_decode($previous->result, true);
                abort_unless($previous->operation_type === 'collection.create' && ($stored['request_hash'] ?? null) === $hash,
                    422, 'La referencia de sincronización ya corresponde a otro pago. No se creó otro cobro.');
                return $stored['data'];
            }
            $order = OrdenesTrabajo::whereKey($orderId)->lockForUpdate()->firstOrFail();
            abort_if((int) $order->estado === 0 || ! $order->cliente_id, 422, 'Se necesita una OT no cancelada con cliente para cobrar.');
            abort_unless((int) $order->cliente_id === (int) $data['client_id'], 422, 'El cliente de la OT cambió. Revise el pago antes de sincronizar.');
            $caja = Cajas::whereKey($request->user()->caja_id)->lockForUpdate()->first();
            abort_unless($caja && $caja->estado && (int) $caja->id === (int) $data['cash_register_id'], 422,
                'La caja del pago ya no es la caja activa asignada. Revise el pendiente; no se trasladó el pago a otra caja.');
            abort_unless(Moneda::whereKey($data['currency_id'])->where('estado', true)->exists(), 422, 'La moneda ya no está activa.');
            abort_unless(MetodosPagos::paraRegistrar()->whereKey($data['payment_method_id'])->exists(), 422, 'El método de pago ya no está activo.');
            $incomes = Ingreso::whereIn('id', array_column($data['lines'], 'income_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $lines = [];
            foreach ($data['lines'] as $line) {
                $income = $incomes->get($line['income_id']);
                abort_unless($income && (int) $income->orden_trabajo_id === (int) $order->id, 422, 'Uno de los ingresos ya no pertenece a esta OT.');
                abort_unless((int) $income->moneda_id === (int) $data['currency_id'], 422, 'En móvil, cobre en la misma moneda del ingreso.');
                // Lectura actual (no el snapshot REPEATABLE READ de operaciones
                // anteriores de la subida) después de obtener el candado de la OT.
                $paid = DB::table('detalle_cobros as d')->join('cobros as c', 'c.id', '=', 'd.cobro_id')
                    ->where('d.ingreso_id', $income->id)->whereNull('d.deleted_at')->whereNull('c.deleted_at')
                    ->where('c.estado', Cobro::ESTADO_FINALIZADA)->lockForUpdate()->get(['d.monto', 'c.moneda_id']);
                abort_if($paid->contains(fn ($p) => (int) $p->moneda_id !== (int) $data['currency_id']), 422,
                    'Este ingreso tiene pagos en otra moneda. Revise su saldo y registre el cobro desde la web.');
                $confirmed = round((float) $paid->sum('monto'), 2);
                if ($confirmed + (float) $line['amount'] - (float) $income->total > 0.005) {
                    throw ValidationException::withMessages(['lines' => sprintf(
                        'El ingreso #%d tiene %.2f confirmados de %.2f. Revise el abono pendiente: supera el saldo actual.',
                        $income->id, $confirmed, $income->total)]);
                }
                $lines[] = ['ingreso_id' => $income->id, 'monto' => round((float) $line['amount'], 2)];
            }
            $cobro = new Cobro(['cliente_id' => $order->cliente_id, 'caja_id' => $caja->id,
                'moneda_id' => $data['currency_id'], 'metodo_pago_id' => $data['payment_method_id'],
                'fecha' => $data['date'], 'referencia' => $data['reference'] ?? null,
                'observaciones' => $data['observations'] ?? null, 'estado' => Cobro::ESTADO_FINALIZADA]);
            $cobro = Cobro::crearConCodigo($cobro->getAttributes());
            foreach ($lines as $line) {
                $cobro->detalles()->create($line);
            }
            $cobro->monto = array_sum(array_column($lines, 'monto'));
            $cobro->save();
            $movimientos->sincronizarCobro($cobro);
            $receipt = ['id' => $cobro->id, 'code' => $cobro->codigo, 'amount' => (float) $cobro->monto, 'status' => 'Finalizada'];
            DB::table('mobile_sync_operations')->insert([
                'user_id' => $request->user()->id, 'operation_key' => $data['operation_key'],
                'operation_type' => 'collection.create', 'result' => json_encode(['data' => $receipt, 'request_hash' => $hash], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return $receipt;
        });

        return response()->json(['data' => $result], 201);
    }
}
