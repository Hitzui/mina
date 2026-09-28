<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * El catalogo del modal de elegir proveedor.
 *
 * Se separa del archivo de compras porque lo que se comprueba es otra cosa: que
 * la ruta que consulta el buscador del modal responde lo que tiene que
 * responder, y responde igual lo pedido con sesion y sin ella.
 */
class SelectorDeProveedoresTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = \App\Models\User::create([
            'name' => 'Admin',
            'email' => 'selector-' . uniqid() . '@test.local',
            'password' => \Illuminate\Support\Facades\Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        $this->actingAs($admin);
    }

    /**
     * Le pide los datos a una tabla por ajax, como hace el navegador.
     *
     * Yajra solo contesta json si ve las dos cabeceras: la de que es una
     * peticion de ajax y la de que se acepta json. Con una de las dos se
     * queda pensando que es una visita normal y devuelve html.
     */
    private function ajax(string $url)
    {
        return $this->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ])->get($url);
    }

    public function test_el_catalogo_responde_json_con_filas(): void
    {
        $json = $this->ajax('/inventario/proveedores/selector')
            ->assertOk()
            ->assertJsonStructure(['data', 'recordsTotal', 'recordsFiltered', 'draw']);

        $this->assertIsArray($json->json('data'));
    }

    public function test_cada_fila_trae_los_datos_para_ponerlos_en_el_formulario(): void
    {
        $proveedor = \App\Models\Proveedore::crearConCodigo([
            \App\Models\Proveedore::NOMBRE => 'Ferretería del selector',
            \App\Models\Proveedore::ESTADO => true,
        ]);

        $json = $this->ajax('/inventario/proveedores/selector')->assertOk()->json('data');

        $fila = null;

        foreach ($json as $candidata) {
            if ((int) $candidata['id'] === $proveedor->id) {
                $fila = $candidata;
                break;
            }
        }

        $this->assertNotNull($fila, 'El proveedor deberia salir en el catalogo del modal');

        // El boton de elegir lleva los tres datos en atributos
        $this->assertStringContainsString('btn-seleccionar-proveedor', $fila['seleccionar']);
        $this->assertStringContainsString('data-id="' . $proveedor->id . '"', $fila['seleccionar']);
        $this->assertStringContainsString('data-codigo="' . $proveedor->codigo . '"', $fila['seleccionar']);
        $this->assertStringContainsString('data-nombre="' . $proveedor->nombre . '"', $fila['seleccionar']);
    }

    public function test_el_modal_avisa_cuando_no_hay_sesion(): void
    {
        /*
         * Sin este aviso, la tabla se queda vacia y el usuario ve un
         * catalogo de proveedores sin proveedores, que en este programa no
         * es verdad.
         */
        $html = $this->get('/inventario/compras/create')->assertOk()->getContent();

        $this->assertStringContainsString('id="avisoSinSesion"', $html);

        // Y sale escondido: en el caso normal, que la peticion va bien, no
        // se ve nunca
        $this->assertMatchesRegularExpression(
            '/d-none[^"]*" id="avisoSinSesion"/',
            $html,
            'El aviso de sesion deberia salir escondido'
        );
    }

    public function test_el_js_distingue_el_401_del_resto_de_fallos(): void
    {
        $js = file_get_contents(public_path('js/inventario/selector_proveedor.js'));

        $this->assertStringContainsString('error.dt', $js);

        $this->assertStringContainsString(
            'status === 401',
            $js,
            'El aviso deberia decir en concreto que se acabo la sesion, que '
            . 'tiene remedio, en vez de un fallo generico'
        );

        $this->assertStringContainsString('avisoSinSesion', $js);
    }

}
