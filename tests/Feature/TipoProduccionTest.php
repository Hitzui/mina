<?php

namespace Tests\Feature;

use App\Models\TiposProduccion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TipoProduccionTest extends TestCase
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
            'name' => 'Admin tipos producción',
            'email' => 'tipo-produccion-'.uniqid().'@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        foreach (['view', 'create', 'edit', 'delete'] as $action) {
            $admin->givePermissionTo(Permission::findOrCreate(
                "configuracion.tipos_produccion.{$action}",
                'web'
            ));
        }
        $this->actingAs($admin);
    }

    public function test_la_pantalla_y_su_tabla_se_cargan(): void
    {
        $this->get('/configuracion/tipos-produccion')
            ->assertOk()
            ->assertSee('Tipos de producción');

        $this->getJson('/configuracion/tipos-produccion?draw=1&start=0&length=10', self::CABECERAS)
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    public function test_el_supervisor_puede_ver_el_catalogo_sin_permisos_de_edicion(): void
    {
        $supervisor = User::create([
            'name' => 'Supervisor tipos producción',
            'email' => 'supervisor-tipo-produccion-'.uniqid().'@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $supervisor->syncRoles(['supervisor']);
        $this->actingAs($supervisor);

        $this->get('/configuracion/tipos-produccion')
            ->assertOk()
            ->assertSee('Tipos de producción')
            ->assertDontSee('id="btnNuevoTipoProduccion"')
            ->assertDontSee('id="btnEditarTipoProduccion"');
    }

    public function test_se_puede_crear_editar_y_desactivar_un_tipo(): void
    {
        $creado = $this->postJson('/configuracion/tipos-produccion', [
            'nombre' => 'Producto de prueba '.uniqid(),
            'descripcion' => 'Descripción inicial',
            'estado' => '1',
        ], self::CABECERAS)->assertOk();

        $id = $creado->json('id');
        $tipo = TiposProduccion::findOrFail($id);

        $this->putJson("/configuracion/tipos-produccion/{$id}", [
            'nombre' => $tipo->nombre,
            'descripcion' => 'Descripción editada',
            'estado' => '1',
        ], self::CABECERAS)->assertOk();

        $this->postJson("/configuracion/tipos-produccion/{$id}/cambiar-estado", [], self::CABECERAS)
            ->assertOk();

        $this->assertFalse($tipo->fresh()->estado);
        $this->assertSame(0, TiposProduccion::paraRegistrar()->whereKey($id)->count());
    }

    public function test_no_se_borra_un_tipo_referenciado_aunque_la_produccion_este_dada_de_baja(): void
    {
        $ordenId = DB::table('ordenes_trabajo')->value('id');
        if (! $ordenId) {
            $this->markTestSkipped('No hay órdenes de trabajo para probar la referencia de producción.');
        }

        $tipo = TiposProduccion::create([
            'nombre' => 'Tipo usado '.uniqid(),
            'estado' => true,
        ]);

        DB::table('producciones')->insert([
            'orden_trabajo_id' => $ordenId,
            'tipo_produccion_id' => $tipo->id,
            'fecha' => '2026-01-01',
            'cantidad' => 1,
            'unidad_medida' => 'unidad',
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => now(),
        ]);

        $this->assertSame(1, $tipo->usos()[0]['cuantas']);
        $this->assertSame(1, $tipo->usos()[0]['dadasDeBaja']);

        $this->deleteJson("/configuracion/tipos-produccion/{$tipo->id}", [], self::CABECERAS)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nombre');

        $this->assertNotNull($tipo->fresh());
    }
}
