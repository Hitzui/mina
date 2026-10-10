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

class LiquidacionOrdenTest extends TestCase
{
    use DatabaseTransactions;

    private const CABECERAS = [
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ];

    private int $ordenId;

    private int $acuerdoId;

    private int $tipoId;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $usuario = User::create([
            'name' => 'Admin liquidaciones',
            'email' => 'liquidacion-'.uniqid().'@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $usuario->syncRoles(['admin']);
        foreach (['view', 'create', 'edit', 'delete'] as $accion) {
            $usuario->givePermissionTo(Permission::findOrCreate('liquidaciones.'.$accion, 'web'));
        }
        $this->actingAs($usuario);

        $this->ordenId = (int) DB::table('ordenes_trabajo')
            ->whereNull('deleted_at')
            ->where('estado', '<>', 0)
            ->value('id');
        if (! $this->ordenId) {
            $this->markTestSkipped('Hace falta una orden activa para probar liquidaciones.');
        }

        $monedaId = (int) DB::table('monedas')->where('estado', true)->value('id');
        if (! $monedaId) {
            $this->markTestSkipped('Hace falta una moneda activa para crear un acuerdo.');
        }

        $this->tipoId = TiposParticipacion::create([
            'nombre' => 'Participación liquidación '.uniqid(),
            'estado' => true,
        ])->id;
        $this->acuerdoId = AcuerdosOrden::create([
            'orden_trabajo_id' => $this->ordenId,
            'tipo_participacion_id' => $this->tipoId,
            'porcentaje_cliente' => 65,
            'porcentaje_empresa' => 35,
            'moneda_id' => $monedaId,
            'estado' => 1,
        ])->id;
    }

    public function test_crea_reparte_edita_y_elimina_un_lote_de_oro(): void
    {
        $url = '/procesos/ordenes-trabajo/'.$this->ordenId.'/liquidaciones';
        $id = $this->postJson($url, [
            'fecha' => '2026-10-10',
            'gramos_recuperados' => '1.2345',
            'acuerdo_id' => $this->acuerdoId,
            'observaciones' => 'Lote de prueba',
        ], self::CABECERAS)->assertOk()->json('id');

        $this->assertDatabaseHas('liquidaciones', [
            'id' => $id,
            'gramos_recuperados' => '1.2345',
            'gramos_cliente' => '0.8024',
            'gramos_empresa' => '0.4321',
            'porcentaje_cliente' => '65.0000',
            'porcentaje_empresa' => '35.0000',
        ]);

        $this->putJson($url.'/'.$id, [
            'fecha' => '2026-10-11',
            'gramos_recuperados' => '2.0000',
            'observaciones' => 'Corrección del lote',
        ], self::CABECERAS)->assertOk();

        $this->assertDatabaseHas('liquidaciones', [
            'id' => $id,
            'gramos_recuperados' => '2.0000',
            'gramos_cliente' => '1.3000',
            'gramos_empresa' => '0.7000',
            'porcentaje_cliente' => '65.0000',
            'porcentaje_empresa' => '35.0000',
        ]);

        $this->deleteJson($url.'/'.$id, [], self::CABECERAS)->assertOk();
        $this->assertSoftDeleted('liquidaciones', ['id' => $id]);
    }

    public function test_se_muestra_la_tabla_y_no_admite_acuerdo_de_tarifa(): void
    {
        $this->get('/procesos/ordenes-trabajo/'.$this->ordenId)
            ->assertOk()
            ->assertSee('Liquidaciones de oro');

        $url = '/procesos/ordenes-trabajo/'.$this->ordenId.'/liquidaciones';
        $this->getJson($url.'?draw=1&start=0&length=10', self::CABECERAS)
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        $monedaId = (int) DB::table('monedas')->where('estado', true)->value('id');
        $tarifa = AcuerdosOrden::create([
            'orden_trabajo_id' => $this->ordenId,
            'tipo_participacion_id' => $this->tipoId,
            'tarifa_servicio' => 100,
            'moneda_id' => $monedaId,
            'estado' => 1,
        ]);

        $this->postJson($url, [
            'fecha' => '2026-10-10',
            'gramos_recuperados' => '1.0000',
            'acuerdo_id' => $tarifa->id,
        ], self::CABECERAS)->assertUnprocessable()->assertJsonValidationErrors('acuerdo_id');
    }
}
