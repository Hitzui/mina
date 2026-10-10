<?php

namespace Tests\Feature;

use App\Models\Cajas;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CrearAdminCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_force_crea_un_usuario_adicional_con_su_caja_sin_cambiar_el_anterior(): void
    {
        $anterior = User::create([
            'name' => 'Administrador anterior',
            'email' => 'admin-anterior-' . uniqid() . '@test.local',
            'password' => Hash::make('contrasena-anterior'),
        ]);
        $caja = Cajas::create(['nombre' => 'Caja usuario nuevo ' . uniqid(), 'estado' => true]);
        $passwordAnterior = $anterior->password;
        $emailNuevo = 'admin-nuevo-' . uniqid() . '@test.local';

        $this->artisan('mina:crear-admin', [
            '--force' => true,
            '--name' => 'Administrador nuevo',
            '--email' => $emailNuevo,
            '--password' => 'contrasena-nueva-123',
            '--caja-id' => $caja->id,
        ])->assertExitCode(0);

        $this->assertSame($passwordAnterior, $anterior->fresh()->password);
        $this->assertDatabaseHas('users', [
            'email' => $emailNuevo,
            'caja_id' => $caja->id,
        ]);
        $this->assertSame(2, User::query()->whereIn('email', [$anterior->email, $emailNuevo])->count());
    }
}
