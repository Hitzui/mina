<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\OrdenesTrabajo;
use App\Models\TrabajosEmpleado;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * La confirmacion de borrado la resuelve realrashid/sweet-alert: su
 * vista blade registra un listener global sobre [data-confirm-delete]
 * y, al confirmar, envia un formulario con _token y _method=DELETE.
 *
 * Solo hacen falta los atributos data-confirm-* en el boton.
 */
class EliminarConConfirmacionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'del-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        $this->actingAs($admin);
    }

    public function test_la_libreria_registra_el_listener_de_confirmacion(): void
    {
        $html = $this->get('/admin/clientes')->assertOk()->getContent();

        // El listener de la libreria, no uno propio
        $this->assertStringContainsString('[data-confirm-delete]', $html);
        $this->assertStringContainsString('var DELETE', $html);
        $this->assertStringContainsString('var TOKEN', $html);

        // Y debe enviar DELETE con el metodo spoofeado
        $this->assertStringContainsString("method !== 'POST'", $html);
        $this->assertStringContainsString("'_method'", $html);
    }

    public function test_no_hay_un_manejador_propio_duplicado(): void
    {
        $html = $this->get('/admin/clientes')->assertOk()->getContent();

        // Un segundo listener abriria dos dialogos de confirmacion
        $this->assertStringNotContainsString('js/confirm-delete.js', $html);

        $this->assertFileDoesNotExist(public_path('js/confirm-delete.js'));
    }

    public function test_los_botones_tienen_los_atributos_que_la_libreria_lee(): void
    {
        $cliente = Cliente::firstOrFail();

        $html = (string) view('admin.clientes._actions', ['id' => $cliente->id])->render();

        $this->assertStringContainsString('data-confirm-delete', $html);
        $this->assertStringContainsString('data-confirm-title', $html);
        $this->assertStringContainsString('data-confirm-text', $html);
        $this->assertStringContainsString('data-confirm-button', $html);
        $this->assertStringContainsString(
            route('admin.clientes.destroy', $cliente->id),
            $html
        );
    }

    public function test_todos_los_listados_tienen_boton_de_eliminar(): void
    {
        $parciales = [
            'admin.clientes._actions',
            'admin.empleados._actions',
            'admin.etapas.action',
            'configuracion.categorias_costos._action',
            'configuracion.tipos_pago_empleado._action',
            'procesos.ordenes_trabajo._action',
            'procesos.ordenes_trabajo.trabajos_empleados._action',
        ];

        foreach ($parciales as $vista) {
            $ruta = resource_path('views/' . str_replace('.', '/', $vista) . '.blade.php');

            $this->assertFileExists($ruta, "No existe el partial {$vista}");

            $contenido = file_get_contents($ruta);

            $this->assertStringContainsString(
                'data-confirm-delete',
                $contenido,
                "El partial {$vista} no activa la confirmacion de la libreria"
            );
        }
    }

    public function test_un_get_no_borra_nada(): void
    {
        $cliente = Cliente::firstOrFail();

        // El enlace sin JS cae en la ruta show: navegacion, no borrado
        $this->get(route('admin.clientes.destroy', $cliente->id))
            ->assertOk();

        $this->assertNotSoftDeleted($cliente);
    }

    public function test_un_delete_si_borra(): void
    {
        $cliente = Cliente::firstOrFail();

        $this->delete(route('admin.clientes.destroy', $cliente->id))
            ->assertRedirect(route('admin.clientes.index'));

        $this->assertSoftDeleted($cliente);
    }

    /**
     * El boton de ordenes de trabajo era un <button> sin href y sin
     * data-confirm-delete, y ningun JS lo atendia: no hacia nada.
     */
    public function test_el_boton_de_ordenes_que_estaba_roto_ya_operativo(): void
    {
        $orden = OrdenesTrabajo::firstOrFail();

        $html = (string) view('procesos.ordenes_trabajo._action', ['id' => $orden->id])->render();

        $this->assertStringContainsString('data-confirm-delete', $html);
        $this->assertStringContainsString(
            route('procesos.ordenes_trabajo.destroy', $orden->id),
            $html
        );

        $this->delete(route('procesos.ordenes_trabajo.destroy', $orden->id))
            ->assertRedirect(route('procesos.ordenes_trabajo.index'));

        $this->assertSoftDeleted($orden);
    }

    /**
     * Regresion: la ruta generaba el placeholder {ordenes_trabajo}
     * mientras destroy() recibe $ordenTrabajo. Los nombres no
     * coincidian, Laravel no hacia route model binding e inyectaba un
     * modelo vacio: delete() no borraba nada y aun asi se mostraba
     * "Orden de trabajo eliminada correctamente".
     */
    public function test_el_binding_de_la_orden_de_trabajo_esta_conectado(): void
    {
        $inexistente = (int) OrdenesTrabajo::withTrashed()->max('id') + 9999;

        $this->delete('/procesos/ordenes-trabajo/' . $inexistente)
            ->assertNotFound();
    }

    /**
     * Este metodo era el unico que devolvia JSON. La libreria envia un
     * formulario normal, asi que el navegador terminaba mostrando el
     * JSON crudo en vez del aviso.
     */
    public function test_el_delete_de_un_trabajo_responde_con_aviso_y_no_con_json(): void
    {
        $orden = OrdenesTrabajo::firstOrFail();
        $trabajo = TrabajosEmpleado::where('orden_trabajo_id', $orden->id)->first();

        if (! $trabajo) {
            $this->markTestSkipped('La orden no tiene trabajos de empleado.');
        }

        $url = route(
            'procesos.ordenes_trabajo.trabajos_empleados.destroy',
            [$orden->id, $trabajo->id]
        );

        // Peticion normal (la que hace la libreria con el formulario)
        $r = $this->delete($url);

        $r->assertRedirect(route('procesos.ordenes_trabajo.show', $orden->id));
        $r->assertSessionHasNoErrors();

        $this->assertStringNotContainsString('"success":true', $r->getContent());
        $this->assertSoftDeleted($trabajo);

        // Y sigue respondiendo JSON si la llamada es AJAX de verdad
        $otro = TrabajosEmpleado::where('orden_trabajo_id', $orden->id)->first();

        if ($otro) {
            $this->deleteJson(route(
                'procesos.ordenes_trabajo.trabajos_empleados.destroy',
                [$orden->id, $otro->id]
            ))->assertOk()->assertJsonPath('success', true);
        }
    }
}
