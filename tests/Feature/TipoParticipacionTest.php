<?php

namespace Tests\Feature;

use App\Models\TiposParticipacion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TipoParticipacionTest extends TestCase
{
    use DatabaseTransactions;

    private const CABECERAS = [
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::create([
            'name' => 'Admin participación',
            'email' => 'tipo-participacion-'.uniqid().'@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        foreach (['view', 'create', 'edit', 'delete'] as $accion) {
            $admin->givePermissionTo(Permission::findOrCreate(
                "configuracion.tipos_participacion.{$accion}",
                'web'
            ));
        }
        $this->actingAs($admin);
    }

    public function test_se_ve_el_catalogo_y_su_tabla_ajax(): void
    {
        $this->get('/configuracion/tipos-participacion')
            ->assertOk()
            ->assertSee('Tipos de participación');

        $this->getJson('/configuracion/tipos-participacion?draw=1&start=0&length=10', self::CABECERAS)
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    public function test_se_puede_crear_editar_activar_y_eliminar_un_tipo_sin_uso(): void
    {
        $nombre = 'Participación de prueba '.uniqid();
        $id = $this->postJson('/configuracion/tipos-participacion', [
            'nombre' => $nombre,
            'descripcion' => 'Modalidad temporal',
            'estado' => '1',
        ], self::CABECERAS)->assertOk()->json('id');

        $tipo = TiposParticipacion::findOrFail($id);
        $this->assertSame([], $tipo->usos());

        $this->putJson("/configuracion/tipos-participacion/{$id}", [
            'nombre' => $nombre,
            'descripcion' => 'Descripción editada',
            'estado' => '1',
        ], self::CABECERAS)->assertOk();

        $this->postJson("/configuracion/tipos-participacion/{$id}/cambiar-estado", [], self::CABECERAS)
            ->assertOk();
        $this->assertFalse($tipo->fresh()->estado);

        $this->deleteJson("/configuracion/tipos-participacion/{$id}", [], self::CABECERAS)->assertOk();
        $this->assertSoftDeleted('tipos_participacion', ['id' => $id]);
    }

    public function test_un_tipo_usado_no_se_borra_incluso_si_el_acuerdo_esta_dado_de_baja(): void
    {
        $ordenId = DB::table('ordenes_trabajo')->value('id');
        $monedaId = DB::table('monedas')->value('id');
        if (! $ordenId || ! $monedaId) {
            $this->markTestSkipped('Hace falta una orden y una moneda para probar la referencia al tipo.');
        }

        $tipo = TiposParticipacion::create([
            'nombre' => 'Participación usada '.uniqid(),
            'estado' => true,
        ]);
        DB::table('acuerdos_orden')->insert([
            'orden_trabajo_id' => $ordenId,
            'tipo_participacion_id' => $tipo->id,
            'moneda_id' => $monedaId,
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => now(),
        ]);

        $this->assertSame(1, $tipo->usos()[0]['cuantas']);
        $this->assertSame(1, $tipo->usos()[0]['dadasDeBaja']);
        $this->deleteJson("/configuracion/tipos-participacion/{$tipo->id}", [], self::CABECERAS)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nombre');
    }
}
