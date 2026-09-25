<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Verifica que el panel exija autenticacion y respete los permisos.
 *
 * Usa DatabaseTransactions: nada de lo que se crea aqui persiste,
 * porque la configuracion de pruebas apunta a la base real 'mina'.
 */
class ControlAccesoTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function usuarioConRol(string $rol): User
    {
        $user = User::create([
            'name' => "Usuario $rol",
            'email' => "$rol-" . uniqid() . '@test.local',
            'password' => bcrypt('secreto-de-prueba'),
        ]);

        $user->assignRole($rol);

        return $user->fresh();
    }

    public function test_las_rutas_de_negocio_requieren_autenticacion(): void
    {
        $clienteId = Cliente::first()?->id;

        $this->get('/admin/clientes')->assertRedirect('/login');

        $this->get('/admin/empleados')->assertRedirect('/login');

        $this->get('/procesos/ordenes-trabajo')->assertRedirect('/login');

        $this->get('/configuracion/categorias-costos')->assertRedirect('/login');

        if ($clienteId) {
            $this->delete("/admin/clientes/{$clienteId}")->assertRedirect('/login');
        }
    }

    public function test_la_pagina_de_inicio_sigue_siendo_publica(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_el_operador_ve_el_listado_pero_no_puede_borrar(): void
    {
        $operador = $this->usuarioConRol('operador');
        $clienteId = Cliente::first()?->id;

        $this->actingAs($operador)
            ->get('/admin/clientes')
            ->assertOk();

        if ($clienteId) {
            $this->actingAs($operador)
                ->delete("/admin/clientes/{$clienteId}")
                ->assertForbidden();
        }
    }

    public function test_el_operador_no_accede_a_configuracion(): void
    {
        $operador = $this->usuarioConRol('operador');

        $this->actingAs($operador)
            ->get('/configuracion/categorias-costos')
            ->assertForbidden();

        $this->actingAs($operador)
            ->get('/configuracion/tipos-pago-empleado')
            ->assertForbidden();
    }

    public function test_el_supervisor_no_puede_borrar_registros_maestros(): void
    {
        $supervisor = $this->usuarioConRol('supervisor');
        $clienteId = Cliente::first()?->id;

        $this->actingAs($supervisor)
            ->get('/admin/clientes')
            ->assertOk();

        if ($clienteId) {
            $this->actingAs($supervisor)
                ->delete("/admin/clientes/{$clienteId}")
                ->assertForbidden();
        }
    }

    public function test_el_administrador_tiene_acceso_completo(): void
    {
        $admin = $this->usuarioConRol('admin');
        $clienteId = Cliente::first()?->id;

        $this->actingAs($admin)->get('/admin/clientes')->assertOk();
        $this->actingAs($admin)->get('/admin/empleados')->assertOk();
        $this->actingAs($admin)->get('/admin/etapas')->assertOk();
        $this->actingAs($admin)->get('/configuracion/categorias-costos')->assertOk();
        $this->actingAs($admin)->get('/configuracion/tipos-pago-empleado')->assertOk();
        $this->actingAs($admin)->get('/procesos/ordenes-trabajo')->assertOk();

        if ($clienteId) {
            $this->actingAs($admin)
                ->delete("/admin/clientes/{$clienteId}")
                ->assertStatus(302);
        }
    }

    public function test_los_registros_no_se_borran_de_verdad_al_rechazar(): void
    {
        $operador = $this->usuarioConRol('operador');
        $cliente = Cliente::first();

        if (! $cliente) {
            $this->markTestSkipped('No hay clientes en la base de datos.');
        }

        $antes = Cliente::withTrashed()->count();

        $this->actingAs($operador)
            ->delete("/admin/clientes/{$cliente->id}")
            ->assertForbidden();

        $this->assertSame(
            $antes,
            Cliente::withTrashed()->count(),
            'El cliente fue eliminado aunque el operador no tiene permiso.'
        );
    }
}
