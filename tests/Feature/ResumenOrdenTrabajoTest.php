<?php

namespace Tests\Feature;

use App\Models\OrdenesTrabajo;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ResumenOrdenTrabajoTest extends TestCase
{
    use DatabaseTransactions;

    public function test_la_ficha_de_orden_enlaza_el_resumen_y_permite_descargar_su_reporte(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $usuario = User::create([
            'name' => 'Usuario resumen',
            'email' => 'resumen-'.uniqid().'@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $usuario->syncRoles(['admin']);
        $orden = OrdenesTrabajo::query()->firstOrFail();

        $this->actingAs($usuario)
            ->get(route('procesos.ordenes_trabajo.show', $orden))
            ->assertOk()
            ->assertSee(route('procesos.ordenes_trabajo.resumen', $orden));

        $this->get(route('procesos.ordenes_trabajo.resumen', $orden))
            ->assertOk()
            ->assertSee('Resumen operativo y financiero')
            ->assertSee('Seguimiento por proceso')
            ->assertSee('Costos conocidos de la orden');

        $this->get(route('procesos.ordenes_trabajo.resumen.pdf', $orden))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->get(route('procesos.ordenes_trabajo.resumen.xlsx', $orden))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
