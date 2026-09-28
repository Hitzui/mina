<?php
/**
 * El catalogo de monedas, de punta a punta.
 *
 * Esta pantalla no existia, y su ausencia era el problema: el catalogo estaba
 * en la base y no habia forma de tocarlo. Todo lo demas de la aplicacion ya
 * lo leia —el tipo de cambio, las compras, los pagos, los costos, el
 * almacen— pero anadir una moneda era cosa de entrar a la base a mano.
 *
 * Lo que se comprueba aqui son las tres reglas, que estan escritas en el
 * controlador y no en el formulario, y que por eso se pueden saltar desde
 * cualquier lado:
 *
 *  1. Solo puede haber una moneda base. El taller contabiliza en una moneda y
 *     todo lo demas se convierte a esa; con dos marcadas, el valor con el que
 *     se convertiria dependeria del orden de las filas.
 *
 *  2. La moneda base no se puede desactivar ni borrar, y no se le puede quitar
 *     la marca. Sin moneda base, el almacen no sabe con que valor tasar el
 *     material que entra, y eso no avisa de nada: el material entra con un
 *     valor y nadie se entera hasta que un cuadre no cuadra.
 *
 *  3. Una moneda que se esta usando no se borra: se desactiva.
 */

namespace Tests\Feature;

use App\Models\Moneda;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MonedaTest extends TestCase
{
    use DatabaseTransactions;

    private const CABECERAS = [
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'monedas-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        $this->actingAs($admin);
    }

    // ------------------------------------------------------------------
    // Ayudas
    // ------------------------------------------------------------------

    /**
     * La moneda base de la aplicacion, la de cordoba.
     *
     * Se busca por codigo y no por id, que es lo que no significa nada: el id
     * lo asigno el generador al crear la base de datos y no dice nada de que
     * esa sea la base.
     */
    private function base(): Moneda
    {
        return Moneda::where('codigo', 'NIO')->first();
    }

    private function crearMoneda(string $codigo = 'XXX', array $extra = []): Moneda
    {
        return Moneda::create(array_merge([
            Moneda::CODIGO => $codigo,
            Moneda::NOMBRE => 'Moneda de la prueba',
            Moneda::SIMBOLO => null,
            Moneda::ES_MONEDA_BASE => false,
            Moneda::ESTADO => true,
        ], $extra));
    }

    // ==================================================================
    // La pantalla
    // ==================================================================

    public function test_la_pantalla_se_ve(): void
    {
        $this->get('/configuracion/monedas')->assertOk()->assertSee('Monedas', false);
    }

    public function test_se_guarda_una_moneda(): void
    {
        $this->post('/configuracion/monedas', [
            'codigo' => 'EUR',
            'nombre' => 'Euros',
            'simbolo' => 'E',
            'estado' => 1,
        ], self::CABECERAS)->assertOk();

        $moneda = Moneda::where('codigo', 'EUR')->first();

        $this->assertNotNull($moneda, 'La moneda deberia haberse guardado');
        $this->assertSame('Euros', $moneda->nombre);
        $this->assertSame('E', $moneda->simbolo);
        $this->assertFalse((bool) $moneda->es_moneda_base, 'Una moneda nueva no es la base');
        $this->assertTrue((bool) $moneda->estado, 'Una moneda nueva viene activa');
    }

    public function test_el_codigo_se_guarda_en_mayusculas(): void
    {
        $this->post('/configuracion/monedas', [
            'codigo' => 'gbp',
            'nombre' => 'Libras esterlinas',
            'estado' => 1,
        ], self::CABECERAS)->assertOk();

        $this->assertSame(
            'GBP',
            Moneda::where('nombre', 'Libras esterlinas')->value('codigo'),
            'El codigo deberia guardarse en mayusculas'
        );
    }

    public function test_un_codigo_que_ya_esta_no_se_puede_repetir(): void
    {
        $this->crearMoneda('EUR', [Moneda::NOMBRE => 'Euros de la prueba']);

        $this->post('/configuracion/monedas', [
            'codigo' => 'eur',
            'nombre' => 'Otra cosa',
            'estado' => 1,
        ], self::CABECERAS)
            ->assertStatus(422)
            ->assertJsonValidationErrors('codigo');

        $this->assertSame(
            1,
            Moneda::where('codigo', 'EUR')->count(),
            'No deberia haberse creado una segunda moneda con el mismo codigo'
        );
    }

    public function test_el_codigo_tiene_que_ser_de_tres_letras(): void
    {
        foreach (['EU', 'EUROS', 'E1', '12'] as $codigo) {
            $this->post('/configuracion/monedas', [
                'codigo' => $codigo,
                'nombre' => 'Moneda de la prueba',
                'estado' => 1,
            ], self::CABECERAS)
                ->assertStatus(422)
                ->assertJsonValidationErrors('codigo');
        }
    }

    public function test_la_moneda_base_y_el_simbolo_no_son_obligatorios(): void
    {
        $this->post('/configuracion/monedas', [
            'codigo' => 'CRC',
            'nombre' => 'Colones costarricenses',
            'estado' => 1,
        ], self::CABECERAS)->assertOk();

        $moneda = Moneda::where('codigo', 'CRC')->first();

        $this->assertNotNull($moneda, 'La moneda deberia haberse guardado sin simbolo');
        $this->assertNull($moneda->simbolo);
    }

    // ==================================================================
    // Regla 1: solo una moneda base
    // ==================================================================

    public function test_al_marcar_otra_como_base_la_anterior_deja_de_serla(): void
    {
        /*
         * Se marca por la pantalla y no con una llamada al modelo, y no por
         * gusto: la regla la aplica el controlador, porque es el que sabe que
         * se esta guardando una moneda y que por lo tanto tiene que quitarle
         * la marca a las demas. Si este test creara la moneda con
         * Moneda::create(), comprobaria que crear una moneda con la casilla
         * marcada no hace nada por si solo, que no es la regla.
         */
        $this->post('/configuracion/monedas', [
            'codigo' => 'EUR',
            'nombre' => 'Euros de la prueba',
            'es_moneda_base' => 1,
            'estado' => 1,
        ], self::CABECERAS)->assertOk();

        $euro = Moneda::where('codigo', 'EUR')->first();

        $this->assertNotNull($euro, 'La moneda deberia haberse guardado');

        $this->assertSame(
            1,
            Moneda::where('es_moneda_base', true)->count(),
            'Deberia quedar una sola moneda base'
        );

        $this->assertSame(
            (int) $euro->id,
            (int) Moneda::where('es_moneda_base', true)->value('id'),
            'La que ha de quedar como base es la nueva'
        );

        $this->assertTrue((bool) $euro->fresh()->es_moneda_base);
        $this->assertFalse(
            (bool) $this->base()->fresh()->es_moneda_base,
            'La moneda base de antes deberia haber dejado de serlo'
        );
    }

    public function test_guardar_una_moneda_que_no_es_base_no_toca_la_base(): void
    {
        $antes = $this->base();

        $this->crearMoneda('EUR');

        $this->assertTrue(
            (bool) $this->base()->fresh()->es_moneda_base,
            'Anadir una moneda normal no deberia quitarle la base a nadie'
        );

        $this->assertSame(
            1,
            Moneda::where('es_moneda_base', true)->count(),
            'Deberia seguir habiendo una sola moneda base'
        );
    }

    public function test_no_se_puede_quitarle_la_base_a_la_moneda_base(): void
    {
        $base = $this->base();

        $this->put('/configuracion/monedas/' . $base->id, [
            'codigo' => $base->codigo,
            'nombre' => $base->nombre,
            'estado' => 1,
            // sin es_moneda_base: se quiere quitar
        ], self::CABECERAS)
            ->assertStatus(422)
            ->assertJsonValidationErrors('es_moneda_base');

        $this->assertTrue(
            (bool) $base->fresh()->es_moneda_base,
            'La moneda base deberia seguir siendo la base'
        );
    }

    public function test_el_aviso_de_la_base_dice_que_hay_que_poner_otra(): void
    {
        $base = $this->base();

        /*
         * El aviso tiene que decir como se arregla, no solo que no se puede.
         * Un "no se puede" a secas deja al usuario sin salida: no puede
         * deshacer y no sabe que tiene que marcar otra primero.
         */
        $respuesta = $this->put('/configuracion/monedas/' . $base->id, [
            'codigo' => $base->codigo,
            'nombre' => $base->nombre,
            'estado' => 1,
        ], self::CABECERAS)->assertStatus(422);

        $this->assertStringContainsString(
            'otra',
            $respuesta->json('errors.es_moneda_base.0'),
            'El aviso deberia decir que hay que marcar otra moneda como base'
        );
    }

    public function test_no_se_puede_desactivar_la_moneda_base(): void
    {
        $base = $this->base();

        $this->put('/configuracion/monedas/' . $base->id, [
            'codigo' => $base->codigo,
            'nombre' => $base->nombre,
            'es_moneda_base' => 1,
            // sin estado: se quiere desactivar
        ], self::CABECERAS)
            ->assertStatus(422)
            ->assertJsonValidationErrors('estado');

        $this->assertTrue(
            (bool) $base->fresh()->estado,
            'La moneda base deberia seguir activa'
        );
    }

    // ==================================================================
    // Regla 3: la que esta en uso no se borra, se desactiva
    // ==================================================================

    public function test_no_se_puede_borrar_la_moneda_base(): void
    {
        $base = $this->base();

        $this->delete('/configuracion/monedas/' . $base->id, [], self::CABECERAS)
            ->assertStatus(422);

        $this->assertNull(
            $base->fresh()->deleted_at,
            'La moneda base no deberia haberse borrado'
        );
    }

    public function test_no_se_puede_borrar_una_moneda_que_se_esta_usando(): void
    {
        $dolar = Moneda::where('codigo', 'USD')->first();

        $this->assertNotNull($dolar, 'El dolar deberia estar en la base de la aplicacion');

        // El dolar tiene dias de tipo de cambio, segun el propio test
        $this->assertGreaterThan(
            0,
            DB::table('tipos_cambio')->where('moneda_id', $dolar->id)->count(),
            'Este test necesita una moneda que se este usando'
        );

        $this->delete('/configuracion/monedas/' . $dolar->id, [], self::CABECERAS)
            ->assertStatus(422);

        $this->assertNull(
            $dolar->fresh()->deleted_at,
            'La moneda en uso no deberia haberse borrado'
        );
    }

    public function test_el_aviso_de_borrar_dice_donde_se_usa_la_moneda(): void
    {
        $dolar = Moneda::where('codigo', 'USD')->first();

        $respuesta = $this->delete('/configuracion/monedas/' . $dolar->id, [], self::CABECERAS)
            ->assertStatus(422);

        $mensaje = $respuesta->json('errors.id.0');

        $this->assertStringContainsString(
            'tipos de cambio',
            $mensaje,
            'El aviso deberia decir donde esta en uso la moneda, con palabras y no con nombres de tabla'
        );

        /*
         * Y que ofrezca la salida. Un aviso que solo dice "no se puede" deja al
         * usuario encerrado en una pantalla donde no puede hacer nada.
         */
        $this->assertStringContainsString(
            'desactivar',
            $mensaje,
            'El aviso deberia decir que lo que si se puede es desactivarla'
        );
    }

    public function test_se_borra_una_moneda_que_no_se_usa(): void
    {
        $moneda = $this->crearMoneda('XXX');

        $this->delete('/configuracion/monedas/' . $moneda->id, [], self::CABECERAS)
            ->assertOk();

        $this->assertNotNull(
            $moneda->fresh()->deleted_at,
            'La moneda sin uso deberia haberse borrado'
        );
    }

    public function test_una_moneda_en_uso_se_puede_desactivar(): void
    {
        $dolar = Moneda::where('codigo', 'USD')->first();

        $this->put('/configuracion/monedas/' . $dolar->id, [
            'codigo' => $dolar->codigo,
            'nombre' => $dolar->nombre,
            'simbolo' => $dolar->simbolo,
            'estado' => 0,
        ], self::CABECERAS)->assertOk();

        $this->assertFalse(
            (bool) $dolar->fresh()->estado,
            'La moneda deberia quedar inactiva'
        );

        /*
         * Y lo que ya se guardo con ella se queda donde estaba. Desactivar no
         * es borrar: si al desactivar se perdieran los treinta dias de tipo de
         * cambio, desactivar seria la forma de perderlos.
         */
        $this->assertSame(
            30,
            DB::table('tipos_cambio')->where('moneda_id', $dolar->id)->count(),
            'Desactivar la moneda no deberia tocar lo que ya se guardo con ella'
        );
    }

    public function test_desactivar_sin_mandar_la_casilla_la_deja_inactiva(): void
    {
        $moneda = $this->crearMoneda('XXX', [Moneda::ESTADO => true]);

        /*
         * Las casillas sin marcar no viajan en el formulario. Si el servidor
         * las ignorara, desmarcar "activa" no guardaria nada y la moneda
         * seguiria activa sin que nadie supiera por que.
         */
        $this->put('/configuracion/monedas/' . $moneda->id, [
            'codigo' => $moneda->codigo,
            'nombre' => $moneda->nombre,
        ], self::CABECERAS)->assertOk();

        $this->assertFalse(
            (bool) $moneda->fresh()->estado,
            'Sin la casilla, la moneda deberia quedar inactiva'
        );
    }

    public function test_se_vuelve_a_poner_el_codigo_de_una_moneda_borrada(): void
    {
        $moneda = $this->crearMoneda('XXX', [Moneda::NOMBRE => 'Moneda de la prueba']);
        $idOriginal = $moneda->id;

        $this->delete('/configuracion/monedas/' . $moneda->id, [], self::CABECERAS)
            ->assertOk();

        $this->assertNotNull($moneda->fresh()->deleted_at);

        /*
         * Volver a poner el codigo de una moneda que se habia borrado.
         *
         * El codigo es un indice unico y la tabla borra de forma logica: sin
         * el arreglo, esto da un error de MySQL —"Duplicate entry"— porque la
         * fila borrada sigue contando para el indice aunque Eloquent no la
         * vea. Y no es un caso raro: es justo lo que pasa cuando se crea una
         * moneda, se ve que estaba mal y se vuelve a crear.
         */
        $this->post('/configuracion/monedas', [
            'codigo' => 'XXX',
            'nombre' => 'La moneda, otra vez',
            'estado' => 1,
        ], self::CABECERAS)->assertOk();

        $this->assertNull(
            $moneda->fresh()->deleted_at,
            'La moneda deberia haber vuelto, y no creado una segunda'
        );

        $this->assertSame(
            'La moneda, otra vez',
            $moneda->fresh()->nombre,
            'La moneda recuperada deberia llevar los datos nuevos'
        );

        $this->assertSame(
            $idOriginal,
            (int) $moneda->fresh()->id,
            'Deberia revive la misma fila y no crear otra'
        );
    }

    // ==================================================================
    // La ficha
    // ==================================================================

    public function test_la_ficha_dice_donde_se_usa_la_moneda(): void
    {
        $dolar = Moneda::where('codigo', 'USD')->first();

        $datos = $this->get('/configuracion/monedas/' . $dolar->id, self::CABECERAS)
            ->assertOk()
            ->json();

        $this->assertSame('USD', $datos['codigo']);
        $this->assertSame('Dólares', $datos['nombre']);
        $this->assertFalse($datos['es_moneda_base']);

        $tablas = array_column($datos['usos'], 'tabla');

        $this->assertContains(
            'tipos_cambio',
            $tablas,
            'La ficha deberia decir que el dolar tiene tipos de cambio'
        );

        $this->assertStringContainsString(
            'tipos de cambio',
            $datos['frase_usos'],
            'La frase deberia decirlo con palabras y no con nombres de tabla'
        );
    }

    public function test_la_ficha_de_una_moneda_sin_uso_no_donde_esta_uso(): void
    {
        $moneda = $this->crearMoneda('XXX');

        $datos = $this->get('/configuracion/monedas/' . $moneda->id, self::CABECERAS)
            ->assertOk()
            ->json();

        $this->assertSame([], $datos['usos'], 'No deberia listar ningun uso');
        $this->assertSame('', $datos['frase_usos']);
    }

    // ==================================================================
    // Quien entra
    // ==================================================================

    public function test_quien_no_tiene_permiso_no_entra(): void
    {
        $usuario = $this->usuarioCon(['tipos_cambio.view']);

        $this->actingAs($usuario)
            ->get('/configuracion/monedas')
            ->assertForbidden();
    }

    public function test_quien_solo_mira_no_puede_crear_una_moneda(): void
    {
        $usuario = $this->usuarioCon(['configuracion.monedas.view']);

        $this->actingAs($usuario)
            ->post('/configuracion/monedas', [
                'codigo' => 'XXX',
                'nombre' => 'Moneda de la prueba',
                'estado' => 1,
            ], self::CABECERAS)
            ->assertForbidden();

        $this->assertSame(
            0,
            Moneda::where('codigo', 'XXX')->count(),
            'Quien solo puede mirar no deberia poder crear una moneda'
        );
    }

    public function test_quien_puede_crear_tambien_puede_editar_y_borrar(): void
    {
        /*
         * El trait de permisos cuelga cada metodo de su accion. Si uno de los
         * tres no estuviera en la lista, el permiso no se miraria sin avisar y
         * el metodo se escribiria y funcionaria para cualquiera que tuviera
         * ver. Esto comprueba los tres, no solo el alta.
         */
        $usuario = $this->usuarioCon([
            'configuracion.monedas.view',
            'configuracion.monedas.create',
            'configuracion.monedas.edit',
            'configuracion.monedas.delete',
        ]);

        $this->actingAs($usuario)
            ->post('/configuracion/monedas', [
                'codigo' => 'XXX',
                'nombre' => 'Moneda de la prueba',
                'estado' => 1,
            ], self::CABECERAS)
            ->assertOk();

        $moneda = Moneda::where('codigo', 'XXX')->first();

        $this->assertNotNull($moneda, 'Con permiso de crear, la moneda deberia existir');

        $this->actingAs($usuario)
            ->put('/configuracion/monedas/' . $moneda->id, [
                'codigo' => 'XXX',
                'nombre' => 'Moneda editada',
                'estado' => 1,
            ], self::CABECERAS)
            ->assertOk();

        $this->assertSame('Moneda editada', $moneda->fresh()->nombre);

        $this->actingAs($usuario)
            ->delete('/configuracion/monedas/' . $moneda->id, [], self::CABECERAS)
            ->assertOk();

        $this->assertNotNull($moneda->fresh()->deleted_at);
    }

    /**
     * Un usuario con exactamente esos permisos y nada mas.
     *
     * @param  array<int, string>  $permisos
     */
    private function usuarioCon(array $permisos): User
    {
        foreach ($permisos as $nombre) {
            Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);
        }

        $usuario = User::create([
            'name' => 'Sin roles',
            'email' => 'sin-roles-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);

        $usuario->syncRoles([]);
        $usuario->syncPermissions($permisos);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $usuario;
    }

    // ==================================================================
    // La tabla que ve el usuario
    // ==================================================================

    public function test_la_tabla_de_ajax_devuelve_las_filas(): void
    {
        $moneda = $this->crearMoneda('XXX', [
            Moneda::NOMBRE => 'Moneda de la prueba',
            Moneda::SIMBOLO => '¤',
        ]);

        $json = $this->get('/configuracion/monedas', self::CABECERAS)
            ->assertOk()
            ->json();

        $fila = $this->filaDe($json['data'], $moneda->id);

        $this->assertNotNull($fila, 'Deberia salir la moneda de la prueba en la tabla');

        $this->assertSame('XXX', $fila['codigo']);
        $this->assertSame('Moneda de la prueba', $fila['nombre']);

        /*
         * La base no deberia salir marcada como base, y la moneda de la prueba
* tampoco. Si el badge de "base" se calculara por posicion en vez de
         * por el dato, saldria en la fila equivocada.
         */
        $this->assertStringContainsString('text-muted', $fila['es_moneda_base']);
        $this->assertStringContainsString('Activa', $fila['estado']);
        $this->assertStringContainsString('bi-pencil', $fila['action']);
        $this->assertStringContainsString('bi-eye', $fila['action']);
    }

    public function test_la_moneda_base_sale_marcada_en_la_tabla(): void
    {
        $base = $this->base();

        $json = $this->get('/configuracion/monedas', self::CABECERAS)
            ->assertOk()
            ->json();

        $fila = $this->filaDe($json['data'], $base->id);

        $this->assertNotNull($fila, 'Deberia salir la moneda base en la tabla');
        $this->assertStringContainsString('Base', $fila['es_moneda_base']);
    }

    public function test_la_tabla_va_ordenada_por_codigo(): void
    {
        $this->crearMoneda('ZZZ');
        $this->crearMoneda('AAA');

        $json = $this->get('/configuracion/monedas', self::CABECERAS)
            ->assertOk()
            ->json();

        $codigos = array_column($json['data'], 'codigo');

        $ordenados = $codigos;
        sort($ordenados);

        $this->assertSame(
            $ordenados,
            $codigos,
            'Las monedas deberian salir ordenadas por codigo, no por orden de alta'
        );
    }

    public function test_los_botones_de_la_fila_llevan_su_url(): void
    {
        $moneda = $this->crearMoneda('XXX');

        $json = $this->get('/configuracion/monedas', self::CABECERAS)->json();

        $fila = $this->filaDe($json['data'], $moneda->id);

        $this->assertNotNull($fila, 'Deberia salir la moneda de la prueba en la tabla');

        /*
         * Los tres botones llevan su url, y no el id escrito dentro del html.
         *
         * El javascript pide los datos al servidor y los pone en el modal.
         * Copiar un campo de la fila al modal es como una tabla se
         * desincroniza de su detalle: en cuanto se cambia lo que se enseña en
         * la fila, el modal enseña lo viejo.
         */
        $this->assertStringContainsString(route('configuracion.monedas.show', $moneda), $fila['action']);
        $this->assertStringContainsString(route('configuracion.monedas.edit', $moneda), $fila['action']);
        $this->assertStringContainsString(route('configuracion.monedas.destroy', $moneda), $fila['action']);
    }

    /**
     * La pantalla tiene que traer los dos modales y el javascript.
     *
     * Esto no se comprueba en ningun otro sitio y es un fallo que sale con
     * la pagina en blanco y sin error de php: los modales y los botones viven
     * en el html, y si uno no se incluye, el boton no hace nada y no hay
     * ningun fallo que mirar mas que "no funciona".
     *
     * El identificador del modal es el que el javascript busca con
     * getElementById, y el del boton es el que escucha con un evento
     * delegado. Si uno de los dos cambia de nombre, el otro se queda
     * escuchando a algo que no existe y el boton no hace nada.
     */
    public function test_la_pantalla_trae_los_modales_y_el_javascript(): void
    {
        $html = $this->get('/configuracion/monedas')->assertOk()->getContent();

        $this->assertStringContainsString('id="modalMoneda"', $html, 'Falta el modal de alta y edicion');
        $this->assertStringContainsString('id="modalVerMoneda"', $html, 'Falta el modal de la ficha');
        $this->assertStringContainsString('id="formMoneda"', $html, 'Falta el formulario del modal');
        $this->assertStringContainsString('id="btnNuevaMoneda"', $html, 'Falta el boton de nueva moneda');
        $this->assertStringContainsString(
            'id="btnEditarDesdeFicha"',
            $html,
            'Falta el boton de editar de la ficha'
        );
        $this->assertStringContainsString('id="verMonedaUsos"', $html, 'Falta la lista de usos de la ficha');

        $this->assertStringContainsString('js/configuracion/monedas.js', $html, 'Falta el javascript de la pagina');

        /*
         * Y los atributos que el javascript necesita para saber a donde
         * enviar. Van en el formulario y no se calculan en el javascript, que
         * es donde se equivocarian: el javascript no sabe cual es el nombre de
         * la ruta.
         */
        $this->assertStringContainsString(
            route('configuracion.monedas.store'),
            $html,
            'El formulario deberia llevar la url del alta'
        );

        $this->assertStringContainsString(
            '__ID__',
            $html,
            'El formulario deberia llevar la url de edicion con el marcador de id'
        );
    }

    /**
     * La fila de la tabla que corresponde a una moneda.
     *
     * Se busca por el identificador de la fila, que es lo que Yajra pone para
     * que el javascript sepa cual es cual. Y es la unica forma de buscarla: la
     * tabla trae tambien las monedas de verdad del taller, y buscar por
     * posicion daria por hecho que no hay nadie mas.
     *
     * @param  array<int, array<string, mixed>>  $filas
     * @return array<string, mixed>|null
     */
    private function filaDe(array $filas, ?int $id): ?array
    {
        foreach ($filas as $fila) {
            if ((int) ($fila['DT_RowId'] ?? 0) === (int) $id) {
                return $fila;
            }
        }

        return null;
    }
}
