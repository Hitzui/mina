<?php

namespace Tests\Feature;

use App\Models\Etapa;
use App\Models\OrdenesTrabajo;
use App\Models\ProcesosOrden;
use App\Models\Produccione;
use App\Models\TiposProduccion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProduccionTest extends TestCase
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
            'name' => 'Admin producción',
            'email' => 'produccion-'.uniqid().'@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        foreach (['view', 'create', 'edit', 'delete'] as $action) {
            $admin->givePermissionTo(Permission::findOrCreate("producciones.{$action}", 'web'));
        }
        $this->actingAs($admin);
    }

    public function test_se_muestra_la_pantalla_y_la_tabla_ajax(): void
    {
        $this->get('/procesos/producciones')
            ->assertOk()
            ->assertSee('Producciones')
            ->assertSee('name="unidad_medida_id"', false)
            ->assertSee('toneladas');

        $this->getJson('/procesos/producciones?draw=1&start=0&length=10', self::CABECERAS)
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    public function test_el_operador_puede_registrar_produccion(): void
    {
        $operador = User::create([
            'name' => 'Operador producción',
            'email' => 'operador-produccion-'.uniqid().'@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $operador->syncRoles(['operador']);
        $this->actingAs($operador);

        $this->get('/procesos/producciones')
            ->assertOk()
            ->assertSee('id="btnNuevaProduccion"', false);
    }

    public function test_registra_y_edita_una_produccion(): void
    {
        $orden = $this->ordenAbierta();
        $tipo = $this->tipoActivo();

        $respuesta = $this->postJson('/procesos/producciones', [
            'orden_trabajo_id' => $orden->id,
            'proceso_orden_id' => '',
            'tipo_produccion_id' => $tipo->id,
            'fecha' => '2026-10-10',
            'descripcion' => 'Producción de prueba',
            'cantidad' => '12.3456',
            'unidad_medida' => 'kg',
            'observaciones' => 'Observación de prueba',
        ], self::CABECERAS)->assertOk();

        $id = $respuesta->json('id');
        $produccion = Produccione::findOrFail($id);
        $this->assertSame((int) $orden->id, (int) $produccion->orden_trabajo_id);
        $this->assertSame((int) DB::table('unidades_medida')->where('nombre', 'kg')->value('id'), (int) $produccion->unidad_medida_id);
        $this->assertSame('12.3456', $produccion->getRawOriginal(Produccione::CANTIDAD));

        $this->putJson("/procesos/producciones/{$id}", [
            'orden_trabajo_id' => $orden->id,
            'proceso_orden_id' => '',
            'tipo_produccion_id' => $tipo->id,
            'fecha' => '2026-10-11',
            'descripcion' => 'Producción corregida',
            'cantidad' => '15.0000',
            'unidad_medida' => 'kg',
            'observaciones' => '',
        ], self::CABECERAS)->assertOk();

        $this->assertSame('Producción corregida', $produccion->fresh()->descripcion);
        $this->assertNull($produccion->fresh()->observaciones);
    }

    public function test_no_acepta_un_proceso_que_pertenece_a_otra_orden(): void
    {
        $orden = $this->ordenAbierta();
        $proceso = ProcesosOrden::query()
            ->where(ProcesosOrden::ORDEN_TRABAJO_ID, '!=', $orden->id)
            ->first();

        if (! $proceso) {
            $otraOrden = OrdenesTrabajo::query()
                ->where(OrdenesTrabajo::ID, '!=', $orden->id)
                ->first();
            $etapa = Etapa::query()->first();

            if (! $otraOrden || ! $etapa) {
                $this->markTestSkipped('No hay otra orden y etapa para verificar la pertenencia del proceso.');
            }

            $proceso = ProcesosOrden::create([
                ProcesosOrden::ORDEN_TRABAJO_ID => $otraOrden->id,
                ProcesosOrden::ETAPA_ID => $etapa->id,
                ProcesosOrden::CODIGO => 'TST-'.strtoupper(substr(uniqid(), -8)),
            ]);
        }

        $this->postJson('/procesos/producciones', [
            'orden_trabajo_id' => $orden->id,
            'proceso_orden_id' => $proceso->id,
            'tipo_produccion_id' => $this->tipoActivo()->id,
            'fecha' => '2026-10-10',
            'cantidad' => '1',
            'unidad_medida' => 'kg',
        ], self::CABECERAS)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('proceso_orden_id');
    }

    public function test_la_unidad_de_medida_debe_elegirse_de_las_unidades_disponibles(): void
    {
        $this->postJson('/procesos/producciones', [
            'orden_trabajo_id' => $this->ordenAbierta()->id,
            'proceso_orden_id' => '',
            'tipo_produccion_id' => $this->tipoActivo()->id,
            'fecha' => '2026-10-10',
            'cantidad' => '1',
            'unidad_medida' => 'unidad inventada',
        ], self::CABECERAS)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('unidad_medida_id');
    }

    public function test_los_procesos_del_selector_pertenecen_a_la_orden_consultada(): void
    {
        $orden = $this->ordenAbierta();

        $procesos = $this->getJson('/procesos/producciones/procesos?orden_trabajo_id='.$orden->id, self::CABECERAS)
            ->assertOk()
            ->json();

        foreach ($procesos as $proceso) {
            $this->assertSame(
                (int) $orden->id,
                (int) DB::table('procesos_orden')->where('id', $proceso['id'])->value('orden_trabajo_id')
            );
        }
    }

    private function ordenAbierta(): OrdenesTrabajo
    {
        $orden = OrdenesTrabajo::query()
            ->whereNotIn(OrdenesTrabajo::ESTADO, OrdenesTrabajo::ESTADOS_CERRADOS)
            ->orderBy(OrdenesTrabajo::ID)
            ->first();

        if (! $orden) {
            $this->markTestSkipped('No hay una orden abierta en la base para probar producciones.');
        }

        return $orden;
    }

    private function tipoActivo(): TiposProduccion
    {
        return TiposProduccion::create([
            'nombre' => 'Tipo de prueba '.uniqid(),
            'estado' => true,
        ]);
    }
}
