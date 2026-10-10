<?php

namespace Tests\Feature;

use App\Models\Cajas;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AsignarCajaAUsuarioCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_asigna_una_caja_activa_al_usuario(): void
    {
        $usuario = User::create([
            'name' => 'Usuario de prueba',
            'email' => 'usuario-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba1234'),
        ]);
        $caja = Cajas::create(['nombre' => 'Caja de prueba ' . uniqid(), 'estado' => true]);

        $this->artisan('mina:asignar-caja', [
            'usuario' => $usuario->email,
            'caja_id' => $caja->id,
        ])->expectsOutput("Caja '{$caja->nombre}' asignada al usuario '{$usuario->name}'.")
            ->assertExitCode(0);

        $this->assertSame((int) $caja->id, (int) $usuario->fresh()->caja_id);
    }

    public function test_no_asigna_cajas_inactivas(): void
    {
        $usuario = User::create([
            'name' => 'Usuario de prueba',
            'email' => 'usuario-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba1234'),
        ]);
        $caja = Cajas::create(['nombre' => 'Caja inactiva ' . uniqid(), 'estado' => false]);

        $this->artisan('mina:asignar-caja', [
            'usuario' => $usuario->email,
            'caja_id' => $caja->id,
        ])->expectsOutput('La caja indicada no existe o está desactivada.')
            ->assertExitCode(1);

        $this->assertNull($usuario->fresh()->caja_id);
    }
}
