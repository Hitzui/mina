<?php

namespace Tests\Feature\Api\V1;

use App\Models\Cajas;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use DatabaseTransactions;

    public function test_usuario_puede_iniciar_sesion_recibir_token_y_consultar_su_perfil(): void
    {
        [$usuario, $caja] = $this->crearUsuario();
        Permission::findOrCreate('clientes.view', 'web');
        $usuario->givePermissionTo('clientes.view');

        $respuesta = $this->postJson('/api/v1/auth/login', [
            'email' => $usuario->email,
            'password' => 'password-de-prueba',
            'device_name' => 'KMP Android',
        ])->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', $usuario->id)
            ->assertJsonPath('data.user.cash_register.id', $caja->id)
            ->assertJsonPath('data.user.permissions.0', 'clientes.view');

        $token = $respuesta->json('data.token');
        $this->assertNotEmpty($token);
        $this->assertNotEmpty($respuesta->json('data.expires_at'));
        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $usuario->email);

        $this->withToken($token)->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->assertJsonPath('data.api_version', 'v1')
            ->assertJsonPath('data.cash_register.id', $caja->id);
    }

    public function test_cierre_de_sesion_revoca_el_token_actual(): void
    {
        [$usuario] = $this->crearUsuario();
        $token = $usuario->createToken('KMP iOS')->plainTextToken;

        $this->withToken($token)->deleteJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Sesión cerrada.');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $usuario->id,
            'name' => 'KMP iOS',
        ]);
    }

    public function test_revocacion_global_cierra_todos_los_dispositivos(): void
    {
        [$usuario] = $this->crearUsuario();
        $token = $usuario->createToken('KMP Android')->plainTextToken;
        $usuario->createToken('KMP iOS');

        $this->withToken($token)->deleteJson('/api/v1/auth/tokens')
            ->assertOk()
            ->assertJsonPath('message', 'Se cerraron todas las sesiones móviles.');

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $usuario->id]);
    }

    public function test_login_rechaza_credenciales_invalidas_y_usuarios_sin_caja_activa(): void
    {
        [$usuario] = $this->crearUsuario();

        $this->postJson('/api/v1/auth/login', [
            'email' => $usuario->email,
            'password' => 'incorrecta',
            'device_name' => 'KMP',
        ])->assertUnauthorized()->assertJsonPath('code', 'INVALID_CREDENTIALS');

        $cajaInactiva = Cajas::create([
            'nombre' => 'Caja inactiva '.Str::upper(Str::random(5)),
            'estado' => false,
        ]);
        $sinCajaActiva = User::create([
            'name' => 'Usuario sin caja activa',
            'email' => 'sin-caja-'.Str::lower(Str::random(10)).'@test.local',
            'password' => Hash::make('password-de-prueba'),
            'caja_id' => $cajaInactiva->id,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $sinCajaActiva->email,
            'password' => 'password-de-prueba',
            'device_name' => 'KMP',
        ])->assertForbidden()->assertJsonPath('code', 'ACTIVE_CASH_REGISTER_REQUIRED');
    }

    public function test_endpoints_protegidos_requieren_bearer_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->getJson('/api/v1/bootstrap')->assertUnauthorized();
    }

    /** @return array{User, Cajas} */
    private function crearUsuario(): array
    {
        $caja = Cajas::create([
            'nombre' => 'Caja API '.Str::upper(Str::random(7)),
            'estado' => true,
        ]);
        $usuario = User::create([
            'name' => 'Usuario API',
            'email' => 'api-'.Str::lower(Str::random(10)).'@test.local',
            'password' => Hash::make('password-de-prueba'),
            'caja_id' => $caja->id,
        ]);

        return [$usuario, $caja];
    }
}
