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

    public function test_el_js_no_monta_la_tabla_a_mano(): void
    {
        /*
         * Yajra ya monta la tabla al cargar la pagina. Si el script del modal
         * la monta otra vez, al abrir el modal se encuentra con que ya existe,
         * sale sin hacer nada, y el girador se queda girando con la lista sin
         * aparecer y sin un solo error en la consola.
         *
         * Eso fue lo que paso, y es un fallo que no se ve leyendo el codigo
         * del script: el DataTable() si esta ahi, y solo falla en el momento
         * en que se comprueba si hace algo.
         */
        $js = file_get_contents(public_path('js/inventario/selector_proveedor.js'));

        // Coge la instancia que Yajra publica, en vez de construir la suya
        $this->assertStringContainsString(
            "window.LaravelDataTables?.['proveedor-selector-table']",
            $js,
            'El script deberia coger la tabla que Yajra ya construyo'
        );

        // Y la llamada a construir solo queda en el camino de emergencia, que
        // se puede ver contando cuantas veces aparece
        $this->assertSame(
            1,
            preg_match_all('/\$tabla\.DataTable\(\)/', $js),
            'La tabla no deberia construirse mas que en el camino de emergencia, '
            . 'cuando Yajra no la ha construido'
        );
    }

    public function test_la_tabla_del_modal_no_lleva_la_clase_que_la_esconde(): void
    {
        /*
         * Con display:none, una tabla se mide a cero de ancho y las columnas
         * salen todas iguales de largas, y despues no hay manera de
         * arreglarlo sin quitarsela. Como el modal ya la esconde mientras esta
         * cerrado, la vista no tiene nada que esconder ahi.
         */
        $html = $this->get('/inventario/compras/create')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<table[^>]*id="proveedor-selector-table"/',
            $html,
            'Deberia estar la tabla del selector en la pantalla'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/<table[^>]*class="[^"]*d-none[^"]*"[^>]*id="proveedor-selector-table"/',
            $html,
            'La tabla del selector no deberia llevar d-none: se mediria a cero '
            . 'de ancho y las columnas saldrian todas iguales de largas'
        );
    }

    public function test_el_girador_se_quita_al_abrir_el_modal(): void
    {
        /*
         * El sintoma era el girador parandose con la lista sin salir. Se
         * quita siempre al abrir el modal, se haya construido la tabla o no;
         * antes solo se quitaba en el camino que nunca se tomaba.
         */
        $js = file_get_contents(public_path('js/inventario/selector_proveedor.js'));

        $posicionDelEvento = strpos($js, "shown.bs.modal");
        $posicionDelGirador = strpos($js, '$cargador.addClass(\'d-none\')');

        $this->assertIsInt($posicionDelEvento);
        $this->assertIsInt($posicionDelGirador);

        $this->assertGreaterThan(
            $posicionDelEvento,
            $posicionDelGirador,
            'El girador deberia quitarse dentro del manejador que abre el modal, '
            . 'o se queda girando con la lista sin salir'
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
