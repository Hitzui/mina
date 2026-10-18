<?php

namespace Tests\Feature;

use App\Models\{AcuerdosOrden, CategoriasCosto, Ingreso, LamaLote, Moneda, MovimientosCosto, OrdenesTrabajo, PreciosOro, Recuperaciones, TiposIngreso, TiposParticipacion, UnidadMedida, User, ValoracionesOro};
use App\Services\LamaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FlujoLamasTest extends TestCase
{
    use DatabaseTransactions;

    private OrdenesTrabajo $origen;
    private AcuerdosOrden $acuerdo;
    private User $admin;
    private Moneda $base;
    private UnidadMedida $unidad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
        $this->base = Moneda::base();
        $this->unidad = UnidadMedida::where('estado', true)->firstOrFail();
        $this->origen = OrdenesTrabajo::firstOrFail()->replicate();
        $this->origen->codigo = 'OT-TEST-LAMA-'.uniqid();
        $this->origen->fecha = '2099-01-01';
        $this->origen->estado = 1;
        $this->origen->tipo_trabajo = 'servicio';
        $this->origen->lama_lote_id = null;
        $this->origen->save();
        $this->acuerdo = AcuerdosOrden::create([
            'orden_trabajo_id' => $this->origen->id, 'tipo_participacion_id' => TiposParticipacion::where('estado', true)->value('id'),
            'tarifa_servicio' => 6000, 'moneda_id' => $this->base->id, 'estado' => true,
            'lama_destino' => 'empresa', 'lama_porcentaje_empresa' => 70,
        ]);
    }

    private function lote(array $cambios = []): LamaLote
    {
        return app(LamaService::class)->registrar($this->origen, [
            'acuerdo_id' => $this->acuerdo->id, 'fecha' => '2099-01-02', 'cantidad_estimada' => 2.5,
            'unidad_id' => $this->unidad->id, 'ubicacion' => 'Depósito 1', ...$cambios,
        ]);
    }

    private function api(): string { return '/api/v1/orders/'.$this->origen->id.'/lama'; }

    public function test_lote_completo_hereda_reparto_y_conserva_cliente_cuando_hay_participacion(): void
    {
        $lote = $this->lote();
        $this->acuerdo->update(['lama_porcentaje_empresa' => 100]);
        $orden = app(LamaService::class)->iniciar($lote, '2099-01-03');
        $this->assertEquals(70, $lote->fresh()->porcentaje_empresa);
        $this->assertEquals($this->origen->cliente_id, $orden->cliente_id);
        $this->assertEquals(2.5, $orden->peso_mineral);
        $this->assertEquals('tratamiento_lama', $orden->tipo_trabajo);
        $heredado = $orden->acuerdos_ordens_where_orden_trabajo()->firstOrFail();
        $this->assertEquals(70, $heredado->porcentaje_empresa);
        $this->assertEquals(30, $heredado->porcentaje_cliente);
        $this->assertNull($heredado->tarifa_servicio);
        $this->get(route('procesos.ordenes_trabajo.lama.show', $orden))->assertOk()->assertSee('OT de tratamiento')->assertSee('Registrar oro recuperado');
        Sanctum::actingAs($this->admin);
        $this->postJson($this->api().'/lotes/'.$lote->id.'/tratamiento', ['fecha' => '2099-01-04'])->assertUnprocessable();
        $this->assertEquals(1, OrdenesTrabajo::where('lama_lote_id', $lote->id)->count());
    }

    public function test_tratamiento_propio_no_crea_cliente_ficticio_ni_ingreso(): void
    {
        $this->acuerdo->update(['lama_porcentaje_empresa' => 100]);
        $lote = $this->lote();
        $clientes = DB::table('clientes')->count();
        $orden = app(LamaService::class)->iniciar($lote, '2099-01-03');
        $this->assertNull($orden->cliente_id);
        $this->assertEquals($clientes, DB::table('clientes')->count());
        $this->assertFalse(Ingreso::where('orden_trabajo_id', $orden->id)->exists());
        $this->get(route('procesos.ordenes_trabajo.show', $orden))->assertOk()->assertSee('tratamiento propio de lama');
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/orders/'.$orden->id)->assertOk()->assertJsonPath('data.work_type', 'tratamiento_lama')->assertJsonPath('data.client.id', null);
    }

    public function test_un_acuerdo_sin_condiciones_no_admite_lote_y_el_porcentaje_es_obligatorio(): void
    {
        $this->acuerdo->update(['lama_destino' => 'sin_definir', 'lama_porcentaje_empresa' => null]);
        Sanctum::actingAs($this->admin);
        $this->postJson($this->api().'/lotes', [
            'acuerdo_id' => $this->acuerdo->id, 'fecha' => '2099-01-02', 'cantidad_estimada' => 1,
            'unidad_id' => $this->unidad->id, 'ubicacion' => 'Patio',
        ])->assertUnprocessable()->assertJsonValidationErrors('acuerdo_id');
        $this->postJson('/api/v1/orders/'.$this->origen->id.'/agreements', [
            'participation_type_id' => $this->acuerdo->tipo_participacion_id, 'service_fee' => 5000,
            'currency_id' => $this->base->id, 'active' => true, 'lama_destino' => 'empresa',
        ])->assertUnprocessable()->assertJsonValidationErrors('lama_porcentaje_empresa');
    }

    public function test_lama_que_conserva_el_cliente_no_se_utiliza_como_saldo_propio(): void
    {
        $this->acuerdo->update(['lama_destino' => 'cliente']);
        $lote = $this->lote();
        Sanctum::actingAs($this->admin);
        $this->postJson($this->api().'/lotes/'.$lote->id.'/tratamiento', ['fecha' => '2099-01-03'])->assertUnprocessable();
        $this->getJson($this->api())->assertOk()->assertJsonPath('data.lots.0.available_quantity', 0);
    }

    public function test_se_puede_corregir_o_anular_solo_antes_de_iniciar_el_tratamiento(): void
    {
        $lote = $this->lote();
        Sanctum::actingAs($this->admin);
        $this->putJson($this->api().'/lotes/'.$lote->id, ['cantidad_estimada' => 3, 'ubicacion' => 'Patio'])->assertOk();
        $this->assertEquals(3, $lote->fresh()->cantidad_estimada);
        $orden = app(LamaService::class)->iniciar($lote, '2099-01-03');
        $this->putJson($this->api().'/lotes/'.$lote->id, ['cantidad_estimada' => 4, 'ubicacion' => 'Patio'])->assertUnprocessable();
        $this->deleteJson($this->api().'/lotes/'.$lote->id)->assertUnprocessable();
        $this->putJson('/api/v1/orders/'.$orden->id.'/agreements/'.$orden->acuerdos_ordens_where_orden_trabajo()->value('id'), [
            'participation_type_id' => $this->acuerdo->tipo_participacion_id, 'client_percentage' => 0, 'company_percentage' => 100,
            'currency_id' => $this->base->id, 'active' => true,
        ])->assertUnprocessable();
        $nuevo = $this->lote();
        $this->deleteJson($this->api().'/lotes/'.$nuevo->id)->assertOk();
        $this->assertSoftDeleted('lama_lotes', ['id' => $nuevo->id]);
    }

    public function test_los_sobrantes_se_registran_despues_del_cierre_pero_no_de_una_cancelacion(): void
    {
        $this->origen->update(['estado' => 3]);
        $lote = $this->lote();
        $this->assertNotNull($lote->id);
        $this->origen->update(['estado' => 0]);
        Sanctum::actingAs($this->admin);
        $this->postJson($this->api().'/lotes/'.$lote->id.'/tratamiento', ['fecha' => '2099-01-03'])->assertUnprocessable();
    }

    private function costo(OrdenesTrabajo $orden, float $importe): void
    {
        MovimientosCosto::create([
            'orden_trabajo_id' => $orden->id, 'categoria_costo_id' => CategoriasCosto::firstOrFail()->id,
            'fecha' => '2099-01-03', 'descripcion' => 'Costo prueba', 'cantidad' => 1, 'costo_unitario' => $importe,
            'costo_total' => $importe, 'costo_unitario_nio' => $importe, 'costo_total_nio' => $importe, 'moneda_id' => $this->base->id,
        ]);
    }

    public function test_resultado_conjunto_suma_solo_la_parte_empresa_y_no_duplica_servicio_ni_venta_oro(): void
    {
        $lote = $this->lote();
        $orden = app(LamaService::class)->iniciar($lote, '2099-01-03');
        $this->costo($this->origen, 7000);
        $this->costo($orden, 4000);
        Ingreso::create(['orden_trabajo_id' => $this->origen->id, 'tipo_ingreso_id' => TiposIngreso::firstOrFail()->id,
            'fecha' => '2099-01-03', 'descripcion' => 'Servicio', 'cantidad' => 1, 'precio_unitario' => 6000,
            'total' => 6000, 'total_nio' => 6000, 'moneda_id' => $this->base->id]);
        // La venta del oro no puede sumarse además de la valoración del mismo oro.
        Ingreso::create(['orden_trabajo_id' => $orden->id, 'tipo_ingreso_id' => TiposIngreso::firstOrFail()->id,
            'fecha' => '2099-01-03', 'descripcion' => 'Venta oro', 'cantidad' => 1, 'precio_unitario' => 12000,
            'total' => 12000, 'total_nio' => 12000, 'moneda_id' => $this->base->id]);
        $rec = Recuperaciones::create(['orden_trabajo_id' => $orden->id, 'fecha' => '2099-01-03', 'gramos' => 10, 'pureza' => 0.8]);
        $this->assertNull(app(LamaService::class)->datos($this->origen, $this->admin)['financial_summary']['reference_result_nio']);
        ValoracionesOro::create(['recuperacion_id' => $rec->id, 'fecha' => '2099-01-03', 'valor' => 12000, 'valor_nio' => 12000, 'moneda_id' => $this->base->id]);
        $datos = app(LamaService::class)->datos($this->origen, $this->admin);
        $this->assertEquals(8400, $datos['financial_summary']['company_gold_value_nio']);
        $this->assertEquals(3400, $datos['financial_summary']['reference_result_nio']);
        $this->assertEquals(7, $datos['lots'][0]['company_grams']);
        $this->assertEquals(4400, $datos['lots'][0]['result']['reference_result_nio']);
        $this->get(route('procesos.ordenes_trabajo.lama.show', $this->origen))->assertOk()->assertSee('Servicio original y aprovechamiento posterior');
    }

    public function test_api_registra_recuperacion_y_valoracion_reutilizando_pureza_y_precio(): void
    {
        $orden = app(LamaService::class)->iniciar($this->lote(), '2099-01-03');
        $api = '/api/v1/orders/'.$orden->id.'/lama';
        Sanctum::actingAs($this->admin);
        PreciosOro::create(['fecha' => '2099-01-03', 'precio' => 1500, 'unidad' => 'gramo', 'moneda_id' => $this->base->id]);
        $this->postJson($api.'/recuperaciones', ['fecha' => '2099-01-03', 'gramos' => 10, 'pureza' => 0.8])->assertOk();
        $rec = Recuperaciones::where('orden_trabajo_id', $orden->id)->firstOrFail();
        $this->postJson($api.'/valoraciones', ['fecha' => '2099-01-03', 'recuperacion_id' => $rec->id, 'precio_moneda' => $this->base->id])->assertOk();
        $this->assertEquals(12000, $rec->fresh()->valoracion->valor_nio);
        $rec->update(['gramos' => 5]);
        $this->assertNull($rec->fresh()->valoracion->valor_nio);
        $this->getJson($api)->assertOk()->assertJsonPath('data.lots.0.recoveries.0.needs_valuation', true)
            ->assertJsonPath('data.lots.0.result.reference_result_nio', null);
        $editor = User::factory()->create();
        $editor->givePermissionTo(['ordenes_trabajo.view', 'valoraciones_oro.create']);
        Sanctum::actingAs($editor);
        $this->postJson($api.'/valoraciones', ['fecha' => '2099-01-03', 'recuperacion_id' => $rec->id, 'precio_moneda' => $this->base->id])->assertForbidden();
        Sanctum::actingAs($this->admin);
        $this->postJson($api.'/valoraciones', ['fecha' => '2099-01-03', 'recuperacion_id' => $rec->id, 'precio_moneda' => $this->base->id])->assertOk();
        $this->assertEquals(6000, $rec->fresh()->valoracion->valor_nio);
        $otra = $this->origen->replicate(); $otra->codigo = 'OT-X-'.uniqid(); $otra->save();
        $recAjena = Recuperaciones::create(['orden_trabajo_id' => $otra->id, 'fecha' => '2099-01-03', 'gramos' => 1]);
        $this->postJson($api.'/valoraciones', ['fecha' => '2099-01-03', 'recuperacion_id' => $recAjena->id, 'precio_moneda' => $this->base->id])->assertNotFound();
    }

    public function test_api_no_expone_costos_ni_oro_sin_permisos_y_snapshot_incluye_lotes(): void
    {
        $this->lote();
        $lector = User::factory()->create();
        $lector->givePermissionTo('ordenes_trabajo.view');
        Sanctum::actingAs($lector);
        $this->getJson($this->api())->assertOk()->assertJsonPath('data.can_register', false)
            ->assertJsonPath('data.financial_summary', null)->assertJsonPath('data.lots.0.recovered_grams', null);
        $this->postJson($this->api().'/lotes', [])->assertForbidden();
        $snapshot = $this->getJson('/api/v1/offline/snapshot?per_page=50')->assertOk();
        $order = collect($snapshot->json('data.orders'))->firstWhere('id', $this->origen->id);
        $this->assertEquals(1, count($order['lama']['lots']));
    }
}
