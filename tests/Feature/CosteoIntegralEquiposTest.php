<?php

namespace Tests\Feature;

use App\Models\{Equipo, EquipoConsumo, EquipoInsumo, InventarioProducto, MovimientosInventario, OrdenesTrabajo, ProcesosOrden, Producto, TarifaEnergia, User};
use App\Services\{DistribucionGastosGenerales, InventarioService, UsoEquipoService};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CosteoIntegralEquiposTest extends TestCase
{
    use DatabaseTransactions;

    private ProcesosOrden $proceso;
    private Producto $producto;
    private Equipo $equipo;

    protected function setUp(): void
    {
        parent::setUp();
        $orden = OrdenesTrabajo::firstOrFail()->replicate();
        $orden->codigo = 'OT-EQ-'.uniqid();
        $orden->estado = 1;
        $orden->save();
        $this->proceso = new ProcesosOrden();
        $this->proceso->orden_trabajo_id = $orden->id;
        $this->proceso->etapa_id = DB::table('etapas')->value('id');
        $this->proceso->codigo = 'P-'.uniqid();
        $this->proceso->fecha_inicio = '2026-10-01 07:00';
        $this->proceso->estado = 1;
        $this->proceso->save();
        $this->producto = Producto::firstOrFail()->replicate();
        $this->producto->codigo = 'MAT-EQ-'.uniqid();
        $this->producto->nombre = 'Diésel de prueba';
        $this->producto->save();
        app(InventarioService::class)->registrar([
            'producto_id' => $this->producto->id, 'tipo' => 'entrada', 'cantidad' => 100,
            'costo_unitario' => 45, 'fecha' => '2026-10-01',
        ]);
        $this->equipo = Equipo::create([
            'codigo' => 'EQ-'.uniqid(), 'nombre' => 'Planta de prueba',
            'valor_adquisicion' => 36000, 'valor_residual' => 0, 'vida_util_meses' => 1,
            'depreciacion_activa' => true, 'estado' => 1,
        ]);
        $this->equipo->recursos()->create(['producto_id' => $this->producto->id, 'cantidad_por_hora' => 2]);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
    }

    private function uso(?float $horas = 5, ?Equipo $equipo = null): \App\Models\ProcesoEquipo
    {
        $uso = new \App\Models\ProcesoEquipo();
        $uso->proceso_orden_id = $this->proceso->id;
        return app(UsoEquipoService::class)->guardar($uso, [
            'equipo_id' => ($equipo ?? $this->equipo)->id,
            'fecha_inicio' => '2026-10-01 07:00', 'fecha_fin' => '2026-10-01 15:00',
            'horas_efectivas' => $horas,
        ]);
    }

    private function entregar($uso, float $cantidad = 20): EquipoInsumo
    {
        app(UsoEquipoService::class)->registrarInsumo($uso, [
            'tipo' => 'entrega', 'producto_id' => $this->producto->id, 'cantidad' => $cantidad, 'fecha' => '2026-10-01',
        ]);
        return EquipoInsumo::where('equipo_id', $uso->equipo_id)->latest('id')->firstOrFail();
    }

    private function mover($uso, $lote, string $tipo, float $cantidad): void
    {
        app(UsoEquipoService::class)->registrarInsumo($uso, [
            'tipo' => $tipo, 'equipo_insumo_id' => $lote->id, 'cantidad' => $cantidad, 'fecha' => '2026-10-01',
        ]);
    }

    public function test_horas_efectivas_y_saldo_en_equipo_se_costean_una_sola_vez(): void
    {
        $uso = $this->uso();
        $this->assertEquals(250, $uso->depreciacion_total); // 50 por hora, cinco horas; no ocho.
        $lote = $this->entregar($uso);
        $this->assertEquals(80, $this->producto->fresh()->existencia);
        $this->assertEquals(250, $this->proceso->fresh()->costo_total); // Entregar no equivale a consumir.
        $this->mover($uso, $lote, 'consumo', 10);
        $this->assertEquals(700, $uso->fresh()->costo_uso_nio);
        $this->assertEquals(700, $this->proceso->fresh()->costo_total);
        $this->assertEquals(0, $this->proceso->fresh()->costo_materia_prima);
        $this->assertEquals(700, app(DistribucionGastosGenerales::class)->paraOrden($this->proceso->orden_trabajo)['costo_directo_total_nio']);
        $this->mover($uso, $lote, 'devolucion', 6);
        $this->assertEquals(86, $this->producto->fresh()->existencia);
        $this->assertEquals(4, $lote->fresh()->disponible);
        $this->assertEquals(700, $this->proceso->fresh()->costo_total);
        $this->assertEquals(45, $lote->fresh()->costo_unitario_nio);
    }

    public function test_los_sobrantes_conservan_valor_para_otro_proceso_y_se_puede_anular_consumo(): void
    {
        $uso = $this->uso();
        $lote = $this->entregar($uso, 10);
        $this->mover($uso, $lote, 'consumo', 3);
        $otroProceso = $this->proceso->replicate();
        $otroProceso->codigo = 'P-'.uniqid();
        $otroProceso->save();
        $otro = new \App\Models\ProcesoEquipo();
        $otro->proceso_orden_id = $otroProceso->id;
        $otro = app(UsoEquipoService::class)->guardar($otro, [
            'equipo_id' => $this->equipo->id, 'fecha_inicio' => '2026-10-01 15:00', 'fecha_fin' => '2026-10-01 17:00', 'horas_efectivas' => 1,
        ]);
        $this->mover($otro, $lote, 'consumo', 2);
        $this->assertEquals(140, $otro->fresh()->costo_uso_nio);
        $this->assertEquals(5, $lote->fresh()->disponible);
        app(UsoEquipoService::class)->anularConsumo($otro, $otro->consumos()->value('id'));
        $this->assertEquals(50, $otro->fresh()->costo_uso_nio);
        $this->assertEquals(7, $lote->fresh()->disponible);
        $this->assertEquals(90, $this->producto->fresh()->existencia); // La anulación vuelve al equipo.
    }

    public function test_el_costo_por_tiempo_conserva_parametros_y_tarifa_al_actualizar_horas(): void
    {
        TarifaEnergia::create(['tarifa_kwh_nio' => 10]);
        $this->equipo->update(['consumo_kw' => 2]);
        $uso = $this->uso(2);
        $this->assertEquals(140, $uso->costo_uso_nio);
        $this->equipo->update(['consumo_kw' => 9, 'valor_adquisicion' => 72000]);
        TarifaEnergia::create(['tarifa_kwh_nio' => 20]);
        $uso = app(UsoEquipoService::class)->guardar($uso, ['horas_efectivas' => 3]);
        $this->assertEquals(210, $uso->costo_uso_nio);
        $this->assertEquals(2, $uso->potencia_kw_usada);
        $this->assertEquals(10, $uso->tarifa_kwh_nio);
    }

    public function test_horas_pendientes_no_cobran_el_tiempo_asignado(): void
    {
        $uso = $this->uso(null);
        $this->assertEquals(0, $uso->costo_uso_nio);
        $this->assertSame('Pendiente de horas efectivas', $uso->estadoCosteo());
        $uso = app(UsoEquipoService::class)->guardar($uso, ['horometro_inicial' => 100, 'horometro_final' => 105]);
        $this->assertEquals(5, $uso->horas_efectivas);
        $this->assertEquals(250, $uso->costo_uso_nio);
    }

    public function test_no_se_puede_consumir_mas_del_saldo_y_la_operacion_es_atomica(): void
    {
        $uso = $this->uso();
        $lote = $this->entregar($uso, 2);
        try {
            $this->mover($uso, $lote, 'consumo', 3);
            $this->fail('Debe rechazar consumo mayor al saldo.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cantidad', $e->errors());
        }
        $this->assertEquals(2, $lote->fresh()->disponible);
        $this->assertEquals(0, $uso->consumos()->count());
    }

    public function test_web_y_api_permiten_gestionar_trabajo_y_consumos(): void
    {
        $uso = $this->uso();
        $parametros = [$this->proceso->orden_trabajo_id, $this->proceso->id, $uso->id];
        $this->get(route('procesos.ordenes_trabajo.procesos.equipos.gestion', $parametros))
            ->assertOk()->assertSee('Trabajo efectivo')->assertSee('Almacén y saldo');
        Sanctum::actingAs(auth()->user());
        $url = '/api/v1/orders/'.$parametros[0].'/processes/'.$parametros[1].'/equipment/'.$parametros[2];
        $this->putJson($url.'/usage', ['horas_efectivas' => 4])->assertOk()->assertJsonPath('data.effective_hours', 4);
        $this->postJson($url.'/supplies', ['tipo' => 'entrega', 'producto_id' => $this->producto->id, 'cantidad' => 10, 'fecha' => '2026-10-01'])
            ->assertOk()->assertJsonCount(1, 'data.lots');
    }

    public function test_crud_configura_componentes_independientes_y_conserva_sus_recursos(): void
    {
        $datos = [
            'codigo' => 'EQ-'.uniqid(), 'nombre' => 'Planta diésel', 'estado' => 1,
            'configuracion_costeo' => 1, 'depreciacion_activa' => 0, 'electricidad_activa' => 0,
            'recursos' => [['producto_id' => $this->producto->id, 'cantidad_por_hora' => 2]],
        ];
        $this->post(route('admin.equipos.store'), $datos)->assertSessionHasNoErrors()->assertRedirect();
        $equipo = Equipo::where('codigo', $datos['codigo'])->firstOrFail();
        $this->assertFalse($equipo->usaDepreciacion());
        $this->assertFalse($equipo->usaEnergia());
        $this->assertCount(1, $equipo->recursos);
        $this->get(route('admin.equipos.edit', $equipo))->assertOk()->assertSee('Diésel de prueba');
        $datos['electricidad_activa'] = 1;
        $datos['consumo_kw'] = 3;
        $this->put(route('admin.equipos.update', $equipo), $datos)->assertSessionHasNoErrors();
        $this->assertTrue($equipo->fresh()->usaEnergia());
        $this->assertEquals(2, $equipo->fresh()->recursos->first()->cantidad_por_hora);
        $datos['recursos'][] = $datos['recursos'][0];
        $this->post(route('admin.equipos.store'), [...$datos, 'codigo' => 'EQ-'.uniqid()])->assertSessionHasErrors('recursos.0.producto_id');
    }

    public function test_historicos_no_se_recalculan_al_corregir_disponibilidad_u_observaciones(): void
    {
        $uso = $this->uso();
        DB::table('proceso_equipos')->where('id', $uso->id)->update([
            'horas_efectivas' => null, 'depreciacion_aplicada' => null, 'depreciacion_total' => 111.11, 'costo_total_nio' => 111.11,
        ]);
        $uso = app(UsoEquipoService::class)->guardar($uso, ['fecha_fin' => '2026-10-01 16:00', 'observaciones' => 'Corregida disponibilidad']);
        $this->assertEquals(111.11, $uso->costo_uso_nio);
        $this->assertNull($uso->depreciacion_aplicada);
        $uso = app(UsoEquipoService::class)->guardar($uso, ['horas_efectivas' => 3]);
        $this->assertEquals(150, $uso->costo_uso_nio);
    }

    public function test_horometro_y_horas_no_pueden_contradecirse_ni_exceder_el_periodo(): void
    {
        $uso = $this->uso();
        foreach ([['horas_efectivas' => 9], ['horometro_inicial' => 10, 'horometro_final' => 9],
            ['horometro_inicial' => 10, 'horometro_final' => 16], ['horometro_inicial' => 10]] as $datos) {
            try {
                app(UsoEquipoService::class)->guardar($uso, $datos);
                $this->fail('Debe rechazar el trabajo inconsistente.');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
        $this->assertEquals(5, $uso->fresh()->horas_efectivas);
    }

    public function test_una_entrega_sin_existencias_no_crea_lote_y_su_movimiento_no_se_puede_borrar(): void
    {
        $uso = $this->uso();
        try {
            $this->entregar($uso, 101);
            $this->fail('Debe rechazar una entrega sin existencias.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cantidad', $e->errors());
        }
        $this->assertEquals(0, $uso->entregas()->count());
        $lote = $this->entregar($uso);
        $this->assertEquals($uso->id, $lote->proceso_equipo_id);
        try {
            app(InventarioService::class)->revertir(MovimientosInventario::findOrFail($lote->movimiento_id));
            $this->fail('La entrega debe devolverse mediante el flujo del equipo.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('movimiento', $e->errors());
        }
        $this->assertEquals(80, $this->producto->fresh()->existencia);
        $this->assertEquals(20, $lote->fresh()->disponible);
        $this->expectException(ValidationException::class);
        $uso->delete();
    }

    public function test_no_se_puede_usar_el_saldo_de_otro_equipo(): void
    {
        $uso = $this->uso();
        $lote = $this->entregar($uso);
        $otroEquipo = $this->equipo->replicate();
        $otroEquipo->codigo = 'EQ-'.uniqid();
        $otroEquipo->save();
        $otroUso = $this->uso(2, $otroEquipo);
        Sanctum::actingAs(auth()->user());
        $this->postJson('/api/v1/orders/'.$this->proceso->orden_trabajo_id.'/processes/'.$this->proceso->id.'/equipment/'.$otroUso->id.'/supplies', [
            'tipo' => 'consumo', 'equipo_insumo_id' => $lote->id, 'cantidad' => 5, 'fecha' => '2026-10-01',
        ])->assertNotFound();
        $this->assertEquals(20, $lote->fresh()->disponible);
        $this->assertEquals(0, $otroUso->consumos()->count());
    }

    public function test_dashboard_y_serie_mensual_incluyen_consumos_y_electricidad(): void
    {
        $panel = new \App\Services\PanelDelTaller('todo');
        $antes = $panel->dinero();
        $serieAntes = $panel->serieDeDoceMeses();
        TarifaEnergia::create(['tarifa_kwh_nio' => 10]);
        $this->equipo->update(['consumo_kw' => 2]);
        $uso = $this->uso();
        $lote = $this->entregar($uso);
        $this->mover($uso, $lote, 'consumo', 10);
        $despues = $panel->dinero();
        $this->assertEqualsWithDelta(800, $despues['costo_total'] - $antes['costo_total'], 0.01);
        $this->assertNotEquals($serieAntes, $panel->serieDeDoceMeses());
    }

    public function test_previsualizar_una_edicion_conserva_la_tarifa_y_no_se_solapa_consigo_misma(): void
    {
        TarifaEnergia::create(['tarifa_kwh_nio' => 10]);
        $this->equipo->update(['consumo_kw' => 2]);
        $uso = $this->uso(2);
        TarifaEnergia::create(['tarifa_kwh_nio' => 20]);
        $respuesta = $this->getJson(route('procesos.ordenes_trabajo.procesos.equipos.depreciacion', [$this->proceso->orden_trabajo_id, $this->proceso]).'?'.http_build_query([
            'proceso_equipo_id' => $uso->id, 'equipo_id' => $this->equipo->id,
            'fecha_inicio' => '2026-10-01 07:00', 'fecha_fin' => '2026-10-01 15:00', 'horas_efectivas' => 3,
        ]))->assertOk()->assertJsonPath('solapamiento', null);
        $this->assertEquals(10, $respuesta->json('tarifa_kwh_nio'));
        $this->assertEquals(210, $respuesta->json('costo_total_nio'));
        $this->assertEquals(140, $uso->fresh()->costo_uso_nio); // Previsualizar no escribe.
    }
}
