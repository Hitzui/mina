<?php

namespace Tests\Feature;

use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UnidadMedidaTest extends TestCase
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
            'name' => 'Admin unidades',
            'email' => 'unidades-'.uniqid().'@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        foreach (['view', 'create', 'edit', 'delete'] as $action) {
            $admin->givePermissionTo(Permission::findOrCreate("configuracion.unidades_medida.{$action}", 'web'));
        }
        $this->actingAs($admin);
    }

    public function test_catalogo_se_puede_consultar_y_mantener(): void
    {
        $this->get('/configuracion/unidades-medida')->assertOk()->assertSee('Unidades de medida');
        $this->getJson('/configuracion/unidades-medida?draw=1&start=0&length=10', self::CABECERAS)
            ->assertOk()->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $nombre = 'prueba-'.uniqid();
        $id = $this->postJson('/configuracion/unidades-medida', [
            'nombre' => $nombre,
            'estado' => '1',
        ], self::CABECERAS)->assertOk()->json('id');

        $this->putJson("/configuracion/unidades-medida/{$id}", [
            'nombre' => $nombre,
            'estado' => '0',
        ], self::CABECERAS)->assertOk();

        $this->assertFalse(UnidadMedida::findOrFail($id)->estado);
        $this->deleteJson("/configuracion/unidades-medida/{$id}", [], self::CABECERAS)->assertOk();
        $this->assertDatabaseMissing('unidades_medida', ['id' => $id]);
    }

    public function test_no_se_puede_eliminar_ni_renombrar_una_unidad_en_uso(): void
    {
        $unidad = UnidadMedida::where('nombre', 'kg')->firstOrFail();
        if (! \DB::table('productos')->where('unidad_medida_id', $unidad->id)->exists()) {
            $this->markTestSkipped('No hay materiales con la unidad kg para comprobar protección de uso.');
        }

        $this->deleteJson("/configuracion/unidades-medida/{$unidad->id}", [], self::CABECERAS)
            ->assertUnprocessable()->assertJsonValidationErrors('nombre');
        $this->putJson("/configuracion/unidades-medida/{$unidad->id}", [
            'nombre' => $unidad->nombre,
            'estado' => '0',
        ], self::CABECERAS)->assertUnprocessable()->assertJsonValidationErrors('estado');
        $this->putJson("/configuracion/unidades-medida/{$unidad->id}", [
            'nombre' => 'kilogramos-'.uniqid(),
            'estado' => '1',
        ], self::CABECERAS)->assertUnprocessable()->assertJsonValidationErrors('nombre');
    }
}
