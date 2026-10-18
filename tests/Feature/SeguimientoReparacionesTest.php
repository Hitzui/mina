<?php

namespace Tests\Feature;

use App\Models\{CategoriasCosto, Equipo, EquipoReparacion, Moneda, MovimientosCosto, OrdenesTrabajo, ProcesoEquipo, ProcesosOrden, User};
use App\Services\{SeguimientoOrdenService, UsoEquipoService};
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SeguimientoReparacionesTest extends TestCase
{
    use DatabaseTransactions;

    private OrdenesTrabajo $orden;
    private ProcesosOrden $proceso;
    private ProcesoEquipo $uso;
    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-17 18:00:00'));
        $this->orden = OrdenesTrabajo::firstOrFail()->replicate();
        $this->orden->fill(['codigo' => 'OT-REP-'.uniqid(), 'estado' => 2]);
        $this->orden->save();
        $this->proceso = ProcesosOrden::create([
            'orden_trabajo_id' => $this->orden->id, 'etapa_id' => DB::table('etapas')->value('id'),
            'codigo' => 'P-REP-'.uniqid(), 'fecha_inicio' => '2026-10-15 08:00', 'estado' => 1,
        ]);
        $equipo = Equipo::create(['codigo' => 'EQ-REP-'.uniqid(), 'nombre' => 'Molino de prueba', 'valor_adquisicion' => 36000, 'valor_residual' => 0, 'vida_util_meses' => 1, 'depreciacion_activa' => true, 'estado' => 1]);
        $this->uso = new ProcesoEquipo(['proceso_orden_id' => $this->proceso->id]);
        $this->uso = app(UsoEquipoService::class)->guardar($this->uso, [
            'equipo_id' => $equipo->id, 'fecha_inicio' => '2026-10-15 08:00', 'fecha_fin' => '2026-10-18 08:00',
            'horometro_inicial' => 100, 'horometro_final' => 110,
        ]);
        $this->usuario = User::factory()->create();
        $this->usuario->assignRole('admin');
        Sanctum::actingAs($this->usuario);
        $this->actingAs($this->usuario);
    }

    private function url(string $action = ''): string { return '/api/v1/orders/'.$this->orden->id.'/tracking'.$action; }
    private function datos(array $extra = []): array { return array_replace(['assignment_id' => $this->uso->id, 'started_at' => '2026-10-16T08:00', 'ended_at' => '2026-10-16T14:00', 'reason' => 'Cambio de rodamiento'], $extra); }
    private function reparar(array $extra = []): EquipoReparacion { return app(SeguimientoOrdenService::class)->guardar($this->orden, $this->datos($extra)); }

    public function test_barra_por_procesos_y_reparacion_seis_horas_sin_modificar_horometro_ni_plazo(): void
    {
        $costo = $this->uso->costo_total_nio;
        $this->reparar();
        $otro = $this->proceso->replicate(); $otro->codigo .= '-2'; $otro->fecha_fin = '2026-10-16 18:00'; $otro->save();
        $cancelado = $this->proceso->replicate(); $cancelado->codigo .= '-3'; $cancelado->estado = 0; $cancelado->save();
        $data = $this->getJson($this->url())->assertOk()->assertJsonPath('data.percent', 50)
            ->assertJsonPath('data.total_processes', 2)->assertJsonPath('data.completed_processes', 1)
            ->assertJsonPath('data.cancelled_processes', 1)->assertJsonPath('data.timeline.hours_in_repair', 6)->json('data');
        $this->assertEqualsWithDelta(33.33333, $data['timeline']['segments'][0]['from'], 0.0001);
        $this->assertEqualsWithDelta(41.66667, $data['timeline']['segments'][0]['to'], 0.0001);
        $this->assertSame('2026-10-18 08:00:00', $this->uso->fresh()->fecha_fin->format('Y-m-d H:i:s'));
        $this->assertEquals(10, $this->uso->fresh()->horas_efectivas);
        $this->assertEquals($costo, $this->uso->fresh()->costo_total_nio);
        $this->assertSame(0, MovimientosCosto::where('orden_trabajo_id', $this->orden->id)->count());
    }

    public function test_union_de_dos_equipos_en_reparacion_a_la_vez_no_duplica_tiempo_calendario(): void
    {
        $this->reparar();
        $otroEquipo = $this->uso->equipo->replicate(); $otroEquipo->codigo .= '-OTRO'; $otroEquipo->save();
        $otroUso = $this->uso->replicate(); $otroUso->equipo_id = $otroEquipo->id; $otroUso->save();
        $this->reparar(['assignment_id' => $otroUso->id, 'started_at' => '2026-10-16T12:00', 'ended_at' => '2026-10-16T16:00']);
        $this->getJson($this->url())->assertOk()->assertJsonPath('data.timeline.hours_in_repair', 8)
            ->assertJsonPath('data.assignments.0.hours_in_repair', 6)->assertJsonPath('data.assignments.1.hours_in_repair', 4)
            ->assertJsonCount(1, 'data.timeline.segments');
    }

    public function test_reparacion_abierta_se_corta_al_plazo_y_su_cierre_real_puede_ser_posterior(): void
    {
        $this->uso->update(['fecha_fin' => '2026-10-16 10:00']);
        $r = $this->reparar(['ended_at' => null]);
        $this->getJson($this->url())->assertOk()->assertJsonPath('data.timeline.hours_in_repair', 2);
        $this->putJson($this->url('/repairs/'.$r->id), $this->datos(['expected_version' => 1, 'ended_at' => '2026-10-17T10:00']))->assertOk()->assertJsonPath('data.version', 2);
        $this->getJson($this->url())->assertJsonPath('data.timeline.hours_in_repair', 2);
    }

    public function test_no_admite_fechas_futuras_solapes_ni_inicio_fuera_de_asignacion(): void
    {
        $this->postJson($this->url('/repairs'), $this->datos(['started_at' => '2026-10-14T08:00']))->assertUnprocessable()->assertJsonValidationErrors('started_at');
        $this->postJson($this->url('/repairs'), $this->datos(['ended_at' => '2026-10-19T08:00']))->assertUnprocessable();
        $this->postJson($this->url('/repairs'), $this->datos(['ended_at' => '2026-10-16T07:00']))->assertUnprocessable();
        $this->reparar();
        $this->postJson($this->url('/repairs'), $this->datos())->assertUnprocessable()->assertJsonValidationErrors('started_at');
        $this->postJson($this->url('/repairs'), $this->datos(['started_at' => '2026-10-16T14:00', 'ended_at' => '2026-10-16T15:00']))->assertCreated();
    }

    public function test_edicion_con_version_antigua_no_sobrescribe_cambios_de_otro_dispositivo(): void
    {
        $r = $this->reparar();
        $this->putJson($this->url('/repairs/'.$r->id), $this->datos(['expected_version' => 1, 'reason' => 'Corregido web']))->assertOk();
        $this->putJson($this->url('/repairs/'.$r->id), $this->datos(['expected_version' => 1, 'reason' => 'Versión móvil vieja']))->assertUnprocessable()->assertJsonValidationErrors('expected_version');
        $this->assertSame('Corregido web', $r->fresh()->motivo);
    }

    public function test_ampliacion_manual_conserva_costos_y_rechaza_plazo_obsoleto_y_otro_equipo_ocupado(): void
    {
        $costo = $this->uso->costo_total_nio;
        $this->putJson($this->url('/assignments/'.$this->uso->id.'/deadline'), ['expected_end' => '2026-10-18T08:00', 'ended_at' => '2026-10-19T08:00'])->assertOk();
        $this->assertEquals($costo, $this->uso->fresh()->costo_total_nio);
        $this->putJson($this->url('/assignments/'.$this->uso->id.'/deadline'), ['expected_end' => '2026-10-18T08:00', 'ended_at' => '2026-10-20T08:00'])->assertUnprocessable()->assertJsonValidationErrors('expected_end');
        $otro = new ProcesoEquipo(['proceso_orden_id' => $this->proceso->id]);
        app(UsoEquipoService::class)->guardar($otro, ['equipo_id' => $this->uso->equipo_id, 'fecha_inicio' => '2026-10-19 08:00', 'fecha_fin' => '2026-10-20 08:00']);
        $this->putJson($this->url('/assignments/'.$this->uso->id.'/deadline'), ['expected_end' => '2026-10-19T08:00', 'ended_at' => '2026-10-20T08:00'])->assertUnprocessable();
    }

    public function test_costo_de_reparacion_es_un_solo_movimiento_del_proceso_y_exige_tipo_de_cambio(): void
    {
        $r = $this->reparar();
        $base = Moneda::where('es_moneda_base', true)->firstOrFail();
        $categoria = CategoriasCosto::paraRegistrar()->firstOrFail();
        $datos = ['categoria_costo_id' => $categoria->id, 'moneda_id' => $base->id, 'fecha' => '2026-10-16', 'descripcion' => 'Rodamiento', 'cantidad' => 1, 'costo_unitario' => 500];
        $anterior = $this->proceso->costo_total;
        $this->postJson($this->url('/repairs/'.$r->id.'/costs'), $datos)->assertCreated();
        $this->assertSame(1, $r->costos()->count());
        $this->assertEquals(500, $r->costos()->first()->costo_total_nio);
        $this->assertEquals($anterior + 500, $this->proceso->fresh()->costo_total);
        $extranjera = $base->replicate(); $extranjera->codigo = 'R'.random_int(10, 99); $extranjera->es_moneda_base = false; $extranjera->save();
        $this->postJson($this->url('/repairs/'.$r->id.'/costs'), array_replace($datos, ['moneda_id' => $extranjera->id]))->assertUnprocessable()->assertJsonValidationErrors('moneda_id');
        $this->assertSame(1, $r->costos()->count());
        $this->deleteJson($this->url('/repairs/'.$r->id), ['expected_version' => 1])->assertUnprocessable();
        $r->costos()->first()->delete();
        $this->deleteJson($this->url('/repairs/'.$r->id), ['expected_version' => 1])->assertOk();
        $this->assertSoftDeleted('equipo_reparaciones', ['id' => $r->id]);
    }

    public function test_no_se_puede_borrar_o_cambiar_el_equipo_con_historial_de_reparaciones(): void
    {
        $this->reparar();
        try { $this->uso->delete(); $this->fail('No debe borrar el uso.'); } catch (ValidationException $e) { $this->assertArrayHasKey('equipo_id', $e->errors()); }
        try { app(UsoEquipoService::class)->guardar($this->uso, ['fecha_inicio' => '2026-10-17 08:00']); $this->fail('No debe recortar el historial.'); } catch (ValidationException $e) { $this->assertArrayHasKey('fecha_fin', $e->errors()); }
        $equipo = $this->uso->equipo->replicate(); $equipo->codigo .= '-OTRO'; $equipo->save();
        try { app(UsoEquipoService::class)->guardar($this->uso->fresh(), ['equipo_id' => $equipo->id]); $this->fail('No debe cambiar el equipo.'); } catch (ValidationException $e) { $this->assertArrayHasKey('equipo_id', $e->errors()); }
    }

    public function test_permisos_y_pertenencia_se_validan_en_web_y_api(): void
    {
        $r = $this->reparar();
        $otro = $this->orden->replicate(); $otro->codigo .= '-OTRA'; $otro->save();
        $this->postJson('/api/v1/orders/'.$otro->id.'/tracking/repairs', $this->datos())->assertNotFound();
        $this->putJson('/api/v1/orders/'.$otro->id.'/tracking/repairs/'.$r->id, $this->datos(['expected_version' => 1]))->assertNotFound();
        $user = User::factory()->create(); $user->givePermissionTo('ordenes_trabajo.view');
        Sanctum::actingAs($user); $this->actingAs($user);
        $this->getJson($this->url())->assertOk()->assertJsonCount(0, 'data.assignments')->assertJsonCount(0, 'data.repairs')->assertJsonPath('data.can_edit', false);
        $this->postJson($this->url('/repairs'), $this->datos())->assertForbidden();
    }

    public function test_web_muestra_barras_y_pantalla_guiada_y_snapshot_conserva_progreso_confirmado(): void
    {
        $this->reparar();
        $this->get(route('procesos.ordenes_trabajo.show', $this->orden))->assertOk()->assertSee('Avance de la OT')->assertSee('role="progressbar"', false);
        $this->get(route('procesos.ordenes_trabajo.seguimiento.show', $this->orden))->assertOk()->assertSee('Registrar una reparación')->assertSee('Registrar costo vinculado');
        $this->getJson(route('procesos.ordenes_trabajo.seguimiento.show', $this->orden).'?summary=1')->assertOk()->assertJsonStructure(['html'])->assertSee('data-tracking-summary');
        $this->getJson('/api/v1/orders/'.$this->orden->id)->assertOk()->assertJsonPath('data.tracking.timeline.hours_in_repair', 6);
        $orders = $this->getJson('/api/v1/offline/snapshot?per_page=50')->assertOk()->json('data.orders');
        $download = collect($orders)->firstWhere('id', $this->orden->id);
        $this->assertNotNull($download);
        $this->assertEquals(6, $download['tracking']['timeline']['hours_in_repair']);
    }

    public function test_subida_offline_idempotente_resuelve_reparacion_temporal_y_su_cierre(): void
    {
        $ops = [
            ['local_id' => -21, 'operation_key' => 'rep-'.uniqid(), 'type' => 'repair.create', 'payload' => $this->datos(['order_id' => $this->orden->id, 'ended_at' => null])],
            ['local_id' => -22, 'operation_key' => 'cierre-'.uniqid(), 'type' => 'repair.update', 'payload' => $this->datos(['order_id' => $this->orden->id, 'repair_id' => -21, 'expected_version' => 1])],
        ];
        $this->postJson('/api/v1/offline/upload', ['operations' => $ops])->assertOk()->assertJsonPath('data.uploaded', 2);
        $this->postJson('/api/v1/offline/upload', ['operations' => $ops])->assertOk()->assertJsonPath('data.uploaded', 2);
        $this->assertSame(1, $this->uso->reparaciones()->count());
        $this->assertEquals(2, $this->uso->reparaciones()->first()->version);
    }

    public function test_conflicto_offline_revierte_todo_el_lote_sin_perder_cambios_centrales(): void
    {
        $r = $this->reparar();
        $ops = [
            ['operation_key' => 'nuevo-'.uniqid(), 'type' => 'repair.create', 'payload' => $this->datos(['order_id' => $this->orden->id, 'started_at' => '2026-10-17T08:00', 'ended_at' => '2026-10-17T10:00'])],
            ['operation_key' => 'viejo-'.uniqid(), 'type' => 'repair.update', 'payload' => $this->datos(['order_id' => $this->orden->id, 'repair_id' => $r->id, 'expected_version' => 99])],
        ];
        $this->postJson('/api/v1/offline/upload', ['operations' => $ops])->assertUnprocessable()->assertJsonPath('rolled_back', true);
        $this->assertSame(1, $this->uso->reparaciones()->count());
        $this->assertEquals(1, $r->fresh()->version);
    }

    public function test_subida_resuelve_asignacion_y_reparacion_temporales_y_registra_su_costo_una_vez(): void
    {
        $base = Moneda::where('es_moneda_base', true)->firstOrFail();
        $categoria = CategoriasCosto::paraRegistrar()->firstOrFail();
        $ops = [
            ['local_id' => -31, 'operation_key' => 'uso-'.uniqid(), 'type' => 'equipment.assign', 'payload' => [
                'order_id' => $this->orden->id, 'process_id' => $this->proceso->id, 'equipo_id' => $this->uso->equipo_id,
                'fecha_inicio' => '2026-10-14T08:00', 'fecha_fin' => '2026-10-15T08:00',
            ]],
            ['local_id' => -32, 'operation_key' => 'rep-'.uniqid(), 'type' => 'repair.create', 'payload' => [
                'order_id' => $this->orden->id, 'assignment_id' => -31, 'started_at' => '2026-10-14T10:00', 'ended_at' => '2026-10-14T12:00', 'reason' => 'Correa',
            ]],
            ['local_id' => -33, 'operation_key' => 'costo-'.uniqid(), 'type' => 'repair_cost.create', 'payload' => [
                'order_id' => $this->orden->id, 'repair_id' => -32, 'categoria_costo_id' => $categoria->id, 'moneda_id' => $base->id,
                'fecha' => '2026-10-14', 'descripcion' => 'Correa de molino', 'cantidad' => 1, 'costo_unitario' => 450,
            ]],
        ];
        $response = $this->postJson('/api/v1/offline/upload', ['operations' => $ops])->assertOk()->assertJsonPath('data.uploaded', 3);
        $this->postJson('/api/v1/offline/upload', ['operations' => $ops])->assertOk()->assertJsonPath('data.uploaded', 3);
        $repair = EquipoReparacion::findOrFail($response->json('data.id_map.-32'));
        $this->assertEquals($response->json('data.id_map.-31'), $repair->proceso_equipo_id);
        $this->assertSame(1, $repair->costos()->count());
        $this->assertEquals(450, $repair->costos()->sum('costo_total_nio'));
        $this->assertEquals($this->proceso->id, $repair->costos()->first()->proceso_orden_id);
    }

    public function test_proceso_cerrado_no_admite_nuevos_gastos_reparaciones_o_ampliaciones_pero_permite_terminar_la_existente(): void
    {
        $r = $this->reparar(['ended_at' => null]);
        $this->proceso->fecha_fin = '2026-10-17 12:00'; $this->proceso->save();
        $this->getJson($this->url())->assertOk()->assertJsonPath('data.assignments.0.can_register', false)->assertJsonPath('data.repairs.0.can_cost', false);
        $this->postJson($this->url('/repairs'), $this->datos())->assertUnprocessable();
        $this->putJson($this->url('/assignments/'.$this->uso->id.'/deadline'), ['expected_end' => '2026-10-18T08:00', 'ended_at' => '2026-10-19T08:00'])->assertUnprocessable();
        $this->postJson($this->url('/repairs/'.$r->id.'/costs'), [])->assertUnprocessable()->assertJsonPath('message', 'El proceso está cerrado. Reábralo para registrar costos nuevos.');
        $this->putJson($this->url('/repairs/'.$r->id), $this->datos(['expected_version' => 1]))->assertOk()->assertJsonPath('data.version', 2);
    }

    public function test_reparacion_externa_o_interna_con_trabajo_realizado_se_conserva_en_api_web_y_snapshot(): void
    {
        $id = $this->postJson($this->url('/repairs'), $this->datos(['ended_at' => null, 'repair_location' => 'externa']))
            ->assertCreated()->json('data.id');
        $this->getJson($this->url())->assertJsonPath('data.repairs.0.repair_location', 'externa')->assertJsonPath('data.repairs.0.work_description', null);
        $this->putJson($this->url('/repairs/'.$id), $this->datos(['expected_version' => 1, 'repair_location' => 'interna', 'work_description' => 'Se cambiaron los rodamientos y se alineó el motor.']))->assertOk();
        // Un cliente anterior omite los campos nuevos: no debe borrar lo ya registrado.
        $this->putJson($this->url('/repairs/'.$id), $this->datos(['expected_version' => 2]))->assertOk();
        $this->getJson($this->url())->assertJsonPath('data.repairs.0.repair_location', 'interna')->assertJsonPath('data.repairs.0.work_description', 'Se cambiaron los rodamientos y se alineó el motor.');
        $this->get(route('procesos.ordenes_trabajo.seguimiento.show', $this->orden))->assertOk()->assertSee('Reparación interna')->assertSee('Se cambiaron los rodamientos')->assertSee('Costo total de reparación');
        $download = collect($this->getJson('/api/v1/offline/snapshot?per_page=50')->assertOk()->json('data.orders'))->firstWhere('id', $this->orden->id);
        $this->assertSame('interna', $download['tracking']['repairs'][0]['repair_location']);
        $this->assertSame('Se cambiaron los rodamientos y se alineó el motor.', EquipoReparacion::findOrFail($id)->trabajo_realizado);
        $this->postJson($this->url('/repairs'), $this->datos(['repair_location' => 'otro']))->assertUnprocessable()->assertJsonValidationErrors('repair_location');
    }

    public function test_tiempo_total_y_estado_del_equipo_incluyen_reparacion_fuera_del_plazo_sin_alterar_barra(): void
    {
        $this->uso->update(['fecha_fin' => '2026-10-16 10:00']);
        $r = $this->reparar(['ended_at' => null, 'repair_location' => 'externa']);
        $this->getJson($this->url())->assertOk()->assertJsonPath('data.repairs.0.total_hours', 34)->assertJsonPath('data.repairs.0.hours_in_assignment', 2)->assertJsonPath('data.timeline.hours_in_repair', 2);
        $this->assertEquals(1, $this->uso->equipo->reparaciones()->whereNull('fin')->count());
        $rows = $this->getJson(route('admin.equipos.index').'?length=100', ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json('data');
        $row = collect($rows)->firstWhere('id', $this->uso->equipo_id);
        $this->assertStringContainsString('En reparación', $row['estado']);
        $this->get(route('admin.equipos.show', $this->uso->equipo))->assertOk()->assertSee('Poner en reparación / consultar');
        $this->putJson($this->url('/repairs/'.$r->id), $this->datos(['expected_version' => 1, 'ended_at' => '2026-10-17T10:00', 'repair_location' => 'externa', 'work_description' => 'Reparado y probado en taller.']))->assertOk();
        $this->getJson($this->url())->assertJsonPath('data.repairs.0.total_hours', 26)->assertJsonPath('data.repairs.0.hours_in_assignment', 2);
        $this->assertEquals(0, $this->uso->equipo->reparaciones()->whereNull('fin')->count());
    }

    public function test_importe_global_de_reparacion_no_se_multiplica_por_cantidad_y_el_reintento_no_lo_duplica(): void
    {
        $r = $this->reparar(['repair_location' => 'interna']);
        $op = ['local_id' => -501, 'operation_key' => 'total-rep-'.uniqid(), 'type' => 'repair_cost.create', 'payload' => [
            'order_id' => $this->orden->id, 'repair_id' => $r->id, 'total_cost' => 1250.50, 'cantidad' => 8,
            'descripcion' => 'Cambio de rodamientos y ajuste del motor.', 'fecha' => '2026-10-16',
            'categoria_costo_id' => CategoriasCosto::paraRegistrar()->firstOrFail()->id,
            'moneda_id' => Moneda::where('es_moneda_base', true)->firstOrFail()->id,
        ]];
        $this->postJson('/api/v1/offline/upload', ['operations' => [$op]])->assertOk();
        $this->postJson('/api/v1/offline/upload', ['operations' => [$op]])->assertOk();
        $this->assertSame(1, $r->costos()->count());
        $this->assertEquals(1, $r->costos()->first()->cantidad);
        $this->assertEquals(1250.50, $r->costos()->first()->costo_total_nio);
        $this->get(route('procesos.ordenes_trabajo.seguimiento.show', $this->orden))->assertOk()->assertSee('Costo total registrado:')->assertSee('1,250.50');
    }
}
