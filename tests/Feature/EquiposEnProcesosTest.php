<?php

namespace Tests\Feature;

use App\Models\Equipo;
use App\Models\OrdenesTrabajo;
use App\Models\ProcesoEquipo;
use App\Models\ProcesosOrden;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Pantallas de equipos y de su uso en los procesos.
 *
 * Comprueba tres cosas que son reglas del negocio y no detalles de la
 * pantalla:
 *   - el equipo es dato maestro y su depreciacion sale de sus tres datos;
 *   - un equipo con historial no se borra, porque los costos ya cargados
 *     dependen de el;
 *   - un equipo no puede trabajar en dos procesos al mismo tiempo, y el
 *     periodo lleva hora por eso.
 */
class EquiposEnProcesosTest extends TestCase
{
    use DatabaseTransactions;

    private OrdenesTrabajo $orden;

    private ProcesosOrden $proceso;

    private ProcesosOrden $otroProceso;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'eq-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        $this->actingAs($admin);

        $this->orden = OrdenesTrabajo::firstOrFail();

        $this->proceso = $this->crearProceso();
        $this->otroProceso = $this->crearProceso();
    }

    private function crearProceso(): ProcesosOrden
    {
        $etapa = DB::table('etapas')->first();

        $proceso = new ProcesosOrden();
        $proceso->orden_trabajo_id = $this->orden->id;
        $proceso->etapa_id = $etapa->id;
        $proceso->codigo = 'P-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $proceso->fecha_inicio = '2026-01-01 08:00:00';
        $proceso->fecha_fin = '2026-12-31 17:00:00';
        $proceso->peso_entrada = 1;
        $proceso->peso_salida = 1;
        $proceso->estado = 1;
        $proceso->save();

        return $proceso;
    }

    private function crearEquipo(array $extra = []): Equipo
    {
        $e = new Equipo();
        $e->codigo = 'EQ-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $e->nombre = 'Molino de prueba';
        $e->valor_adquisicion = 120000;
        $e->valor_residual = 20000;
        $e->vida_util_meses = 120;
        $e->estado = 1;

        foreach ($extra as $k => $v) {
            $e->$k = $v;
        }

        $e->save();

        return $e;
    }

    private function ruta(string $accion = '', ?ProcesosOrden $proceso = null): string
    {
        $proceso ??= $this->proceso;

        return "/procesos/ordenes-trabajo/{$this->orden->id}/procesos/{$proceso->id}/equipos{$accion}";
    }

    // ==================================================================
    // La depreciacion se calcula sola
    // ==================================================================

    public function test_al_registrar_el_uso_la_depreciacion_se_calcula_sola(): void
    {
        $equipo = $this->crearEquipo();

        // No se manda depreciacion_total en ningun sitio: la tiene que
        // calcular el servidor
        $this->post($this->ruta(), [
            'equipo_id' => $equipo->id,
            'fecha_inicio' => '2026-10-01 08:00',
            'fecha_fin' => '2026-10-05 08:00',
        ])->assertRedirect();

        $uso = ProcesoEquipo::where('equipo_id', $equipo->id)->firstOrFail();

        // (120000 - 20000) / (120 * 30) = 27.7778 por dia, 4 dias
        $this->assertEqualsWithDelta(111.11, (float) $uso->depreciacion_total, 0.02);
    }

    public function test_el_periodo_lleva_hora_y_no_solo_fecha(): void
    {
        $columnas = DB::select(
            'SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                AND COLUMN_NAME IN (?, ?)',
            ['proceso_equipos', 'fecha_inicio', 'fecha_fin']
        );

        $this->assertCount(2, $columnas);

        foreach ($columnas as $columna) {
            $this->assertEquals(
                'datetime',
                strtolower($columna->DATA_TYPE),
                "La columna {$columna->COLUMN_NAME} debe ser datetime, no date: " .
                'un equipo puede pasar de un proceso a otro el mismo dia'
            );
        }
    }

    public function test_un_uso_sin_fecha_de_fin_sigue_asignado(): void
    {
        $equipo = $this->crearEquipo();

        $this->post($this->ruta(), [
            'equipo_id' => $equipo->id,
            'fecha_inicio' => '2026-10-01 08:00',
        ])->assertRedirect();

        $uso = ProcesoEquipo::where('equipo_id', $equipo->id)->firstOrFail();

        $this->assertNull($uso->fecha_fin, 'Sin fecha de fin el equipo sigue en el proceso');
    }

    // ==================================================================
    // Un equipo no puede estar en dos procesos a la vez
    // ==================================================================

    public function test_no_se_puede_asignar_un_equipo_ya_ocupado(): void
    {
        $equipo = $this->crearEquipo();

        $this->post($this->ruta('', $this->proceso), [
            'equipo_id' => $equipo->id,
            'fecha_inicio' => '2026-10-01 08:00',
            'fecha_fin' => '2026-10-05 08:00',
        ])->assertRedirect();

        $antes = ProcesoEquipo::where('equipo_id', $equipo->id)->count();

        // Del 3 al 7: se cruza con el primer uso
        $this->post($this->ruta('', $this->otroProceso), [
            'equipo_id' => $equipo->id,
            'fecha_inicio' => '2026-10-03 08:00',
            'fecha_fin' => '2026-10-07 08:00',
        ])->assertSessionHasErrors('fecha_inicio');

        $this->assertSame(
            $antes,
            ProcesoEquipo::where('equipo_id', $equipo->id)->count(),
            'No debe haberse guardado el segundo uso'
        );
    }

    public function test_no_se_puede_asignar_por_un_minuto_de_solapamiento(): void
    {
        $equipo = $this->crearEquipo();

        $this->post($this->ruta('', $this->proceso), [
            'equipo_id' => $equipo->id,
            'fecha_inicio' => '2026-10-01 08:00',
            'fecha_fin' => '2026-10-05 08:00',
        ])->assertRedirect();

        // Empieza un minuto antes de que termine el otro
        $this->post($this->ruta('', $this->otroProceso), [
            'equipo_id' => $equipo->id,
            'fecha_inicio' => '2026-10-05 07:59',
            'fecha_fin' => '2026-10-08 08:00',
        ])->assertSessionHasErrors('fecha_inicio');
    }

    public function test_dos_equipos_distintos_si_pueden_a_la_vez(): void
    {
        $a = $this->crearEquipo();
        $b = $this->crearEquipo();

        $this->post($this->ruta(), [
            'equipo_id' => $a->id,
            'fecha_inicio' => '2026-10-01 08:00',
            'fecha_fin' => '2026-10-05 08:00',
        ])->assertRedirect();

        $this->post($this->ruta(), [
            'equipo_id' => $b->id,
            'fecha_inicio' => '2026-10-01 08:00',
            'fecha_fin' => '2026-10-05 08:00',
        ])->assertRedirect();

        $this->assertSame(2, ProcesoEquipo::where('proceso_orden_id', $this->proceso->id)->count());
    }

    public function test_un_equipo_abierto_bloquea_lo_que_venga_despues(): void
    {
        $equipo = $this->crearEquipo();

        $this->post($this->ruta('', $this->proceso), [
            'equipo_id' => $equipo->id,
            'fecha_inicio' => '2026-10-01 08:00',
        ])->assertRedirect();

        $this->post($this->ruta('', $this->otroProceso), [
            'equipo_id' => $equipo->id,
            'fecha_inicio' => '2026-11-01 08:00',
            'fecha_fin' => '2026-11-05 08:00',
        ])->assertSessionHasErrors('fecha_inicio');
    }

    public function test_editar_el_periodo_no_se_choca_consigo_mismo(): void
    {
        $equipo = $this->crearEquipo();

        $this->post($this->ruta(), [
            'equipo_id' => $equipo->id,
            'fecha_inicio' => '2026-10-01 08:00',
            'fecha_fin' => '2026-10-05 08:00',
        ])->assertRedirect();

        $uso = ProcesoEquipo::where('equipo_id', $equipo->id)->firstOrFail();

        $this->put($this->ruta("/{$uso->id}"), [
            'equipo_id' => $equipo->id,
            'fecha_inicio' => '2026-10-01 09:00',
            'fecha_fin' => '2026-10-05 09:00',
        ])->assertRedirect();

        $this->assertEquals(1, ProcesoEquipo::where('equipo_id', $equipo->id)->count());
    }

    public function test_la_fecha_de_fin_no_puede_venir_antes_de_la_de_inicio(): void
    {
        $equipo = $this->crearEquipo();

        $this->post($this->ruta(), [
            'equipo_id' => $equipo->id,
            'fecha_inicio' => '2026-10-05 08:00',
            'fecha_fin' => '2026-10-01 08:00',
        ])->assertSessionHasErrors('fecha_fin');
    }

    public function test_no_se_puede_asignar_un_equipo_inactivo(): void
    {
        $equipo = $this->crearEquipo(['estado' => 0]);

        $this->post($this->ruta(), [
            'equipo_id' => $equipo->id,
            'fecha_inicio' => '2026-10-01 08:00',
        ])->assertSessionHasErrors('equipo_id');
    }

    // ==================================================================
    // El proceso sale de la ruta, no del formulario
    // ==================================================================

    public function test_el_proceso_se_toma_de_la_ruta_y_no_del_formulario(): void
    {
        $equipo = $this->crearEquipo();

        $this->post($this->ruta('', $this->proceso), [
            'proceso_orden_id' => $this->otroProceso->id,
            'equipo_id' => $equipo->id,
            'fecha_inicio' => '2026-10-01 08:00',
        ])->assertRedirect();

        $uso = ProcesoEquipo::where('equipo_id', $equipo->id)->firstOrFail();

        $this->assertEquals(
            $this->proceso->id,
            $uso->proceso_orden_id,
            'El proceso debe tomarse de la ruta, no del formulario'
        );
    }

    public function test_no_se_puede_usar_un_proceso_de_otra_orden(): void
    {
        $otra = $this->orden->replicate();
        $otra->codigo = 'OT-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $otra->save();

        $etapa = DB::table('etapas')->first();

        $ajeno = new ProcesosOrden();
        $ajeno->orden_trabajo_id = $otra->id;
        $ajeno->etapa_id = $etapa->id;
        $ajeno->codigo = 'P-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $ajeno->fecha_inicio = '2026-01-01 08:00:00';
        $ajeno->peso_entrada = 1;
        $ajeno->peso_salida = 1;
        $ajeno->estado = 1;
        $ajeno->save();

        $equipo = $this->crearEquipo();

        // El proceso es de otra orden: escribarlo en la url de esta no
        // puede colar el equipo en la orden que no es
        $this->post(
            "/procesos/ordenes-trabajo/{$this->orden->id}/procesos/{$ajeno->id}/equipos",
            [
                'equipo_id' => $equipo->id,
                'fecha_inicio' => '2026-10-01 08:00',
            ]
        )->assertNotFound();

        $this->assertSame(0, ProcesoEquipo::where('equipo_id', $equipo->id)->count());
    }

    public function test_no_se_puede_tocar_un_uso_de_otro_proceso(): void
    {
        $equipo = $this->crearEquipo();

        $this->post($this->ruta('', $this->proceso), [
            'equipo_id' => $equipo->id,
            'fecha_inicio' => '2026-10-01 08:00',
        ])->assertRedirect();

        $uso = ProcesoEquipo::where('equipo_id', $equipo->id)->firstOrFail();

        // El uso es del proceso, pero se pide desde el otro
        $this->get($this->ruta("/{$uso->id}", $this->otroProceso))->assertNotFound();
        $this->delete($this->ruta("/{$uso->id}", $this->otroProceso))->assertNotFound();
    }

    // ==================================================================
    // El costo del proceso
    // ==================================================================

    public function test_la_depreciacion_entra_al_costo_del_proceso(): void
    {
        $equipo = $this->crearEquipo();

        $this->post($this->ruta(), [
            'equipo_id' => $equipo->id,
            'fecha_inicio' => '2026-10-01 08:00',
            'fecha_fin' => '2026-10-05 08:00',
        ])->assertRedirect();

        $proceso = $this->proceso->fresh();

        $this->assertEqualsWithDelta(111.11, $proceso->costo_equipos, 0.02);
        $this->assertEqualsWithDelta(111.11, $proceso->costo_total, 0.02);

        // Y el otro proceso no arrastra el costo
        $this->assertEquals(0.0, $this->otroProceso->fresh()->costo_equipos);
    }

    public function test_quitar_el_equipo_baja_el_costo_del_proceso(): void
    {
        $equipo = $this->crearEquipo();

        $this->post($this->ruta(), [
            'equipo_id' => $equipo->id,
            'fecha_inicio' => '2026-10-01 08:00',
            'fecha_fin' => '2026-10-05 08:00',
        ])->assertRedirect();

        $uso = ProcesoEquipo::where('equipo_id', $equipo->id)->firstOrFail();

        $this->delete($this->ruta("/{$uso->id}"))->assertRedirect();

        $this->assertEquals(0.0, $this->proceso->fresh()->costo_equipos);
    }

    public function test_cambiar_el_equipo_rehace_la_depreciacion(): void
    {
        $caro = $this->crearEquipo([
            'valor_adquisicion' => 120000,
            'valor_residual' => 0,
            'vida_util_meses' => 120,
        ]);
        $barato = $this->crearEquipo([
            'valor_adquisicion' => 12000,
            'valor_residual' => 0,
            'vida_util_meses' => 120,
        ]);

        $this->post($this->ruta(), [
            'equipo_id' => $caro->id,
            'fecha_inicio' => '2026-10-01 08:00',
            'fecha_fin' => '2026-10-05 08:00',
        ])->assertRedirect();

        $uso = ProcesoEquipo::where('equipo_id', $caro->id)->firstOrFail();

        $this->assertEqualsWithDelta(133.33, (float) $uso->depreciacion_total, 0.02);

        // 12000 / (120 * 30) = 3.3333 por dia, 4 dias
        $this->put($this->ruta("/{$uso->id}"), [
            'equipo_id' => $barato->id,
            'fecha_inicio' => '2026-10-01 08:00',
            'fecha_fin' => '2026-10-05 08:00',
        ])->assertRedirect();

        $this->assertEqualsWithDelta(
            13.33,
            (float) $uso->fresh()->depreciacion_total,
            0.02,
            'Al cambiar de equipo la foto de la depreciacion debe rehacerse'
        );
    }

    public function test_tocar_solo_la_observacion_no_remezcla_la_depreciacion(): void
    {
        $equipo = $this->crearEquipo();

        $this->post($this->ruta(), [
            'equipo_id' => $equipo->id,
            'fecha_inicio' => '2026-10-01 08:00',
            'fecha_fin' => '2026-10-05 08:00',
        ])->assertRedirect();

        $uso = ProcesoEquipo::where('equipo_id', $equipo->id)->firstOrFail();
        $antes = (float) $uso->depreciacion_total;

        // Entre tanto se toca el equipo: si se remezclara, la cifra moveria
        $equipo->update(['vida_util_meses' => 240]);
        $equipo->refresh();

        $this->put($this->ruta("/{$uso->id}"), [
            'equipo_id' => $equipo->id,
            'fecha_inicio' => '2026-10-01 08:00',
            'fecha_fin' => '2026-10-05 08:00',
            'observaciones' => 'Solo una nota',
        ])->assertRedirect();

        $this->assertEqualsWithDelta(
            $antes,
            (float) $uso->fresh()->depreciacion_total,
            0.001,
            'Sin cambio de fechas no se debe rehacer la foto'
        );

        $this->assertEquals('Solo una nota', $uso->fresh()->observaciones);
    }

    // ==================================================================
    // La pantalla del proceso
    // ==================================================================

    public function test_la_pantalla_del_proceso_muestra_los_equipos_y_la_depreciacion(): void
    {
        $equipo = $this->crearEquipo();

        $this->post($this->ruta(), [
            'equipo_id' => $equipo->id,
            'fecha_inicio' => '2026-10-01 08:00',
            'fecha_fin' => '2026-10-05 08:00',
        ])->assertRedirect();

        $html = $this->get(
            "/procesos/ordenes-trabajo/{$this->orden->id}/procesos/{$this->proceso->id}"
        )->assertOk()->getContent();

        $this->assertStringContainsString('Equipos del proceso', $html);
        $this->assertStringContainsString('Depreciación de equipos', $html);
        $this->assertStringContainsString('Costo total del proceso', $html);
        $this->assertStringContainsString($equipo->codigo, $html);
        $this->assertStringContainsString('111.11', $html);
    }

    public function test_el_modal_ofrece_los_equipos_activos(): void
    {
        $activo = $this->crearEquipo();
        $inactivo = $this->crearEquipo(['estado' => 0]);

        $html = $this->get(
            "/procesos/ordenes-trabajo/{$this->orden->id}/procesos/{$this->proceso->id}"
        )->assertOk()->getContent();

        $this->assertStringContainsString($activo->codigo, $html);
        $this->assertStringNotContainsString($inactivo->codigo, $html);
    }

    public function test_el_modal_advierte_que_no_se_puede_dos_procesos_a_la_vez(): void
    {
        $html = $this->get(
            "/procesos/ordenes-trabajo/{$this->orden->id}/procesos/{$this->proceso->id}"
        )->assertOk()->getContent();

        $this->assertStringContainsString('nunca en el mismo instante', $html);
    }

    public function test_la_previsualizacion_devuelve_la_depreciacion_del_periodo(): void
    {
        $equipo = $this->crearEquipo();

        $r = $this->getJson($this->ruta('/depreciacion') . '?' . http_build_query([
            'equipo_id' => $equipo->id,
            'fecha_inicio' => '2026-10-01 08:00',
            'fecha_fin' => '2026-10-05 08:00',
        ]))->assertOk();

        $this->assertEqualsWithDelta(4.0, (float) $r->json('dias'), 0.01);
        $this->assertEqualsWithDelta(27.7778, (float) $r->json('depreciacion_diaria'), 0.01);
        $this->assertEqualsWithDelta(111.11, (float) $r->json('depreciacion_total'), 0.02);
        $this->assertTrue($r->json('disponible'));
        $this->assertNull($r->json('solapamiento'));
    }

    public function test_la_previsualizacion_avisa_del_solapamiento(): void
    {
        $equipo = $this->crearEquipo();

        $this->post($this->ruta('', $this->otroProceso), [
            'equipo_id' => $equipo->id,
            'fecha_inicio' => '2026-10-01 08:00',
            'fecha_fin' => '2026-10-05 08:00',
        ])->assertRedirect();

        $r = $this->getJson($this->ruta('/depreciacion') . '?' . http_build_query([
            'equipo_id' => $equipo->id,
            'fecha_inicio' => '2026-10-03 08:00',
            'fecha_fin' => '2026-10-07 08:00',
        ]))->assertOk();

        $this->assertNotNull(
            $r->json('solapamiento'),
            'La previsualizacion debe avisar del conflicto antes de guardar'
        );
    }

    // ==================================================================
    // El equipo es dato maestro
    // ==================================================================

    public function test_se_puede_crear_un_equipo_desde_la_pantalla(): void
    {
        $codigo = 'MAQ-' . strtoupper(substr(md5(uniqid('', true)), 0, 5));

        $this->post('/admin/equipos', [
            'codigo' => $codigo,
            'nombre' => 'Molino de bolas',
            'descripcion' => 'Molino de prueba',
            'valor_adquisicion' => 500000,
            'valor_residual' => 50000,
            'vida_util_meses' => 120,
            'estado' => 1,
        ])->assertRedirect('/admin/equipos');

        $equipo = Equipo::where('codigo', $codigo)->firstOrFail();

        $this->assertEquals('Molino de bolas', $equipo->nombre);
        $this->assertEquals(120, $equipo->vida_util_meses);

        // 450000 repartidos en 120 meses de 30 dias
        $this->assertEqualsWithDelta(125.0, $equipo->depreciacionDiaria(), 0.01);
    }

    public function test_el_codigo_del_equipo_no_se_repite(): void
    {
        $codigo = 'MAQ-' . strtoupper(substr(md5(uniqid('', true)), 0, 5));

        $this->crearEquipo(['codigo' => $codigo]);

        $this->post('/admin/equipos', [
            'codigo' => $codigo,
            'nombre' => 'Otro equipo',
            'valor_adquisicion' => 1000,
            'valor_residual' => 0,
            'vida_util_meses' => 12,
            'estado' => 1,
        ])->assertSessionHasErrors('codigo');
    }

    public function test_el_valor_residual_no_puede_pasar_el_de_adquisicion(): void
    {
        $this->post('/admin/equipos', [
            'codigo' => 'MAQ-X1',
            'nombre' => 'Equipo imposible',
            'valor_adquisicion' => 1000,
            'valor_residual' => 5000,
            'vida_util_meses' => 12,
            'estado' => 1,
        ])->assertSessionHasErrors('valor_residual');
    }

    public function test_la_vida_util_no_puede_ser_cero_meses(): void
    {
        $this->post('/admin/equipos', [
            'codigo' => 'MAQ-X2',
            'nombre' => 'Equipo sin vida util',
            'valor_adquisicion' => 1000,
            'valor_residual' => 0,
            'vida_util_meses' => 0,
            'estado' => 1,
        ])->assertSessionHasErrors('vida_util_meses');
    }

    public function test_un_equipo_sin_historial_se_borra(): void
    {
        $equipo = $this->crearEquipo();

        $this->delete("/admin/equipos/{$equipo->id}")->assertRedirect('/admin/equipos');

        $this->assertSoftDeleted('equipos', ['id' => $equipo->id]);
    }

    public function test_un_equipo_con_historial_no_se_borra(): void
    {
        $equipo = $this->crearEquipo();

        $this->post($this->ruta(), [
            'equipo_id' => $equipo->id,
            'fecha_inicio' => '2026-10-01 08:00',
            'fecha_fin' => '2026-10-05 08:00',
        ])->assertRedirect();

        $this->delete("/admin/equipos/{$equipo->id}")
            ->assertRedirect("/admin/equipos/{$equipo->id}");

        $this->assertNotSoftDeleted('equipos', ['id' => $equipo->id]);
    }

    public function test_las_pantallas_de_equipo_abren(): void
    {
        $equipo = $this->crearEquipo();

        $this->get('/admin/equipos')->assertOk();
        $this->get('/admin/equipos/create')->assertOk();
        $this->get("/admin/equipos/{$equipo->id}")->assertOk();
        $this->get("/admin/equipos/{$equipo->id}/edit")->assertOk();
    }

    public function test_los_equipos_aparecen_en_el_menu(): void
    {
        $html = $this->get('/admin/equipos')->assertOk()->getContent();

        $this->assertStringContainsString(route('admin.equipos.index'), $html);
        $this->assertStringContainsString('Equipos', $html);
    }

    // ==================================================================
    // Permisos
    // ==================================================================

    public function test_hacen_falta_permisos_para_asignar_equipos(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->usuarioConPermisos(['proceso_equipo.view']));

        $equipo = $this->crearEquipo();

        // Puede ver, pero no registrar
        $this->post($this->ruta(), [
            'equipo_id' => $equipo->id,
            'fecha_inicio' => '2026-10-01 08:00',
        ])->assertForbidden();

        $this->assertSame(0, ProcesoEquipo::where('equipo_id', $equipo->id)->count());
    }

    public function test_hacen_falta_permisos_para_crear_equipos(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->usuarioConPermisos(['equipos.view']));

        $this->post('/admin/equipos', [
            'codigo' => 'MAQ-Z9',
            'nombre' => 'No permitido',
            'valor_adquisicion' => 1000,
            'valor_residual' => 0,
            'vida_util_meses' => 12,
            'estado' => 1,
        ])->assertForbidden();
    }

    public function test_operador_puede_asignar_equipos_pero_no_crearlos(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->usuarioConPermisos([
            'proceso_equipo.view', 'proceso_equipo.create', 'proceso_equipo.edit',
            'equipos.view',
        ]));

        $equipo = $this->crearEquipo();

        $this->post($this->ruta(), [
            'equipo_id' => $equipo->id,
            'fecha_inicio' => '2026-10-01 08:00',
        ])->assertRedirect();

        $this->assertSame(1, ProcesoEquipo::where('equipo_id', $equipo->id)->count());

        // El maestro no: es de administracion
        $this->post('/admin/equipos', [
            'codigo' => 'MAQ-Z8',
            'nombre' => 'No permitido',
            'valor_adquisicion' => 1000,
            'valor_residual' => 0,
            'vida_util_meses' => 12,
            'estado' => 1,
        ])->assertForbidden();
    }

    /**
     * Usuario con exactamente los permisos indicados, en un rol propio
     * para no tocar los roles reales de la aplicacion.
     */
    private function usuarioConPermisos(array $nombres): User
    {
        $nombre = 'rol-test-' . substr(md5(implode(',', $nombres)), 0, 8);

        $rol = Role::firstOrCreate([
            'name' => $nombre,
            'guard_name' => 'web',
        ]);

        $rol->syncPermissions($nombres);

        $usuario = User::create([
            'name' => 'Usuario de prueba',
            'email' => 'eqp-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);

        $usuario->assignRole($nombre);

        return $usuario;
    }
}
