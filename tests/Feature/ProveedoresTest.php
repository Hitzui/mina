<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\Moneda;
use App\Models\Producto;
use App\Models\Proveedore;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Proveedores: alta, edicion, ficha y borrado, los tres en modal.
 *
 * Comprueba lo mismo que en los materiales, mas el caso propio de aqui: un
 * proveedor con compras no se borra, se desactiva, porque el historial de lo
 * que se compro depende de el.
 */
class ProveedoresTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'prov-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        $this->actingAs($admin);
    }

    private function crearProveedor(array $extra = []): Proveedore
    {
        return Proveedore::crearConCodigo(array_merge([
            Proveedore::NOMBRE => 'Ferretería de prueba',
            Proveedore::CONTACTO => 'Juan Pérez',
            Proveedore::TELEFONO => '5555-1111',
            Proveedore::EMAIL => 'juan@prueba.local',
            Proveedore::DIRECCION => 'Calle 1, Managua',
            Proveedore::ESTADO => true,
        ], $extra));
    }

    // ==================================================================
    // El codigo lo pone el sistema
    // ==================================================================

    public function test_el_codigo_se_asigna_solo(): void
    {
        $this->postJson('/inventario/proveedores', [
            'nombre' => 'Cemento del Pacífico',
            'unidad_medida' => '',
            'estado' => 1,
        ])->assertOk()->assertJson(['success' => true]);

        $proveedor = Proveedore::where('nombre', 'Cemento del Pacífico')->firstOrFail();

        $this->assertMatchesRegularExpression(
            '/^' . Proveedore::PREFIJO_CODIGO . '\d{6}$/',
            $proveedor->codigo
        );
    }

    public function test_la_serie_de_proveedores_no_se_mezcla_con_la_de_materiales(): void
    {
        /*
         * El codigo se genera con un trait compartido, asi que conviene
         * comprobar que las dos series van por separado. Si se mezclaran, un
         * proveedor podria salir con el numero siguiente de un material.
         */
        $material = Producto::crearConCodigo([
            Producto::NOMBRE => 'Material',
            Producto::UNIDAD_MEDIDA => 'kg',
        ]);

        $proveedor = $this->crearProveedor();

        $this->assertStringStartsWith('MAT-', $material->codigo);
        $this->assertStringStartsWith('PROV-', $proveedor->codigo);
    }

    public function test_la_serie_va_incrementando(): void
    {
        $primero = $this->crearProveedor(['nombre' => 'A']);
        $segundo = $this->crearProveedor(['nombre' => 'B']);

        $this->assertSame(
            (int) substr($primero->codigo, 5) + 1,
            (int) substr($segundo->codigo, 5),
            'El segundo proveedor tiene que ser el siguiente'
        );
    }

    public function test_uno_que_manda_un_codigo_no_lo_impone(): void
    {
        $this->postJson('/inventario/proveedores', [
            'codigo' => 'PROV-999999',
            'nombre' => 'Con código impuesto',
            'estado' => 1,
        ])->assertOk();

        $this->assertDatabaseMissing('proveedores', ['codigo' => 'PROV-999999']);
    }

    public function test_el_codigo_no_cambia_al_editar(): void
    {
        $proveedor = $this->crearProveedor();

        $this->putJson("/inventario/proveedores/{$proveedor->id}", [
            'codigo' => 'PROV-888888',
            'nombre' => 'Ferretería renombrada',
            'estado' => 1,
        ])->assertOk();

        $proveedor->refresh();

        $this->assertNotSame('PROV-888888', $proveedor->codigo);
        $this->assertSame('Ferretería renombrada', $proveedor->nombre);
    }

    // ==================================================================
    // Validar el formulario
    // ==================================================================

    public function test_hacen_falta_nombre_y_se_avisa(): void
    {
        $this->post('/inventario/proveedores', [])
            ->assertSessionHasErrors('nombre');
    }

    public function test_un_correo_que_no_parece_valido_se_avisa(): void
    {
        $this->post('/inventario/proveedores', [
            'nombre' => 'Prueba',
            'email' => 'esto no es un correo',
        ])->assertSessionHasErrors('email');
    }

    public function test_el_correo_y_el_telefono_son_opcionales(): void
    {
        $this->post('/inventario/proveedores', [
            'nombre' => 'Solo con nombre',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('proveedores', ['nombre' => 'Solo con nombre']);
    }

    public function test_un_proveedor_inactivo_se_puede_registrar(): void
    {
        /*
         * El interruptor manda un 0 escondido delante, que es lo que permite
         * desmarcarlo y que llegue 0 en vez de nada. Sin ese 0, un proveedor
         * al que se lequite la marca se guardaria con el valor por defecto
         * de la base, que es activo.
         */
        $this->post('/inventario/proveedores', [
            'nombre' => 'Inactivo a proposito',
            'estado' => '0',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('proveedores', [
            'nombre' => 'Inactivo a proposito',
            'estado' => 0,
        ]);
    }

    // ==================================================================
    // La pantalla: los tres en modal
    // ==================================================================

    public function test_la_pantalla_carga_con_los_modales(): void
    {
        $html = $this->get('/inventario/proveedores')->assertOk()->getContent();

        $this->assertStringContainsString('proveedores-table', $html);
        $this->assertStringContainsString('id="btnNuevoProveedor"', $html);
        $this->assertStringContainsString('id="modalProveedor"', $html);
        $this->assertStringContainsString('id="formProveedor"', $html);
        $this->assertStringContainsString('id="modalShowProveedor"', $html);
        $this->assertStringContainsString('proveedores.js', $html);

        $this->assertStringNotContainsString(
            "route('inventario.proveedores.create')",
            $html,
            'El alta se hace en modal, no en otra página'
        );
    }

    public function test_el_modal_tiene_las_urls_de_crear_y_editar(): void
    {
        $html = $this->get('/inventario/proveedores')->assertOk()->getContent();

        $this->assertStringContainsString(
            'data-store-url="' . route('inventario.proveedores.store') . '"',
            $html
        );

        $this->assertStringContainsString('__ID__', $html);
    }

    public function test_el_formulario_no_pide_el_codigo(): void
    {
        $formulario = file_get_contents(
            resource_path('views/inventario/proveedores/_form.blade.php')
        );

        $this->assertStringNotContainsString(
            'name="codigo"',
            $formulario,
            'El código lo pone el sistema'
        );

        $this->assertStringContainsString('Se asigna automáticamente', $formulario);
    }

    public function test_la_edicion_devuelve_los_datos_para_el_modal(): void
    {
        $proveedor = $this->crearProveedor([
            Proveedore::OBSERVACIONES => 'Paga a 30 días',
        ]);

        $r = $this->getJson("/inventario/proveedores/{$proveedor->id}/edit")
            ->assertOk()
            ->assertJsonStructure([
                'id', 'codigo', 'nombre', 'contacto', 'telefono', 'email',
                'direccion', 'observaciones', 'estado',
            ]);

        $this->assertSame($proveedor->codigo, $r->json('codigo'));
        $this->assertSame('Ferretería de prueba', $r->json('nombre'));
        $this->assertSame('Juan Pérez', $r->json('contacto'));
        $this->assertSame('Paga a 30 días', $r->json('observaciones'));
    }

    public function test_la_ficha_devuelve_los_datos_y_las_compras(): void
    {
        $proveedor = $this->crearProveedor();

        $r = $this->getJson("/inventario/proveedores/{$proveedor->id}")
            ->assertOk()
            ->assertJsonStructure([
                'id', 'codigo', 'nombre', 'contacto', 'telefono', 'email',
                'direccion', 'observaciones', 'estado', 'compras',
                'tiene_compras',
            ]);

        $this->assertSame($proveedor->codigo, $r->json('codigo'));
        $this->assertSame(0, $r->json('compras'));
        $this->assertFalse($r->json('tiene_compras'));
    }

    // ==================================================================
    // Borrar: con compras se desactiva, sin compras se borra
    // ==================================================================

    public function test_sin_compras_se_borra_de_verdad(): void
    {
        $proveedor = $this->crearProveedor();

        $r = $this->deleteJson("/inventario/proveedores/{$proveedor->id}")
            ->assertOk();

        $this->assertTrue($r->json('success'));
        $this->assertArrayNotHasKey('desactivado', $r->json());

        $this->assertSoftDeleted('proveedores', ['id' => $proveedor->id]);
    }

    public function test_con_compras_se_desactiva_en_vez_de_borrarse(): void
    {
        $proveedor = $this->crearProveedor();

        $this->crearCompraDe($proveedor);

        $r = $this->deleteJson("/inventario/proveedores/{$proveedor->id}")
            ->assertOk();

        $this->assertTrue($r->json('desactivado'));
        $this->assertStringContainsString('desactivó', $r->json('message'));

        $this->assertNotSoftDeleted('proveedores', ['id' => $proveedor->id]);
        $this->assertDatabaseHas('proveedores', [
            'id' => $proveedor->id,
            'estado' => false,
        ]);
    }

    public function test_la_ficha_avisa_de_que_tiene_compras(): void
    {
        $proveedor = $this->crearProveedor();
        $this->crearCompraDe($proveedor);

        $r = $this->getJson("/inventario/proveedores/{$proveedor->id}")->assertOk();

        $this->assertTrue($r->json('tiene_compras'));
        $this->assertSame(1, $r->json('compras'));
    }

    private function crearCompraDe(Proveedore $proveedor): Compra
    {
        $moneda = Moneda::where('es_moneda_base', true)->firstOrFail();

        $compra = new Compra();
        $compra->proveedor_id = $proveedor->id;
        $compra->codigo = 'C-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $compra->fecha = now()->format('Y-m-d');
        $compra->subtotal = 100;
        $compra->impuesto = 0;
        $compra->total = 100;
        $compra->moneda_id = $moneda->id;
        $compra->estado = 1;
        $compra->save();

        return $compra;
    }

    // ==================================================================
    // Permisos
    // ==================================================================

    public function test_hacen_falta_permisos_para_tocar_los_proveedores(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->usuarioSinPermisos());

        $this->get('/inventario/proveedores')->assertForbidden();
        $this->post('/inventario/proveedores', ['nombre' => 'Colado'])->assertForbidden();

        $this->assertDatabaseMissing('proveedores', ['nombre' => 'Colado']);
    }

    private function usuarioSinPermisos(): User
    {
        $usuario = User::create([
            'name' => 'Sin permisos',
            'email' => 'prov-sin-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);

        $usuario->syncRoles([]);

        return $usuario;
    }
}
