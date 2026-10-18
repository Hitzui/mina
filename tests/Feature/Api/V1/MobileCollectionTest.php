<?php

namespace Tests\Feature\Api\V1;

use App\Models\{Cajas, Cliente, Cobro, DetalleCobro, Ingreso, MetodosPagos, Moneda, MovimientoCaja, OrdenesTrabajo, TiposIngreso, UnidadMedida, User};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MobileCollectionTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private OrdenesTrabajo $order;
    private Ingreso $income;
    private Moneda $currency;
    private int $method;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['caja_id' => Cajas::create(['nombre' => 'Caja cobros móvil '.uniqid(), 'estado' => true])->id]);
        $this->user->syncPermissions(['ordenes_trabajo.view', 'ordenes_trabajo.create', 'ordenes_trabajo.edit', 'cobros.view', 'cobros.create', 'ingresos.create', 'ingresos.view']);
        $this->withToken($this->user->createToken('Cobros test')->plainTextToken);
        $this->order = OrdenesTrabajo::firstOrFail()->replicate();
        $this->order->codigo = 'OT-COBRO-'.uniqid();
        $this->order->tipo_trabajo = 'servicio';
        $this->order->lama_lote_id = null;
        $this->order->estado = 3;
        $this->order->save();
        $this->currency = Moneda::base();
        $this->method = MetodosPagos::paraRegistrar()->value('id');
        $this->income = Ingreso::create(['orden_trabajo_id' => $this->order->id,
            'tipo_ingreso_id' => TiposIngreso::paraRegistrar()->value('id'), 'fecha' => '2026-10-15',
            'descripcion' => 'Servicio de molienda', 'cantidad' => 1, 'precio_unitario' => 6000,
            'total' => 6000, 'total_nio' => 6000, 'moneda_id' => $this->currency->id]);
    }

    private function path(): string { return '/api/v1/orders/'.$this->order->id.'/collections'; }

    private function payment(float $amount = 2000): array
    {
        return ['operation_key' => 'payment-'.uniqid(), 'client_id' => $this->order->cliente_id,
            'cash_register_id' => $this->user->caja_id, 'date' => '2026-10-15',
            'currency_id' => $this->currency->id, 'payment_method_id' => $this->method,
            'reference' => 'Recibo del cliente', 'observations' => 'Abono móvil',
            'lines' => [['income_id' => $this->income->id, 'amount' => $amount]]];
    }

    public function test_abonos_de_una_ot_finalizada_generan_solo_el_importe_recibido_en_caja(): void
    {
        $id = $this->postJson($this->path(), $this->payment())->assertCreated()->json('data.id');
        $this->assertDatabaseHas('cobros', ['id' => $id, 'estado' => 3, 'monto' => 2000]);
        $this->assertEquals(2000, MovimientoCaja::where('cobro_id', $id)->sum('monto'));
        $this->getJson($this->path())->assertOk()->assertJsonPath('data.incomes.0.confirmed', 2000)
            ->assertJsonPath('data.incomes.0.total', 6000)->assertJsonPath('data.history.0.id', $id);
        $this->postJson($this->path(), $this->payment(4000))->assertCreated();
        $this->postJson($this->path(), $this->payment(0.01))->assertUnprocessable();
        $this->getJson($this->path())->assertOk()->assertJsonPath('data.incomes.0.confirmed', 6000);
    }

    public function test_reintentar_el_mismo_pago_no_duplica_cobro_ni_movimiento(): void
    {
        $payment = $this->payment();
        $id = $this->postJson($this->path(), $payment)->assertCreated()->json('data.id');
        $this->postJson($this->path(), $payment)->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertEquals(1, MovimientoCaja::where('cobro_id', $id)->count());
        $payment['lines'][0]['amount'] = 1500;
        $this->postJson($this->path(), $payment)->assertUnprocessable();
        $this->assertEquals(2000, Cobro::findOrFail($id)->monto);
    }

    public function test_se_puede_registrar_ingreso_despues_de_finalizar_la_ot(): void
    {
        $this->postJson('/api/v1/orders/'.$this->order->id.'/incomes', [
            'type_id' => $this->income->tipo_ingreso_id, 'date' => '2026-10-15', 'description' => 'Servicio posterior al cierre',
            'quantity' => 1, 'currency_id' => $this->currency->id, 'unit_price' => 300,
        ])->assertCreated()->assertJsonPath('data.total', 300);
        $this->order->update(['estado' => 0]);
        $this->postJson($this->path(), $this->payment())->assertUnprocessable();
    }

    public function test_rechaza_ingreso_de_otra_ot_cliente_cambiado_y_caja_distinta(): void
    {
        $payment = $this->payment();
        $payment['client_id'] = 999999999;
        $this->postJson($this->path(), $payment)->assertUnprocessable();
        $payment = $this->payment();
        $payment['cash_register_id'] = Cajas::create(['nombre' => 'Otra caja '.uniqid(), 'estado' => true])->id;
        $this->postJson($this->path(), $payment)->assertUnprocessable();
        $other = $this->order->replicate(); $other->codigo = 'OT-OTRA-'.uniqid(); $other->save();
        $this->income->update(['orden_trabajo_id' => $other->id]);
        $this->postJson($this->path(), $this->payment())->assertUnprocessable();
        $this->assertFalse(Cobro::where('observaciones', 'Abono móvil')->where('caja_id', $this->user->caja_id)->exists());
    }

    public function test_misma_moneda_y_no_suma_divisas_incompatibles_para_el_saldo(): void
    {
        $otherCurrency = Moneda::where('estado', true)->whereKeyNot($this->currency->id)->firstOrFail();
        $payment = $this->payment(); $payment['currency_id'] = $otherCurrency->id;
        $this->postJson($this->path(), $payment)->assertUnprocessable();
        $foreign = Cobro::crearConCodigo(['cliente_id' => $this->order->cliente_id, 'caja_id' => $this->user->caja_id,
            'metodo_pago_id' => $this->method, 'moneda_id' => $otherCurrency->id, 'fecha' => '2026-10-14', 'estado' => 3]);
        $foreign->detalles()->create(['ingreso_id' => $this->income->id, 'monto' => 50]);
        $this->getJson($this->path())->assertOk()->assertJsonPath('data.incomes.0.confirmed', 0)->assertJsonPath('data.incomes.0.mixed_currency', true);
        $this->postJson($this->path(), $this->payment())->assertUnprocessable();
    }

    public function test_metodo_inactivo_lineas_duplicadas_e_importes_invalidos_no_generan_caja(): void
    {
        foreach ([0, -1, 6000.01, 2.001] as $amount) {
            $this->postJson($this->path(), $this->payment($amount))->assertUnprocessable();
        }
        $payment = $this->payment(); $payment['lines'][] = $payment['lines'][0];
        $this->postJson($this->path(), $payment)->assertUnprocessable();
        MetodosPagos::findOrFail($this->method)->update(['estado' => false]);
        $this->postJson($this->path(), $this->payment())->assertUnprocessable();
        $this->assertFalse(MovimientoCaja::where('caja_id', $this->user->caja_id)->exists());
    }

    public function test_consulta_respeta_permisos_y_snapshot_incluye_catalogos_de_cobro(): void
    {
        $this->getJson('/api/v1/orders/'.$this->order->id)->assertOk()->assertJsonPath('data.collections.incomes.0.id', $this->income->id);
        $this->getJson('/api/v1/offline/snapshot?per_page=1')->assertOk()->assertJsonPath('data.catalogs.collection_settings.cash_register.id', $this->user->caja_id);
        $this->user->syncPermissions(['ordenes_trabajo.view']);
        app('auth')->forgetGuards();
        $this->getJson($this->path())->assertForbidden();
        $this->postJson($this->path(), $this->payment())->assertForbidden();
        $this->getJson('/api/v1/orders/'.$this->order->id)->assertOk()->assertJsonMissingPath('data.collections');
    }

    public function test_upload_resuelve_ingreso_local_y_reintenta_sin_duplicar(): void
    {
        $payment = $this->payment(1000); unset($payment['operation_key']);
        $payment['order_id'] = -9; $payment['lines'][0]['income_id'] = -10;
        $operations = [
            ['local_id' => -9, 'operation_key' => 'order-'.uniqid(), 'type' => 'order.create', 'payload' => [
                'cliente_id' => $this->order->cliente_id, 'fecha' => '2026-10-15', 'peso_mineral' => 2,
                'unidad_peso_id' => UnidadMedida::where('estado', true)->value('id'), 'descripcion' => 'Servicio creado sin conexión',
            ]],
            ['local_id' => -10, 'operation_key' => 'income-'.uniqid(), 'type' => 'income.create', 'payload' => [
                'order_id' => -9, 'type_id' => $this->income->tipo_ingreso_id, 'date' => '2026-10-15',
                'quantity' => 1, 'unit_price' => 2000, 'currency_id' => $this->currency->id,
            ]],
            ['local_id' => -11, 'operation_key' => 'collection-'.uniqid(), 'type' => 'collection.create', 'payload' => $payment],
        ];
        $id = $this->postJson('/api/v1/offline/upload', ['operations' => $operations])->assertOk()->assertJsonPath('data.uploaded', 3)->json('data.id_map.-11');
        $this->postJson('/api/v1/offline/upload', ['operations' => $operations])->assertOk()->assertJsonPath('data.id_map.-11', $id);
        $this->assertEquals(1, MovimientoCaja::where('cobro_id', $id)->count());
        $this->assertEquals(1000, Cobro::findOrFail($id)->monto);
    }

    public function test_conflicto_offline_revierte_el_lote_y_no_deja_cobros_parciales(): void
    {
        $first = $this->payment(2000); $second = $this->payment(5000);
        $first['order_id'] = $second['order_id'] = $this->order->id;
        $operations = array_map(fn ($p) => ['type' => 'collection.create', 'operation_key' => $p['operation_key'], 'payload' => $p], [$first, $second]);
        $this->postJson('/api/v1/offline/upload', ['operations' => $operations])->assertUnprocessable()->assertJsonPath('rolled_back', true);
        $this->assertFalse(MovimientoCaja::where('caja_id', $this->user->caja_id)->exists());
        $this->assertFalse(DB::table('mobile_sync_operations')->whereIn('operation_key', [$first['operation_key'], $second['operation_key']])->exists());
        $operations[1]['payload']['lines'][0]['amount'] = 4000;
        $this->postJson('/api/v1/offline/upload', ['operations' => $operations])->assertOk();
        $this->assertEquals(6000, MovimientoCaja::where('caja_id', $this->user->caja_id)->sum('monto'));
    }
}
