<?php

namespace Tests\Feature;

use App\Models\OrdenesTrabajo;
use App\Models\ProcesosOrden;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * El estado del proceso.
 *
 * Venia de dos sitios que se contradecian: la ficha lo leia como booleano y
 * decia "Activo" o "Inactivo", y el listado lo leia con su propia copia del
 * catalogo y decia "Pendiente", "En proceso", "Finalizado" o "Cancelado".
 * Para el mismo proceso. Y ninguno de los dos se podia cambiar: la columna
 * se ponia en 1 al crear y no habia ningun sitio donde moverla, asi que un
 * proceso que empezo y termino hace dias seguia diciendo "Pendiente".
 *
 * Ahora el estado se deduce de las fechas del proceso, que es lo que de
 * verdad se teclea, y lo unico que se marca a mano es la cancelacion: un
 * proceso abandonado tiene las mismas fechas que uno terminado a tiempo y no
 * hay forma de deducirlo.
 */
class EstadoProcesoTest extends TestCase
{
    use DatabaseTransactions;

    private OrdenesTrabajo $orden;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'estado-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        $this->actingAs($admin);

        $this->orden = OrdenesTrabajo::firstOrFail();
    }

    /**
     * Un proceso con las fechas que se le pidan.
     */
    private function proceso(?string $inicio, ?string $fin, int $estado = ProcesosOrden::ESTADO_PENDIENTE): ProcesosOrden
    {
        $etapa = DB::table('etapas')->first();

        $proceso = new ProcesosOrden();
        $proceso->orden_trabajo_id = $this->orden->id;
        $proceso->etapa_id = $etapa->id;
        $proceso->codigo = 'P-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $proceso->fecha_inicio = $inicio;
        $proceso->fecha_fin = $fin;
        $proceso->peso_entrada = 1;
        $proceso->peso_salida = 1;
        $proceso->estado = $estado;
        $proceso->save();

        return $proceso;
    }

    // ==================================================================
    // Los cuatro estados, con la misma numeracion que la orden
    // ==================================================================

    public function test_el_proceso_tiene_los_cuatro_estados_de_la_orden(): void
    {
        $this->assertCount(4, ProcesosOrden::ESTADOS);

        // La misma numeracion que la orden: es la misma cosa
        $this->assertSame(0, ProcesosOrden::ESTADO_CANCELADO);
        $this->assertSame(1, ProcesosOrden::ESTADO_PENDIENTE);
        $this->assertSame(2, ProcesosOrden::ESTADO_EN_PROCESO);
        $this->assertSame(3, ProcesosOrden::ESTADO_FINALIZADO);
    }

    public function test_las_palabras_van_en_masculino_porque_el_sustantivo_es_el_proceso(): void
    {
        $esperados = [
            0 => 'Cancelado',
            1 => 'Pendiente',
            2 => 'En proceso',
            3 => 'Finalizado',
        ];

        foreach ($esperados as $numero => $texto) {

            $this->assertSame(
                $texto,
                ProcesosOrden::ESTADOS[$numero]['texto'],
                "El estado $numero"
            );
        }
    }

    // ==================================================================
    // El estado sale de las fechas
    // ==================================================================

    public function test_sin_fecha_de_inicio_el_proceso_esta_pendiente(): void
    {
        $proceso = $this->proceso(null, null);

        $this->assertSame(ProcesosOrden::ESTADO_PENDIENTE, $proceso->estadoCalculado());
        $this->assertSame('Pendiente', $proceso->estadoTexto());
    }

    public function test_con_fecha_de_inicio_y_sin_fecha_de_fin_esta_en_proceso(): void
    {
        $proceso = $this->proceso(now()->subHours(3)->toDateTimeString(), null);

        $this->assertSame(ProcesosOrden::ESTADO_EN_PROCESO, $proceso->estadoCalculado());
        $this->assertSame('En proceso', $proceso->estadoTexto());
    }

    public function test_con_la_fecha_de_fin_ya_pasada_esta_finalizado(): void
    {
        $proceso = $this->proceso(
            now()->subDays(6)->toDateTimeString(),
            now()->subDays(6)->addHours(9)->toDateTimeString()
        );

        $this->assertSame(ProcesosOrden::ESTADO_FINALIZADO, $proceso->estadoCalculado());
        $this->assertSame('Finalizado', $proceso->estadoTexto());
    }

    public function test_con_la_fecha_de_fin_por_delante_sigue_en_proceso(): void
    {
        /*
         * Este es el caso que hacia falta cuidar. Un proceso planificado para
         * el dia 30 sigue siendo "En proceso" el dia 27, no "Finalizado" por
         * tener la fecha puesta. Marcarlo terminado antes de tiempo seria
         * volver a mentir, que es justo lo que se vino a arreglar.
         */
        $proceso = $this->proceso(
            now()->subDay()->toDateTimeString(),
            now()->addDays(3)->toDateTimeString()
        );

        $this->assertSame(
            ProcesosOrden::ESTADO_EN_PROCESO,
            $proceso->estadoCalculado(),
            'Con la fecha de fin por delante, el proceso todavía no terminó'
        );
    }

    public function test_el_estado_cambia_solo_cuando_cambian_las_fechas(): void
    {
        $proceso = $this->proceso(now()->subDay()->toDateTimeString(), null);

        $this->assertSame('En proceso', $proceso->estadoTexto());

        // Se le pone una fecha de fin que ya paso
        $proceso->fecha_fin = now()->subHours(1);
        $proceso->save();

        $this->assertSame(
            'Finalizado',
            $proceso->fresh()->estadoTexto(),
            'No hay que tocar el estado a mano: cambia con las fechas'
        );
    }

    public function test_un_proceso_terminado_no_dice_pendiente(): void
    {
        $proceso = $this->proceso(
            now()->subDays(6)->toDateTimeString(),
            now()->subDays(6)->addHours(9)->toDateTimeString()
        );

        $this->assertStringNotContainsString(
            'Pendiente',
            $proceso->estadoEtiqueta(),
            'Un proceso con fecha de fin pasada no puede decir Pendiente'
        );

        $this->assertStringContainsString('Finalizado', $proceso->estadoEtiqueta());
    }

    // ==================================================================
    // Cancelado, que es la unica marca manual
    // ==================================================================

    public function test_cancelado_manda_sobre_las_fechas(): void
    {
        $proceso = $this->proceso(
            now()->subDays(6)->toDateTimeString(),
            now()->subDays(6)->addHours(9)->toDateTimeString(),
            ProcesosOrden::ESTADO_CANCELADO
        );

        $this->assertTrue($proceso->estaCancelado());
        $this->assertSame('Cancelado', $proceso->estadoTexto());
    }

    public function test_un_proceso_cancelado_esta_cerrado(): void
    {
        $proceso = $this->proceso(null, null, ProcesosOrden::ESTADO_CANCELADO);

        $this->assertTrue($proceso->estaCerrado());
    }

    public function test_un_proceso_finalizado_esta_cerrado_pero_uno_en_proceso_no(): void
    {
        $finalizado = $this->proceso(
            now()->subDays(6)->toDateTimeString(),
            now()->subDays(6)->addHours(9)->toDateTimeString()
        );
        $enProceso = $this->proceso(now()->subDay()->toDateTimeString(), null);

        $this->assertTrue($finalizado->estaCerrado());
        $this->assertFalse($enProceso->estaCerrado());
    }

    // ==================================================================
    // Guardar la marca desde el formulario
    // ==================================================================

    public function test_la_casilla_de_cancelado_se_guarda(): void
    {
        $etapa = DB::table('etapas')->first();

        $this->post("/procesos/ordenes-trabajo/{$this->orden->id}/procesos", [
            'etapa_id' => $etapa->id,
            'fecha_inicio' => now()->subDay()->toDateTimeString(),
            'cancelado' => '1',
        ])->assertRedirect();

        $proceso = ProcesosOrden::where('orden_trabajo_id', $this->orden->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertTrue($proceso->estaCancelado());
        $this->assertSame('Cancelado', $proceso->fresh()->estadoTexto());
    }

    public function test_sin_marcar_el_cancelado_el_proceso_no_queda_cancelado(): void
    {
        $etapa = DB::table('etapas')->first();

        $this->post("/procesos/ordenes-trabajo/{$this->orden->id}/procesos", [
            'etapa_id' => $etapa->id,
            'fecha_inicio' => now()->subDay()->toDateTimeString(),
            'cancelado' => '0',
        ])->assertRedirect();

        $proceso = ProcesosOrden::where('orden_trabajo_id', $this->orden->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertFalse($proceso->estaCancelado());
    }

    public function test_la_casilla_oculta_manda_uno_por_defecto_al_no_venir(): void
    {
        $etapa = DB::table('etapas')->first();

        // Sin mandar la casilla: el oculto deberia traer 0
        $this->post("/procesos/ordenes-trabajo/{$this->orden->id}/procesos", [
            'etapa_id' => $etapa->id,
        ])->assertRedirect();

        $proceso = ProcesosOrden::where('orden_trabajo_id', $this->orden->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertFalse(
            $proceso->estaCancelado(),
            'Sin marcar la casilla, el proceso no debe quedar cancelado'
        );
    }

    public function test_se_puede_quitar_la_marca_de_cancelado_al_editar(): void
    {
        $proceso = $this->proceso(null, null, ProcesosOrden::ESTADO_CANCELADO);

        $this->put("/procesos/ordenes-trabajo/{$this->orden->id}/procesos/{$proceso->id}", [
            'etapa_id' => $proceso->etapa_id,
            'fecha_inicio' => now()->subDay()->toDateTimeString(),
            'cancelado' => '0',
        ])->assertRedirect();

        $proceso->refresh();

        $this->assertFalse($proceso->estaCancelado());
        $this->assertSame('En proceso', $proceso->estadoTexto());
    }

    // ==================================================================
    // La pantalla ya no dice Activo ni Inactivo
    // ==================================================================

    public function test_la_ficha_no_dice_activo_ni_inactivo(): void
    {
        $ficha = file_get_contents(
            resource_path('views/procesos/procesos_orden/show.blade.php')
        );

        $this->assertStringNotContainsString(
            '> Activo',
            $ficha,
            'La ficha no debe tratar el estado como si fuera un booleano'
        );

        $this->assertStringNotContainsString('> Inactivo', $ficha);

        $this->assertStringContainsString('estadoEtiqueta', $ficha);
    }

    public function test_el_listado_usa_el_modelo_y_no_su_propia_copia(): void
    {
        $listado = file_get_contents(app_path('DataTables/ProcesosOrdenDataTable.php'));

        $this->assertStringContainsString('estadoEtiqueta', $listado);

        // La copia propia del catalogo, con la Cancelada en el 4, no debe
        // volver: en la orden el 0 es la Cancelada
        $this->assertStringNotContainsString(
            '4 => ',
            $listado,
            'El listado no debe traer su propia copia del catálogo de estados'
        );

        $this->assertStringNotContainsString('Desconocido', $listado);
    }

    public function test_el_formulario_no_escribe_el_estado(): void
    {
        $formulario = file_get_contents(
            resource_path('views/procesos/procesos_orden/_form.blade.php')
        );

        // El estado se deduce: no puede ser un campo que se mande
        $this->assertStringNotContainsString('name="estado"', $formulario);
        $this->assertStringNotContainsString('@switch($estado)', $formulario);

        // Lo que si se marca es la cancelacion
        $this->assertStringContainsString('name="cancelado"', $formulario);
    }

    public function test_la_ficha_muestra_el_estado_real(): void
    {
        $proceso = $this->proceso(
            now()->subDays(6)->toDateTimeString(),
            now()->subDays(6)->addHours(9)->toDateTimeString()
        );

        $html = $this->get(
            "/procesos/ordenes-trabajo/{$this->orden->id}/procesos/{$proceso->id}"
        )->assertOk()->getContent();

        $this->assertStringContainsString('Finalizado', $html);
        $this->assertStringNotContainsString('> Activo<', $html);
    }

    // ==================================================================
    // Permisos
    // ==================================================================

    public function test_hacen_falta_permisos_para_editar_un_proceso(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $proceso = $this->proceso(null, null);

        $this->actingAs($this->usuarioSinPermisos());

        $this->put(
            "/procesos/ordenes-trabajo/{$this->orden->id}/procesos/{$proceso->id}",
            ['etapa_id' => $proceso->etapa_id, 'cancelado' => '1']
        )->assertForbidden();

        $this->assertFalse(
            $proceso->fresh()->estaCancelado(),
            'Un proceso no se puede cancelar sin permiso'
        );
    }

    private function usuarioSinPermisos(): User
    {
        $usuario = User::create([
            'name' => 'Sin permisos',
            'email' => 'estado-sin-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);

        $usuario->syncRoles([]);

        return $usuario;
    }
}
