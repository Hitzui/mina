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

    /**
     * El texto de la confirmacion tiene que estar en el atributo que la
     * libreria lee.
     *
     * data-confirm-delete es solo la bandera que dice "este boton pide
     * confirmacion". Poner ahi el mensaje no da error ni aviso: la
     * libreria lo ignora y sale el dialogo por defecto, que antes estaba
     * en ingles. Y esto no se ve leyendo el partial, sino el texto que
     * sale en pantalla.
     */
    public function test_el_texto_de_la_confirmacion_esta_en_su_atributo(): void
    {
        $parciales = [
            'inventario.productos._action',
            'inventario.movimientos._action',
            'procesos.procesos_orden.materiales._action',
        ];

        foreach ($parciales as $vista) {
            $ruta = resource_path('views/' . str_replace('.', '/', $vista) . '.blade.php');

            $this->assertFileExists($ruta, "No existe el partial {$vista}");

            $contenido = file_get_contents($ruta);

            $this->assertStringContainsString(
                'data-confirm-title',
                $contenido,
                "El partial {$vista} no trae el titulo de la confirmacion"
            );

            $this->assertStringContainsString(
                'data-confirm-text',
                $contenido,
                "El partial {$vista} no trae el texto de la confirmacion"
            );

            $this->assertStringContainsString(
                'data-confirm-button',
                $contenido,
                "El partial {$vista} no trae el texto del boton"
            );

            /*
             * El atributo de la bandera no debe llevar el mensaje. Se
             * comprueba que venga solo o con "true", y no con una frase:
             * es la causa del fallo.
             */
            $this->assertDoesNotMatchRegularExpression(
                '/data-confirm-delete\s*=\s*["\'][^"\']{20,}["\']/',
                $contenido,
                "El partial {$vista} tiene el mensaje en data-confirm-delete, "
                . 'que es solo la bandera: el texto se ignoraria'
            );
        }
    }

    /**
     * Los textos por defecto del dialogo, en español.
     *
     * Son la red de seguridad para los botones que no traen texto propio.
     * Si vuelven al inglés, cualquier boton nuevo que se olvide de ponerlos
     * los saca en pantalla en inglés sin que nadie se entere.
     */
    public function test_los_textos_por_defecto_estan_en_espanol(): void
    {
        $ingleses = [
            'Are you sure',
            'This cannot be undone',
            'Yes, delete it',
            "'OK'",
            "'Cancel'",
            "'Deny'",
        ];

        $contenido = file_get_contents(config_path('sweetalert.php'));

        foreach ($ingleses as $ingles) {
            $this->assertStringNotContainsString(
                $ingles,
                $contenido,
                "Quedo un texto en ingles en la config: {$ingles}"
            );
        }

        // Y que los de confirmar y borrar esten puestos
        $this->assertStringContainsString('¿Eliminar?', $contenido);
        $this->assertStringContainsString('Cancelar', $contenido);
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
    /**
     * El boton de eliminar de la pantalla de la orden usaba
     * data-form-delete, que la libreria no soporta: sin href ni formulario
     * asociado la libreria salia en silencio y el boton no hacia nada.
     *
     * El patron correcto es poner la confirmacion en el formulario y
     * asociar el boton con el atributo HTML "form".
     */
    public function test_el_boton_de_eliminar_de_la_orden_usa_el_patron_de_la_libreria(): void
    {
        // Se lee el fuente y no se renderiza: la vista necesita el datatable
        // montado y aqui solo importa como esta escrito el boton
        $html = (string) file_get_contents(
            resource_path('views/procesos/ordenes_trabajo/show.blade.php')
        );

        // Se quitan los comentarios de Blade: aqui se explica por que
        // data-form-delete no sirve, y el test comprobaria su propio texto
        $html = preg_replace('/\{\{--.*?--\}\}/s', '', $html);

        // El boton se asocia al formulario con el atributo HTML form=
        $this->assertStringContainsString(
            'form="formEliminarOrden"',
            $html,
            'El boton de eliminar deberia asociarse al formulario con el atributo form'
        );

        // Y la confirmacion va en el formulario
        $this->assertMatchesRegularExpression(
            '/<form[^>]*id="formEliminarOrden"[\s\S]*?data-confirm-delete/',
            $html,
            'La confirmacion deberia estar en el formulario, no en el boton'
        );

        // data-form-delete no existe en la libreria: no debe quedar
        $this->assertStringNotContainsString(
            'data-form-delete',
            $html,
            'data-form-delete no lo soporta la libreria de confirmacion'
        );

        // El boton no puede ser type=button, o nunca dispara el formulario
        $this->assertDoesNotMatchRegularExpression(
            '/<button[^>]*form="formEliminarOrden"[^>]*type="button"/',
            $html,
            'El boton deberia ser type=submit para enviar el formulario'
        );
    }

    public function test_el_delete_de_un_trabajo_responde_con_aviso_y_no_con_json(): void
    {
        $trabajo = TrabajosEmpleado::with('proceso_orden.orden_trabajo')->first();

        if (! $trabajo) {
            $this->markTestSkipped('No hay trabajos de empleado.');
        }

        $orden = $trabajo->proceso_orden->orden_trabajo;
        $proceso = $trabajo->proceso_orden;

        $url = route(
            'procesos.ordenes_trabajo.procesos.trabajos_empleados.destroy',
            [$orden, $proceso, $trabajo]
        );

        // Peticion normal (la que hace la libreria con el formulario)
        $r = $this->delete($url);

        $r->assertRedirect(
            route('procesos.ordenes_trabajo.procesos.show', [$orden, $proceso])
        );
        $r->assertSessionHasNoErrors();

        $this->assertStringNotContainsString('"success":true', $r->getContent());
        $this->assertSoftDeleted($trabajo);

        // Y sigue respondiendo JSON si la llamada es AJAX de verdad
        $otro = TrabajosEmpleado::query()->first();

        if ($otro) {
            $this->deleteJson(route(
                'procesos.ordenes_trabajo.procesos.trabajos_empleados.destroy',
                [$otro->proceso_orden->orden_trabajo, $otro->proceso_orden, $otro]
            ))->assertOk()->assertJsonPath('success', true);
        }
    }
}
