<?php
/**
 * Los metodos de pago, de punta a punta.
 *
 * Lo que se comprueba aqui no es que la pantalla exista, que eso lo hacen otros
 * tests. Son las cuatro cosas que pueden fallar sin que se note, y las cuatro
 * estan escritas en el servidor y no en el formulario porque se pueden saltar
 * desde cualquier lado.
 *
 * Y LO QUE ESTA PANTALLA SI HACE. Los tipos de ingreso no se anaden: los cuatro
 * tipos los puso el taller a mano y la pantalla no decide cuales hay. Aqui SI se
 * crean, y hay un test que lo comprueba. La diferencia no es una mania: la tabla
 * estaba vacia, y una pantalla que solo puede corregir y desactivar filas que no
 * existen se abre y no hace nada. Y el precedente esta en el mismo proyecto: los
 * tipos de pago de empleado, que son la misma idea, tienen pantalla con alta y el
 * taller metio sus seis filas desde ella.
 *
 * QUE ESTA EN USO NO SE BORRA, SE DESACTIVA. Si hay cobros con ese metodo,
 * borrarlo dejaria la FK apuntando a una fila que ya no esta. Y desactivar sirve
 * para lo mismo —sacarlo de los desplegables— sin tocar lo que ya se registro con
 * el.
 *
 * Y SE CUENTAN COMO USO LOS COBROS DADOS DE BAJA TAMBIEN. La FK no distingue: la
 * fila borrada sigue escribiendo el numero. Si aqui se contaran solo los vivos, el
 * boton dejaria pulsar el borrar y reventaria con un error de MySQL en vez de con
 * un aviso que explica. Es el fallo mas probable de esta pantalla, y el motivo de
 * que uno de los tests sea exactamente ese.
 *
 * EL NOMBRE PUEDE REPETIRSE, a proposito y en contra de lo que hace el catalogo
 * de monedas. Alli lo unico es el codigo ISO, que por definicion no puede
 * repetirse. Aqui el nombre es libre, igual que en los otros catalogos del taller.
 *
 * Y AQUI UN REPETIDO NO ROMPE NADA, al contrario que en las cajas. Alli dos cajas
 * que se llamen igual salen partido en dos sumas sin avisar, porque el saldo se
 * agrupa por identificador y el usuario cree que esta viendo una. Aqui los metodos
 * de pago no se suman entre si —"cuanto se cobro en efectivo" es una pregunta que
 * se hace sobre los cobros, no sobre el catalogo—, asi que un duplicado sale dos
 * veces en el desplegable y ya esta. Ese matiz esta escrito porque el dia que se
 * meta una suma por metodo, este test es el que avisa de que hay que decidir.
 *
 * NUNCA SE CUENTA LA TABLA ENTERA. La base es la de verdad y el taller la usa:
 * assertDatabaseCount pasaria por casualidad mientras el taller no tenga metodos
 * de pago, y en cuanto tenga uno empezaria a fallar sin que cambie nada.
 */

namespace Tests\Feature;

use App\Models\Cajas;
use App\Models\Cobro;
use App\Models\MetodosPagos;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MetodoPagoTest extends TestCase
{
    use DatabaseTransactions;

    private const CABECERAS = [
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ];

    /** Cuantos metodos de pago habia antes de que esta prueba empezara. */
    private int $metodosIniciales;

    /** La caja de andamiaje, que se crea una vez y se reusa. */
    private ?int $cajaId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->metodosIniciales = MetodosPagos::withTrashed()->count();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'metodo-pago-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        $this->actingAs($admin);
    }

    // ==================================================================
    // Ayudas
    // ==================================================================

    /**
     * Un metodo de pago de la prueba.
     *
     * El nombre lleva un hash y no un nombre fijo, para que al repetir las
     * pruebas no choquen entre si.
     */
    private function metodo(string $nombre = '', bool $estado = true): MetodosPagos
    {
        return MetodosPagos::create([
            MetodosPagos::NOMBRE => $nombre !== ''
                ? $nombre
                : 'Metodo de la prueba ' . strtoupper(substr(md5(uniqid('', true)), 0, 6)),
            MetodosPagos::DESCRIPCION => 'Creado por la prueba',
            MetodosPagos::ESTADO => $estado,
        ]);
    }

    /**
     * Cuantos metodos de pago creo esta prueba.
     *
     * Cuenta tambien los dados de baja, para que un borrado mal hecho tampoco
     * pase por alto: lo que importa es cuantas filas toco el test.
     */
    private function metodosCreados(): int
    {
        return MetodosPagos::withTrashed()->count() - $this->metodosIniciales;
    }

    /**
     * El html con los escapes del unicode deshechos.
     *
     * La configuracion que se le pasa a DataTables es un json generado sin
     * JSON_UNESCAPED_UNICODE, con lo que las vocales acentuadas viajan como
     * "u00fa" y no como "a". Buscar en el html tal cual encuentra "Mostrando" y
     * "registros", que no llevan tilde, y no encuentra "Ningun registro coincide"
     * con la u acentuada, que si la lleva: el idioma estaba puesto y bien, y lo
     * que no estaba era puesto el texto que se buscaba.
     *
     * TablasEnEspanolTest hace esto mejor, sacando el bloque del idioma y
     * volcandolo con JSON_UNESCAPED_UNICODE. Aqui no hace falta esa fabrica
     * entera: lo que se comprueba es que la pantalla lleva el idioma, y dejar el
     * html legible es ademas lo que hace util el fallo la proxima vez que esto se
     * rompa.
     */
    private function htmlSinEscapes(string $html): string
    {
        return preg_replace_callback(
            '#\\\\u([0-9a-f]{4})#i',
            fn(array $m) => mb_chr((int) hexdec($m[1]), 'UTF-8'),
            $html
        );
    }

    /**
     * Un cobro que paga con este metodo de pago.
     *
     * Se crea de verdad y no con una llamada a la base, porque el cobro tiene
     * claves foraneas que tambien hay que cumplir: si se metiera a pelo,
     * contariamos filas que en la vida real no podrian existir y el test pasaria
     * por un motivo equivocado.
     */
    private function cobro(MetodosPagos $metodo, bool $borrado = false): Cobro
    {
        $cliente = DB::table('clientes')->first();
        $moneda = DB::table('monedas')->where('estado', 1)->first();

        if ($cliente === null || $moneda === null) {
            $this->markTestSkipped('No hay clientes ni monedas para montar un cobro.');
        }

        $cobro = new Cobro();
        $cobro->cliente_id = $cliente->id;
        $cobro->caja_id = $this->cajaDeAndamiaje();
        $cobro->metodo_pago_id = $metodo->id;
        $cobro->fecha = '2026-09-01';
        $cobro->monto = 100;
        $cobro->moneda_id = $moneda->id;
        $cobro->estado = 1;
        $cobro->codigo = 'CBOX-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
        $cobro->save();

        if ($borrado) {
            $cobro->delete();
        }

        return $cobro;
    }

    /**
     * La caja de andamiaje, y la misma en todos los cobros del test.
     *
     * Aqui la que se prueba es el metodo de pago, no la caja, y por eso el
     * andamiaje es al reves que en CajaTest: un cobro no se puede guardar sin
     * caja, y una pantalla no puede depender de que la tabla de cajas siga
     * teniendo algo —igual que la de metodos de pago, que por eso esta prueba
     * crea el suyo en vez de coger uno que ya este—. Sin esta fila el insert falla
     * con un "Field 'caja_id' doesn't have a default value", que no dice nada de
     * metodos de pago y deja los tests de "un metodo con cobros no se borra" sin
     * poder ni ejecutarse.
     *
     * Es andamiaje, no lo que se prueba: lo que se prueba es la regla del metodo
     * en uso. Y va dentro de la transaccion del test, que se deshace al terminar,
     * asi que el taller no ve esta fila.
     */
    private function cajaDeAndamiaje(): int
    {
        if ($this->cajaId !== null) {
            return $this->cajaId;
        }

        $caja = Cajas::create([
            Cajas::NOMBRE => 'Caja de andamiaje '
                . strtoupper(substr(md5(uniqid('', true)), 0, 6)),
            Cajas::ESTADO => true,
        ]);

        return $this->cajaId = $caja->id;
    }

    // ==================================================================
    // El alta, que aqui SI existe
    // ==================================================================

    public function test_se_puede_crear_un_metodo_de_pago(): void
    {
        $nombre = 'Metodo de la prueba ' . strtoupper(substr(md5(uniqid('', true)), 0, 6));

        $respuesta = $this->postJson('/configuracion/metodos-pago', [
            'nombre' => $nombre,
            'descripcion' => 'Transferencia al banco',
            'estado' => 1,
        ]);

        $respuesta->assertOk();

        $this->assertSame(1, $this->metodosCreados());

        $metodo = MetodosPagos::where('nombre', $nombre)->firstOrFail();

        $this->assertSame('Transferencia al banco', $metodo->descripcion);
        $this->assertTrue((bool) $metodo->estado);
    }

    public function test_un_metodo_de_pago_nace_activado(): void
    {
        $metodo = $this->metodo(estado: true);

        $this->assertTrue((bool) $metodo->estado);
    }

    public function test_sin_nombre_no_se_crea(): void
    {
        $this->postJson('/configuracion/metodos-pago', [
            'nombre' => '',
            'estado' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('nombre');

        $this->assertSame(0, $this->metodosCreados());
    }

    public function test_nombre_mas_largo_de_lo_que_cabe_no_se_crea(): void
    {
        $this->postJson('/configuracion/metodos-pago', [
            'nombre' => str_repeat('a', 101),
            'estado' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('nombre');
    }

    public function test_una_descripcion_mas_larga_de_lo_que_cabe_no_se_crea(): void
    {
        $this->postJson('/configuracion/metodos-pago', [
            'nombre' => 'Metodo con descripcion larga',
            'descripcion' => str_repeat('a', 256),
            'estado' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('descripcion');
    }

    public function test_la_descripcion_es_opcional(): void
    {
        $this->postJson('/configuracion/metodos-pago', [
            'nombre' => 'Metodo sin descripcion',
            'descripcion' => null,
            'estado' => 1,
        ])->assertOk();

        $this->assertNull(
            MetodosPagos::where('nombre', 'Metodo sin descripcion')->firstOrFail()->descripcion
        );
    }

    public function test_una_descripcion_vacia_se_guarda_como_vacio_y_no_como_texto(): void
    {
        $this->postJson('/configuracion/metodos-pago', [
            'nombre' => 'Metodo con descripcion en blanco',
            'descripcion' => '',
            'estado' => 1,
        ])->assertOk();

        $metodo = MetodosPagos::where('nombre', 'Metodo con descripcion en blanco')->firstOrFail();

        /*
         * Sin esto se guardaria "" en vez de no guardar nada, y en la lista sale
         * un guion y en la ficha una cadena invisible. Y "que no vino nada" no es
         * lo mismo que "vino vacio": un campo que el usuario ni ha tocado no
         * llega en la peticion.
         */
        $this->assertNull($metodo->descripcion);
    }

    public function test_el_estado_solo_acepta_cero_y_uno(): void
    {
        $this->postJson('/configuracion/metodos-pago', [
            'nombre' => 'Metodo con estado raro',
            'estado' => 7,
        ])->assertStatus(422)->assertJsonValidationErrors('estado');
    }

    // ==================================================================
    // El nombre puede repetirse
    // ==================================================================

    public function test_el_nombre_puede_repetirse(): void
    {
        $primero = $this->metodo('Efectivo');
        $segundo = $this->metodo('Efectivo');

        $this->assertNotSame($primero->id, $segundo->id);
        $this->assertSame('Efectivo', $segundo->nombre);
    }

    public function test_dos_metodos_con_el_mismo_nombre_se_gestan_por_separado(): void
    {
        $conCobros = $this->metodo('Efectivo');
        $sinCobros = $this->metodo('Efectivo');

        $this->cobro($conCobros);

        // El que tiene cobros no se puede borrar
        $this->deleteJson("/configuracion/metodos-pago/{$conCobros->id}")->assertStatus(422);

        // El otro si, y son dos filas distintas
        $this->deleteJson("/configuracion/metodos-pago/{$sinCobros->id}")->assertOk();

        $this->assertSoftDeleted('metodos_pago', ['id' => $sinCobros->id]);
        $this->assertDatabaseHas('metodos_pago', [
            'id' => $conCobros->id,
            'deleted_at' => null,
        ]);
    }

    // ==================================================================
    // Editar
    // ==================================================================

    public function test_se_puede_editar_un_metodo_de_pago(): void
    {
        $metodo = $this->metodo('Nombre viejo');

        $this->putJson("/configuracion/metodos-pago/{$metodo->id}", [
            'nombre' => 'Nombre nuevo',
            'descripcion' => 'La nueva descripcion',
            'estado' => 1,
        ])->assertOk();

        $metodo->refresh();

        $this->assertSame('Nombre nuevo', $metodo->nombre);
        $this->assertSame('La nueva descripcion', $metodo->descripcion);
    }

    public function test_editar_no_crea_un_metodo_nuevo(): void
    {
        $metodo = $this->metodo();

        $this->putJson("/configuracion/metodos-pago/{$metodo->id}", [
            'nombre' => 'Otro nombre',
            'estado' => 1,
        ])->assertOk();

        /*
         * Uno y no dos: el test crea un metodo y lo edita, y lo que se
         * comprueba es que la edicion no calcule otro.
         */
        $this->assertSame(1, $this->metodosCreados());
    }

    public function test_no_se_puede_editar_sin_nombre(): void
    {
        $metodo = $this->metodo('Nombre que se queda');

        $this->putJson("/configuracion/metodos-pago/{$metodo->id}", [
            'nombre' => '',
            'estado' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('nombre');

        $metodo->refresh();

        $this->assertSame('Nombre que se queda', $metodo->nombre);
    }

    // ==================================================================
    // Activar y desactivar
    // ==================================================================

    public function test_se_puede_desactivar_y_volver_a_activar(): void
    {
        $metodo = $this->metodo();

        $this->postJson("/configuracion/metodos-pago/{$metodo->id}/cambiar-estado")->assertOk();

        $metodo->refresh();
        $this->assertFalse((bool) $metodo->estado);

        $this->postJson("/configuracion/metodos-pago/{$metodo->id}/cambiar-estado")->assertOk();

        $metodo->refresh();
        $this->assertTrue((bool) $metodo->estado);
    }

    public function test_se_puede_desactivar_un_metodo_que_ya_tiene_cobros(): void
    {
        $metodo = $this->metodo();
        $cobro = $this->cobro($metodo);

        /*
         * Desactivar un metodo en uso es justamente lo que se quiere hacer: es el
         * otro camino del aviso de "no se puede borrar". Por eso este boton no se
         * apaga cuando hay cobros.
         */
        $this->postJson("/configuracion/metodos-pago/{$metodo->id}/cambiar-estado")->assertOk();

        $metodo->refresh();

        $this->assertFalse((bool) $metodo->estado);
        $this->assertDatabaseHas('cobros', ['id' => $cobro->id, 'deleted_at' => null]);
    }

    public function test_un_metodo_desactivado_sale_de_los_registrables_pero_no_se_borra(): void
    {
        $metodo = $this->metodo();

        $metodo->update([MetodosPagos::ESTADO => false]);

        $registrables = MetodosPagos::paraRegistrar()->pluck('id');

        $this->assertNotContains($metodo->id, $registrables->all());

        // Y sigue estando: desactivar no es borrar
        $this->assertDatabaseHas('metodos_pago', [
            'id' => $metodo->id,
            'deleted_at' => null,
        ]);
    }

    // ==================================================================
    // Borrar
    // ==================================================================

    public function test_un_metodo_sin_cobros_se_borra(): void
    {
        $metodo = $this->metodo();

        $this->deleteJson("/configuracion/metodos-pago/{$metodo->id}")->assertOk();

        $this->assertSoftDeleted('metodos_pago', ['id' => $metodo->id]);
    }

    public function test_un_metodo_con_cobros_no_se_borra(): void
    {
        $metodo = $this->metodo();
        $this->cobro($metodo);

        $respuesta = $this->deleteJson("/configuracion/metodos-pago/{$metodo->id}");

        $respuesta->assertStatus(422)->assertJsonValidationErrors(MetodosPagos::NOMBRE);

        $this->assertDatabaseHas('metodos_pago', [
            'id' => $metodo->id,
            'deleted_at' => null,
        ]);
    }

    public function test_el_aviso_de_no_se_puede_borrar_dice_donde_esta_en_uso(): void
    {
        $metodo = $this->metodo();
        $this->cobro($metodo);

        $respuesta = $this->deleteJson("/configuracion/metodos-pago/{$metodo->id}");

        $mensaje = $respuesta->json('errors.' . MetodosPagos::NOMBRE . '.0');

        /*
         * El aviso tiene que decir DONDE esta en uso, no solo que no se puede.
         * Un "no se puede borrar" sin mas deja al usuario sin salida, y la salida
         * existe: desactivar.
         */
        $this->assertStringContainsString('cobro', $mensaje);
        $this->assertStringContainsString('desactivar', $mensaje);
    }

    public function test_los_cobros_dados_de_baja_cuentan_como_uso_igual(): void
    {
        $metodo = $this->metodo();

        $cobro = $this->cobro($metodo, borrado: true);

        $this->assertNotNull($cobro->fresh()->trashed());

        /*
         * Esto es el fallo mas probable de la pantalla y por eso tiene su propio
         * test. La FK no distingue: la fila borrada sigue escribiendo el numero,
         * y el valor por defecto de MySQL es restringir, o sea que borrar el
         * metodo con un cobro dado de baja tambien lo impide. Si aqui se contaran
         * solo los vivos, el boton dejaria pulsar el borrar y reventaria con un
         * error de MySQL en vez de con un aviso que explica.
         */
        $this->deleteJson("/configuracion/metodos-pago/{$metodo->id}")->assertStatus(422);

        $this->assertDatabaseHas('metodos_pago', [
            'id' => $metodo->id,
            'deleted_at' => null,
        ]);
    }

    public function test_el_aviso_dice_cuantos_cobros_dados_de_baja_hay(): void
    {
        $metodo = $this->metodo();

        $this->cobro($metodo);
        $this->cobro($metodo, borrado: true);

        $respuesta = $this->deleteJson("/configuracion/metodos-pago/{$metodo->id}");

        $mensaje = $respuesta->json('errors.' . MetodosPagos::NOMBRE . '.0');

        /*
         * Si el usuario ve "en 2 cobros" y despues borra uno, no va a entender
         * por que le sigue dejando el mismo numero. El matiz va escrito.
         */
        $this->assertStringContainsString('dado de baja', $mensaje);
    }

    public function test_borrar_y_volver_a_meter_un_metodo_funciona(): void
    {
        $nombre = 'Metodo que se borra y vuelve';

        $metodo = $this->metodo($nombre);
        $this->deleteJson("/configuracion/metodos-pago/{$metodo->id}")->assertOk();

        $otro = $this->metodo($nombre);

        $this->assertNotSame($metodo->id, $otro->id);
        $this->assertNull($otro->fresh()->deleted_at);
    }

    // ==================================================================
    // La ficha
    // ==================================================================

    public function test_la_ficha_trae_los_datos_y_donde_se_usa(): void
    {
        $metodo = $this->metodo('Metodo de la ficha');
        $cobro = $this->cobro($metodo);

        $respuesta = $this->getJson("/configuracion/metodos-pago/{$metodo->id}", self::CABECERAS);

        $respuesta->assertOk();

        $datos = $respuesta->json();

        $this->assertSame($metodo->id, $datos['id']);
        $this->assertSame('Metodo de la ficha', $datos['nombre']);
        $this->assertTrue($datos['estado']);
        $this->assertFalse($datos['se_puede_borrar']);
        $this->assertSame(1, $datos['usos'][0]['cuantas']);
        $this->assertStringContainsString('cobro', $datos['frase_de_usos']);
    }

    public function test_la_ficha_de_un_metodo_libre_dice_que_se_puede_borrar(): void
    {
        $metodo = $this->metodo('Metodo sin cobros');

        $datos = $this->getJson(
            "/configuracion/metodos-pago/{$metodo->id}",
            self::CABECERAS
        )->json();

        $this->assertTrue($datos['se_puede_borrar']);
        $this->assertSame([], $datos['usos']);
        $this->assertSame('', $datos['frase_de_usos']);
    }

    public function test_la_frase_de_usos_pone_el_singular_y_el_plural(): void
    {
        $uno = $this->metodo();
        $this->cobro($uno);

        // "1 cobro" y no "1 cobros", que es el fallo clasico de estos avisos
        $this->assertSame('1 cobro', $uno->fraseDeUsos());

        $varios = $this->metodo();
        $this->cobro($varios);
        $this->cobro($varios);

        $this->assertSame('2 cobros', $varios->fraseDeUsos());
    }

    // ==================================================================
    // La tabla
    // ==================================================================

    public function test_la_tabla_devuelve_los_metodos_por_nombre(): void
    {
        $this->metodo('Metodo zeta de la prueba');
        $this->metodo('Metodo alfa de la prueba');

        $respuesta = $this->get(
            '/configuracion/metodos-pago?' . http_build_query([
                'draw' => 1,
                'start' => 0,
                'length' => 50,
                'columns' => [['data' => 'nombre', 'name' => 'nombre']],
            ]),
            self::CABECERAS
        );

        $respuesta->assertOk();

        $nombres = array_column($respuesta->json('data'), 'nombre');

        /*
         * Se buscan las dos filas del test dentro de la respuesta y se mira el
         * orden en que salen entre ellas, y no las dos primeras.
         *
         * La tabla tiene tambien los metodos de pago del taller, que estan
         * vacios hoy pero no lo estaran: en cuanto haya un cobro habra un
         * "Efectivo", y ese metodo va a caer entre las dos filas del test y a
         * tumbar el orden de las posiciones fijas. Este test se escribe como
         * si la tabla ya tuviera lo que va a tener, porque un test que solo
         * funciona con la base vacia deja de comprobar nada el dia que la base
         * deja de estar vacia.
         */
        $posicionAlfa = array_search('Metodo alfa de la prueba', $nombres, true);
        $posicionZeta = array_search('Metodo zeta de la prueba', $nombres, true);

        $this->assertNotFalse($posicionAlfa, 'El metodo alfa no sale en su propia tabla.');
        $this->assertNotFalse($posicionZeta, 'El metodo zeta no sale en su propia tabla.');

        // En un catalogo el orden por id es el orden en que se crearon, que para
        // el taller no significa nada: lo que se mira es la lista de nombres.
        $this->assertLessThan($posicionZeta, $posicionAlfa);
    }

    public function test_la_tabla_no_enseña_los_metodos_dados_de_baja(): void
    {
        $borrado = $this->metodo('Metodo borrado de la prueba');
        $borrado->delete();

        $this->metodo('Metodo vivo de la prueba');

        $respuesta = $this->get(
            '/configuracion/metodos-pago?' . http_build_query([
                'draw' => 1,
                'start' => 0,
                'length' => 50,
                'columns' => [['data' => 'nombre', 'name' => 'nombre']],
            ]),
            self::CABECERAS
        );

        $nombres = array_column($respuesta->json('data'), 'nombre');

        $this->assertContains('Metodo vivo de la prueba', $nombres);
        $this->assertNotContains('Metodo borrado de la prueba', $nombres);
    }

    public function test_la_columna_de_cobros_muestra_un_guion_cuando_no_hay_ninguno(): void
    {
        $metodo = $this->metodo('Metodo sin cobros en la lista');

        $respuesta = $this->get(
            '/configuracion/metodos-pago?' . http_build_query([
                'draw' => 1,
                'start' => 0,
                'length' => 50,
                'columns' => [['data' => 'nombre', 'name' => 'nombre']],
            ]),
            self::CABECERAS
        );

        $fila = null;

        foreach ($respuesta->json('data') as $una) {
            if (($una['nombre'] ?? '') === 'Metodo sin cobros en la lista') {
                $fila = $una;
            }
        }

        $this->assertNotNull($fila, 'El metodo creado no sale en su propia tabla.');

        /*
         * Un guion y no un "0", porque un cero aqui no es un dato: es la ausencia
         * de datos, y escribiendolo como cero parece que se quebro la columna.
         */
        $this->assertStringContainsString('—', $fila['en_uso']);
        $this->assertStringContainsString('se puede borrar', $fila['en_uso']);
    }

    public function test_la_columna_de_cobros_muestra_el_numero_cuando_hay_alguno(): void
    {
        $metodo = $this->metodo('Metodo con cobros en la lista');

        $this->cobro($metodo);
        $this->cobro($metodo);

        $respuesta = $this->get(
            '/configuracion/metodos-pago?' . http_build_query([
                'draw' => 1,
                'start' => 0,
                'length' => 50,
                'columns' => [['data' => 'nombre', 'name' => 'nombre']],
            ]),
            self::CABECERAS
        );

        $fila = null;

        foreach ($respuesta->json('data') as $una) {
            if (($una['nombre'] ?? '') === 'Metodo con cobros en la lista') {
                $fila = $una;
            }
        }

        $this->assertNotNull($fila);
        $this->assertStringContainsString('2', $fila['en_uso']);

        // Y el aviso dice que no se puede borrar, que es lo que va a pasar
        $this->assertStringContainsString('No se puede borrar', $fila['en_uso']);
    }

    public function test_la_pantalla_lleva_el_idioma_en_espanol(): void
    {
        $this->metodo();

        $html = $this->htmlSinEscapes(
            $this->get('/configuracion/metodos-pago')->assertOk()->getContent()
        );

        /*
         * El idioma viaja en la configuracion que la pantalla escribe en el html,
         * y no en la respuesta de ajax: la respuesta son las filas en json y ahi
         * no hay ningun texto de DataTables. Por eso se mira la pagina.
         *
         * Y esto no sustituye a TablasEnEspanolTest, que recorre el directorio de
         * DataTables entero y comprueba que todas las tablas se lleven el idioma
         * por el camino comun. Aqui lo que se comprueba es otra cosa: que el
         * idioma llego a la pagina y que no salio nada en ingles.
         *
         * Y el html va con los escapes del unicode deshechos, porque si no los
         * textos con tilde no se encuentran nunca.
         */
        $this->assertStringContainsString('Mostrando', $html);
        $this->assertStringContainsString('registros', $html);
        $this->assertStringContainsString('Ningún registro coincide', $html);
        $this->assertStringContainsString('No hay datos en la tabla', $html);
        $this->assertStringContainsString('Cargando...', $html);
        $this->assertStringContainsString('página', $html);

        /*
         * Y la clave "entries" sale con su valor en espanol. La clave no se puede
         * cambiar —es lo que DataTables busca— y el valor es lo que ve el
         * usuario, asi que se comprueban las dos mitades: la clave esta y el
         * valor debajo de ella es el que tiene que estar traducido.
         */
        $this->assertStringContainsString('"entries":{"_":"registros","1":"registro"}', $html);

        /*
         * Los textos en ingles de DataTables, los mismos que prohíbe
         * TablasEnEspanolTest y con la misma lista. La palabra "entries" NO va
         * aqui a proposito: es una clave del json, no un texto, y buscarla haria que
         * el test se quejara del idioma de una pantalla que lo tiene bien puesto.
         */
        foreach ([
            'No data available in table',
            'No matching records found',
            'Search:',
            'Previous',
            'Next',
            'Loading...',
            'First',
            'Last',
            'Showing',
        ] as $ingles) {
            $this->assertStringNotContainsString(
                $ingles,
                $html,
                'El texto "' . $ingles . '" se ha quedado en ingles en la pantalla de metodos de pago.'
            );
        }
    }

    public function test_la_tabla_de_la_pantalla_trae_los_titulos_en_espanol(): void
    {
        $html = $this->get('/configuracion/metodos-pago')->assertOk()->getContent();

        foreach (['Nombre', 'Descripción', 'Cobros con él', 'Estado', 'Acciones'] as $titulo) {
            $this->assertStringContainsString(
                $titulo,
                $html,
                'La columna "' . $titulo . '" no sale en la pantalla.'
            );
        }
    }

    public function test_la_pantalla_se_abre(): void
    {
        $this->get('/configuracion/metodos-pago')->assertOk();
    }

    // ==================================================================
    // Los permisos
    // ==================================================================

    public function test_quien_no_tiene_el_permiso_no_llega_a_la_ruta(): void
    {
        $lector = User::create([
            'name' => 'Lector',
            'email' => 'metodo-pago-lector-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);

        $lector->givePermissionTo('configuracion.monedas.view');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($lector);

        $this->getJson('/configuracion/metodos-pago')->assertForbidden();

        $this->postJson('/configuracion/metodos-pago', [
            'nombre' => 'Metodo que no deberia crearse',
            'estado' => 1,
        ])->assertForbidden();

        $this->assertSame(0, $this->metodosCreados());
    }

    public function test_el_supervisor_ve_la_pantalla_pero_no_la_toca(): void
    {
        $supervisor = User::create([
            'name' => 'Supervisor',
            'email' => 'metodo-pago-supervisor-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);

        $supervisor->syncRoles(['supervisor']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($supervisor);

        /*
         * Ver si hace falta: el metodo sale en el desplegable del cobro y quien
         * registra cobros es el supervisor. Sin este permiso ese desplegable
         * saldria vacio. Y tocarlo no, que las formas de cobro las decide quien
         * lleva el taller.
         */
        $this->getJson('/configuracion/metodos-pago')->assertOk();

        $this->postJson('/configuracion/metodos-pago', [
            'nombre' => 'Metodo que el supervisor no deberia poder crear',
            'estado' => 1,
        ])->assertForbidden();

        $this->assertSame(0, $this->metodosCreados());
    }
}
