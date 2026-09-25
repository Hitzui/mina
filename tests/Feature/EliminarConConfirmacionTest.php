<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\OrdenesTrabajo;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * El boton de eliminar es un enlace con data-confirm-delete. Antes de
 * existir js/confirm-delete.js no habia nada que lo procesara.
 *
 * Comportamiento real previo: el enlace hace GET a
 * /admin/clientes/{id}, que coincide con la ruta show, asi que el clic
 * llevaba al detalle del cliente sin borrar nada y sin avisar. Con el
 * script, el clic pide confirmacion y envia un DELETE de verdad.
 */
class EliminarConConfirmacionTest extends TestCase
{
    use DatabaseTransactions;

    /** Los DataTable cargan por AJAX: los botones llegan en el JSON. */
    private const PARAMS_DATATABLE = [
        'draw' => 1,
        'start' => 0,
        'length' => 10,
    ];

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

    public function test_los_botones_de_eliminar_tienen_los_atributos_de_confirmacion(): void
    {
        $cliente = Cliente::firstOrFail();

        // Se renderiza el partial real con un registro real, para
        // comprobar el HTML que llega al navegador (DataTable incluido)
        $html = (string) view('admin.clientes._actions', ['id' => $cliente->id])->render();

        $this->assertStringContainsString('data-confirm-delete', $html);
        $this->assertStringContainsString('data-confirm-title', $html);
        $this->assertStringContainsString('data-confirm-text', $html);
        $this->assertStringContainsString('data-confirm-button', $html);

        // Y debe apuntar a la ruta destroy, no a otro lado
        $this->assertStringContainsString(
            route('admin.clientes.destroy', $cliente->id),
            $html
        );
    }

    public function test_el_script_de_confirmacion_se_carga_en_todas_las_paginas(): void
    {
        $this->get('/admin/clientes')
            ->assertOk()
            ->assertSee(asset('js/confirm-delete.js'), false);

        $this->get('/')
            ->assertOk()
            ->assertSee(asset('js/confirm-delete.js'), false);
    }

    public function test_la_pagina_expone_el_token_csrf_para_el_script(): void
    {
        $this->get('/admin/clientes')
            ->assertOk()
            ->assertSee('name="csrf-token"', false);
    }

    public function test_un_get_a_la_ruta_destroy_no_borra_nada(): void
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

    public function test_todos_los_listados_tienen_boton_de_eliminar(): void
    {
        $listados = [
            '/admin/clientes',
            '/admin/empleados',
            '/admin/etapas',
            '/configuracion/categorias-costos',
            '/configuracion/tipos-pago-empleado',
        ];

        foreach ($listados as $url) {
            // Las paginas cargan bien
            $this->get($url)->assertOk();

            // Y su partial de acciones trae la confirmacion
            $this->assertStringContainsString(
                'data-confirm-delete',
                $this->renderAcciones($url),
                "El listado {$url} no tiene boton de eliminar con confirmacion"
            );
        }
    }

    /**
     * Localizar el partial _action/_actions de un listado.
     */
    private function renderAcciones(string $listadoUrl): string
    {
        $parciales = [
            '/admin/clientes' => 'admin.clientes._actions',
            '/admin/empleados' => 'admin.empleados._actions',
            '/admin/etapas' => 'admin.etapas.action',
            '/configuracion/categorias-costos' => 'configuracion.categorias_costos._action',
            '/configuracion/tipos-pago-empleado' => 'configuracion.tipos_pago_empleado._action',
        ];

        $vista = $parciales[$listadoUrl];
        $ruta = resource_path('views/' . str_replace('.', '/', $vista) . '.blade.php');

        return file_exists($ruta) ? file_get_contents($ruta) : '';
    }

    public function test_el_boton_de_ordenes_que_estaba_roto_ya_operativo(): void
    {
        $orden = OrdenesTrabajo::firstOrFail();

        // El partial antes era un <button> sin href y sin JS que lo atara.
        // Ahora se renderiza y debe producir un enlace con confirmacion.
        $html = (string) view('procesos.ordenes_trabajo._action', ['id' => $orden->id])->render();

        $this->assertStringContainsString('data-confirm-delete', $html);
        $this->assertStringContainsString(
            route('procesos.ordenes_trabajo.destroy', $orden->id),
            $html
        );

        // Y la ruta responde a DELETE
        $this->delete(route('procesos.ordenes_trabajo.destroy', $orden->id))
            ->assertRedirect(route('procesos.ordenes_trabajo.index'));

        $this->assertSoftDeleted($orden);
    }

    public function test_el_script_de_confirmacion_existe_y_envia_delete(): void
    {
        $ruta = public_path('js/confirm-delete.js');

        $this->assertFileExists($ruta);

        $contenido = file_get_contents($ruta);

        $this->assertStringContainsString('data-confirm-delete', $contenido);
        $this->assertStringContainsString("'_method'", $contenido);
        $this->assertStringContainsString("'DELETE'", $contenido);
        $this->assertStringContainsString('_token', $contenido);

        // Debe evitar la navegacion por defecto
        $this->assertStringContainsString('preventDefault', $contenido);
    }

    /**
     * Regresion: la ruta generaba el placeholder {ordenes_trabajo}
     * mientras destroy() recibe $ordenTrabajo. Los nombres no
     * coincidian, Laravel no hacia route model binding e inyectaba un
     * modelo vacio: delete() no borraba nada y aun asi se mostraba
     * "Orden de trabajo eliminada correctamente".
     *
     * Con el binding correcto, un id inexistente da 404 en vez de un
     * exito mentiroso.
     */
    public function test_el_binding_de_la_orden_de_trabajo_esta_conectado(): void
    {
        $inexistente = (int) OrdenesTrabajo::withTrashed()->max('id') + 9999;

        $this->delete('/procesos/ordenes-trabajo/' . $inexistente)
            ->assertNotFound();
    }

    public function test_el_binding_entrega_el_modelo_real_al_controlador(): void
    {
        $orden = OrdenesTrabajo::firstOrFail();

        $this->delete(route('procesos.ordenes_trabajo.destroy', $orden->id))
            ->assertRedirect(route('procesos.ordenes_trabajo.index'));

        $this->assertNotNull(
            OrdenesTrabajo::withTrashed()->find($orden->id)?->deleted_at
        );
    }
}
