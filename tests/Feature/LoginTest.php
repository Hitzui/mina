<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Comprueba el flujo de acceso completo usando el login real de Fortify
 * (POST a /login), no simulando la sesion con actingAs().
 */
class LoginTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Cada test usa un correo distinto porque Fortify limita a 5 intentos
     * de login por minuto y por (correo + IP). Asi un test no agota el
     * cupo de los siguientes, sin desactivar la proteccion.
     */
    private function crearAdmin(): User
    {
        $user = User::create([
            'name' => 'Administrador',
            'email' => 'admin-' . uniqid() . '@test.local',
            'password' => Hash::make('contrasena-de-prueba'),
        ]);

        $user->syncRoles(['admin']);

        return $user->fresh();
    }

    public function test_el_formulario_de_login_es_un_formulario_real(): void
    {
        $r = $this->get('/login');

        $r->assertOk();

        // Debe enviar los datos a la ruta real de Fortify
        $r->assertSee('action="' . route('login') . '"', false);
        $r->assertSee('name="_token"', false);
        $r->assertSee('name="email"', false);
        $r->assertSee('name="password"', false);
        $r->assertSee('type="password"', false);
        $r->assertSee('name="remember"', false);

        // Y no debe quedar el boton que no hacia nada
        $r->assertDontSee('javascript:void(0);" class="btn  btn-social-login', false);
    }

    public function test_no_se_puede_registrar_nadie_por_publico(): void
    {
        // El registro esta desactivado: /register no debe existir
        $this->get('/register')->assertNotFound();

        $antes = User::count();

        $this->post('/register', [
            'name' => 'Intruso',
            'email' => 'intruso@test.local',
            'password' => 'contrasena-de-prueba',
        ]);

        $this->assertSame(
            $antes,
            User::count(),
            'Se creo un usuario por una ruta de registro que deberia estar cerrada.'
        );
    }

    public function test_el_administrador_puede_iniciar_sesion_y_entrar_al_panel(): void
    {
        $admin = $this->crearAdmin();

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'contrasena-de-prueba',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();

        // Con la sesion iniciada debe entrar a las areas protegidas
        $this->get('/admin/clientes')->assertOk();
        $this->get('/admin/empleados')->assertOk();
        $this->get('/admin/etapas')->assertOk();
        $this->get('/configuracion/categorias-costos')->assertOk();
        $this->get('/configuracion/tipos-pago-empleado')->assertOk();
        $this->get('/procesos/ordenes-trabajo')->assertOk();
    }

    public function test_una_contrasena_incorrecta_no_autentica(): void
    {
        $admin = $this->crearAdmin();

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'esta-es-la-equivocada',
        ]);

        $this->assertGuest();

        $this->get('/admin/clientes')->assertRedirect('/login');
    }

    public function test_el_formulario_conserva_el_correo_al_rechazar(): void
    {
        $admin = $this->crearAdmin();

        $this->from('/login')->post('/login', [
            'email' => $admin->email,
            'password' => 'incorrecta',
        ])->assertRedirect('/login');

        // Al volver al formulario, el correo debe seguir puesto
        $this->get('/login')
            ->assertOk()
            ->assertSee('value="' . $admin->email . '"', false);
    }

    public function test_se_puede_cerrar_sesion(): void
    {
        $admin = $this->crearAdmin();

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'contrasena-de-prueba',
        ]);

        $this->assertAuthenticated();

        $this->post('/logout')->assertRedirect('/');

        $this->assertGuest();

        $this->get('/admin/clientes')->assertRedirect('/login');
    }

    public function test_un_visitante_sin_sesion_no_ve_el_boton_de_salir(): void
    {
        // La portada es publica y usa el mismo layout que el panel.
        // El navbar no debe ofrecer "Cerrar sesion" a quien no entro.
        $r = $this->get('/');

        $r->assertOk();
        $r->assertDontSee('Cerrar sesión');
        $r->assertDontSee('action="' . route('logout') . '"', false);
        $r->assertSee('Iniciar sesión');
    }

    public function test_el_navbar_muestra_el_usuario_cuando_hay_sesion(): void
    {
        $admin = $this->crearAdmin();

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'contrasena-de-prueba',
        ]);

        $r = $this->get('/admin/clientes');

        $r->assertOk();
        $r->assertSee('Administrador');
        $r->assertSee('Cerrar sesión');
    }
}
