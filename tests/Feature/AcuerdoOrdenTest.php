<?php

namespace Tests\Feature;

use App\Models\AcuerdosOrden;
use App\Models\TiposParticipacion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AcuerdoOrdenTest extends TestCase
{
    use DatabaseTransactions;

    private const CABECERAS = [
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ];

    private int $ordenId;

    private int $monedaId;

    private int $tipoId;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $usuario = User::create([
            'name' => 'Admin acuerdos',
            'email' => 'acuerdo-orden-'.uniqid().'@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $usuario->syncRoles(['admin']);
        foreach (['view', 'create', 'edit', 'delete'] as $accion) {
            $usuario->givePermissionTo(Permission::findOrCreate('acuerdos_orden.'.$accion, 'web'));
        }
        $this->actingAs($usuario);

        $this->ordenId = (int) DB::table('ordenes_trabajo')->whereNull('deleted_at')->value('id');
        $this->monedaId = (int) DB::table('monedas')->where('estado', true)->value('id');
        if (! $this->ordenId || ! $this->monedaId) {
            $this->markTestSkipped('Hace falta una orden y una moneda activa para probar acuerdos.');
        }

        $this->tipoId = TiposParticipacion::create([
            'nombre' => 'Participación acuerdo '.uniqid(),
            'estado' => true,
        ])->id;
    }

    public function test_se_muestran_acuerdos_en_la_ficha_y_por_ajax(): void
    {
        $this->get('/procesos/ordenes-trabajo/'.$this->ordenId)
            ->assertOk()
            ->assertSee('Acuerdos de participación')
            ->assertSee('plugins/sweetalerts2/sweetalerts2.min.js');

        $this->getJson('/procesos/ordenes-trabajo/'.$this->ordenId.'/acuerdos?draw=1&start=0&length=10', self::CABECERAS)
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }

    public function test_guarda_edita_y_elimina_acuerdo_de_porcentajes(): void
    {
        $url = '/procesos/ordenes-trabajo/'.$this->ordenId.'/acuerdos';
        $id = $this->postJson($url, [
            'tipo_participacion_id' => $this->tipoId,
            'porcentaje_cliente' => '65.0000',
            'porcentaje_empresa' => '35.0000',
            'tarifa_servicio' => '',
            'moneda_id' => $this->monedaId,
            'estado' => '1',
            'observaciones' => 'Acuerdo inicial',
        ], self::CABECERAS)->assertOk()->json('id');

        $this->putJson($url.'/'.$id, [
            'tipo_participacion_id' => $this->tipoId,
            'porcentaje_cliente' => '60.0000',
            'porcentaje_empresa' => '40.0000',
            'tarifa_servicio' => '',
            'moneda_id' => $this->monedaId,
            'estado' => '0',
            'observaciones' => 'Acuerdo corregido',
        ], self::CABECERAS)->assertOk();

        $this->assertDatabaseHas('acuerdos_orden', [
            'id' => $id,
            'porcentaje_cliente' => '60.0000',
            'porcentaje_empresa' => '40.0000',
            'tarifa_servicio' => null,
            'estado' => 0,
        ]);

        $this->deleteJson($url.'/'.$id, [], self::CABECERAS)->assertOk();
        $this->assertSoftDeleted('acuerdos_orden', ['id' => $id]);
    }

    public function test_tarifa_y_reglas_de_modalidad_y_suma_de_porcentajes(): void
    {
        $url = '/procesos/ordenes-trabajo/'.$this->ordenId.'/acuerdos';
        $id = $this->postJson($url, [
            'tipo_participacion_id' => $this->tipoId,
            'tarifa_servicio' => '125.50',
            'moneda_id' => $this->monedaId,
            'estado' => '1',
        ], self::CABECERAS)->assertOk()->json('id');
        $this->assertDatabaseHas('acuerdos_orden', [
            'id' => $id,
            'tarifa_servicio' => '125.50',
            'porcentaje_cliente' => null,
            'porcentaje_empresa' => null,
        ]);

        $base = [
            'tipo_participacion_id' => $this->tipoId,
            'moneda_id' => $this->monedaId,
            'estado' => '1',
        ];
        $this->postJson($url, $base + [
            'porcentaje_cliente' => '70',
            'porcentaje_empresa' => '20',
        ], self::CABECERAS)->assertUnprocessable()->assertJsonValidationErrors('porcentaje_empresa');

        $this->postJson($url, $base + [
            'porcentaje_cliente' => '70',
            'porcentaje_empresa' => '30',
            'tarifa_servicio' => '100',
        ], self::CABECERAS)->assertUnprocessable()->assertJsonValidationErrors('modalidad');
    }

    public function test_no_se_puede_modificar_un_acuerdo_de_otra_orden(): void
    {
        $segundaOrden = DB::table('ordenes_trabajo')
            ->whereNull('deleted_at')
            ->where('id', '<>', $this->ordenId)
            ->value('id');
        if (! $segundaOrden) {
            $this->markTestSkipped('Hace falta una segunda orden para probar el aislamiento.');
        }

        $acuerdo = AcuerdosOrden::create([
            'orden_trabajo_id' => $segundaOrden,
            'tipo_participacion_id' => $this->tipoId,
            'porcentaje_cliente' => 50,
            'porcentaje_empresa' => 50,
            'moneda_id' => $this->monedaId,
            'estado' => 1,
        ]);

        $this->getJson('/procesos/ordenes-trabajo/'.$this->ordenId.'/acuerdos/'.$acuerdo->id, self::CABECERAS)
            ->assertNotFound();
    }
}
