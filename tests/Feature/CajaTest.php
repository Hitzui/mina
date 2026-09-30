<?php
/**
 * Las cajas, de punta a punta.
 *
 * Lo que se comprueba aqui no es que la pantalla exista, que eso lo hacen otros
 * tests. Son las cuatro cosas que pueden fallar sin que se note, y las cuatro
 * estan escritas en el servidor y no en el formulario porque se pueden saltar
 * desde cualquier lado.
 *
 * Y LO QUE ESTA PANTALLA SI HACE, AL REVES QUE LA DE LOS TIPOS DE INGRESO.
 *
 * Ahi no se anaden filas: los cuatro tipos los puso el taller a mano y la
 * pantalla no decide cuales hay. Aqui SI se crean, y hay un test que lo
 * comprueba. La diferencia no es una mania: la tabla estaba vacia, y una
 * pantalla que solo puede corregir y desactivar filas que no existen se abre y
 * no hace nada. Una caja es un hecho —el taller tiene caja chica o no la tiene—
 * y no una clasificacion del gasto.
 *
 * QUE ESTA EN USO NO SE BORRA, SE DESACTIVA. Si hay cobros en esa caja, borrarla
 * dejaria la FK apuntando a una fila que ya no esta. Y desactivar sirve para lo
 * mismo —sacarla de los desplegables— sin tocar lo que ya se registro con ella.
 *
 * Y SE CUENTAN COMO USO LOS COBROS DADOS DE BAJA TAMBIEN. La FK no distingue: la
 * fila borrada sigue escribiendo el numero. Si aqui se contaran solo los vivos,
 * el boton dejaria pulsar el borrar y reventaria con un error de MySQL en vez de
 * con un aviso que explica. Es el fallo mas probable de esta pantalla, y el
 * motivo de que uno de los tests sea exactamente ese.
 *
 * Y EL NOMBRE PUEDE REPETIRSE, a proposito y en contra de lo que hace el
 * catalogo de monedas. Alli lo unico es el codigo ISO, que por definicion no
 * puede repetirse. Aqui el nombre es libre, igual que en los otros tres
 * catalogos del taller. Este test esta para que esa decision sea visible: si
 * alguien cambia de idea, falla el dia que lo haga y no un dia que alguien haya
 * metido dos "Caja chica".
 *
 * NUNCA SE CUENTA LA TABLA ENTERA. La base es la de verdad y el taller la usa:
 * assertDatabaseCount pasaria por casualidad mientras el taller no tenga cajas,
 * y en cuanto tenga una empezaria a fallar sin que cambie nada.
 */

namespace Tests\Feature;

use App\Models\Cajas;
use App\Models\Cobro;
use App\Models\MetodosPago;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CajaTest extends TestCase
{
    use DatabaseTransactions;

    private const CABECERAS = [
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ];

    /** Cuantas cajas habia antes de que esta prueba empezara. */
    private int $cajasIniciales;

    /** El metodo de pago de la prueba, que se crea una vez y se reusa. */
    private ?int $metodoPagoId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cajasIniciales = Cajas::withTrashed()->count();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'caja-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        $this->actingAs($admin);
    }

    // ==================================================================
    // Ayudas
    // ==================================================================

    /**
     * Una caja de la prueba.
     *
     * El nombre lleva un hash y no un nombre fijo, para que al repetir las
     * pruebas no choquen entre si. Aqui el nombre NO es indice unico —que es
     * justo lo que se esta comprobando—, pero tampoco hace falta que lo sean: lo
     * que importa es distinguir una caja de otra en los avisos.
     */
    private function caja(string $nombre = '', bool $estado = true): Cajas
    {
        return Cajas::create([
            Cajas::NOMBRE => $nombre !== ''
                ? $nombre
                : 'Caja de la prueba ' . strtoupper(substr(md5(uniqid('', true)), 0, 6)),
            Cajas::DESCRIPCION => 'Creada por la prueba',
            Cajas::ESTADO => $estado,
        ]);
    }

    /**
     * Cuantas cajas creo esta prueba.
     *
     * Cuenta tambien las dadas de baja, para que un borrado mal hecho tampoco
     * pase por alto: lo que importa es cuantas filas toco el test.
     */
    private function cajasCreadas(): int
    {
        return Cajas::withTrashed()->count() - $this->cajasIniciales;
    }

    /**
     * El html con los escapes del unicode deshechos.
     *
     * La configuracion que se le pasa a DataTables es un json generado sin
     * JSON_UNESCAPED_UNICODE, con lo que las vocales acentuadas viajan como
     * "u00fa" y no como "a". Buscar en el html tal cual encuentra "Mostrando" y
     * "registros", que no llevan tilde, y no encuentra "Ningun registro
     * coincide" con la u acentuada, que si la lleva: el idioma estaba puesto y
     * bien, y lo que no estaba era puesto el texto que se buscaba.
     *
     * TablasEnEspanolTest hace esto mejor, sacando el bloque del idioma y
     * volcandolo con JSON_UNESCAPED_UNICODE. Aqui no hace falta esa fabrica
     * entera: lo que se comprueba es que la pantalla lleva el idioma, y dejar
     * el html legible es ademas lo que hace util el fallo la proxima vez que
     * esto se rompa.
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
     * Un cobro que apunta a esta caja.
     *
     * Se crea de verdad y no con una llamada a la base, porque el cobro tiene
     * claves foraneas que tambien hay que cumplir: si se metiera a pelo,
     * contariamos filas que en la vida real no podrian existir y el test pasaria
     * por un motivo equivocado.
     */
    private function cobro(Cajas $caja, bool $borrado = false): Cobro
    {
        $cliente = DB::table('clientes')->first();
        $moneda = DB::table('monedas')->where('estado', 1)->first();

        if ($cliente === null || $moneda === null) {
            $this->markTestSkipped('No hay clientes ni monedas para montar un cobro.');
        }

        $cobro = new Cobro();
        $cobro->cliente_id = $cliente->id;
        $cobro->caja_id = $caja->id;
        $cobro->metodo_pago_id = $this->metodoDePago();
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
     * Un metodo de pago de la prueba, y el mismo en todos los cobros del test.
     *
     * Un cobro no se puede guardar sin metodo de pago, y la tabla esta vacia
     * porque el taller todavia no ha dado de alta ninguno. Sin esta fila el
     * insert falla con un "Field 'metodo_pago_id' doesn't have a default value",
     * que no dice nada de cajas y deja los tests de "una caja con cobros no se
     * borra" sin poder ni ejecutarse.
     *
     * Es andamiaje, no lo que se prueba: lo que se prueba es la regla de la caja
     * en uso. Y va dentro de la transaccion del test, que se deshace al terminar,
     * asi que el taller no ve esta fila.
     */
    private function metodoDePago(): int
    {
        if ($this->metodoPagoId !== null) {
            return $this->metodoPagoId;
        }

        $metodo = MetodosPago::create([
            MetodosPago::NOMBRE => 'Metodo de la prueba '
                . strtoupper(substr(md5(uniqid('', true)), 0, 6)),
            MetodosPago::ESTADO => true,
        ]);

        return $this->metodoPagoId = $metodo->id;
    }

    // ==================================================================
    // El alta, que aqui SI existe
    // ==================================================================

    public function test_se_puede_crear_una_caja(): void
    {
        $antes = $this->cajasIniciales;

        $nombre = 'Caja de la prueba ' . strtoupper(substr(md5(uniqid('', true)), 0, 6));

        $respuesta = $this->postJson('/configuracion/cajas', [
            'nombre' => $nombre,
            'descripcion' => 'Efectivo de la recepcion',
            'estado' => 1,
        ]);

        $respuesta->assertOk();

        $this->assertSame($antes + 1, $this->cajasCreadas());

        $caja = Cajas::where('nombre', $nombre)->firstOrFail();

        $this->assertSame('Efectivo de la recepcion', $caja->descripcion);
        $this->assertTrue((bool) $caja->estado);
    }

    public function test_una_caja_nace_activada(): void
    {
        $caja = $this->caja(estado: true);

        $this->assertTrue((bool) $caja->estado);
    }

    public function test_sin_nombre_no_se_crea(): void
    {
        $antes = $this->cajasIniciales;

        $this->postJson('/configuracion/cajas', [
            'nombre' => '',
            'estado' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('nombre');

        $this->assertSame($antes, $this->cajasCreadas());
    }

    public function test_nombre_mas_largo_de_lo_que_cabe_no_se_crea(): void
    {
        $this->postJson('/configuracion/cajas', [
            'nombre' => str_repeat('a', 101),
            'estado' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('nombre');
    }

    public function test_una_descripcion_mas_larga_de_lo_que_cabe_no_se_crea(): void
    {
        $this->postJson('/configuracion/cajas', [
            'nombre' => 'Caja con descripcion larga',
            'descripcion' => str_repeat('a', 256),
            'estado' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('descripcion');
    }

    public function test_la_descripcion_es_opcional(): void
    {
        $this->postJson('/configuracion/cajas', [
            'nombre' => 'Caja sin descripcion',
            'descripcion' => null,
            'estado' => 1,
        ])->assertOk();

        $this->assertNull(Cajas::where('nombre', 'Caja sin descripcion')->firstOrFail()->descripcion);
    }

    public function test_una_descripcion_vacia_se_guarda_como_vacio_y_no_como_texto(): void
    {
        $this->postJson('/configuracion/cajas', [
            'nombre' => 'Caja con descripcion en blanco',
            'descripcion' => '',
            'estado' => 1,
        ])->assertOk();

        $caja = Cajas::where('nombre', 'Caja con descripcion en blanco')->firstOrFail();

        /*
         * Sin esto se guardaria "" en vez de no guardar nada, y en la lista sale
         * un guion y en la ficha una cadena invisible. Y "que no vino nada" no es
         * lo mismo que "vino vacio": un campo que el usuario ni ha tocado no
         * llega en la peticion.
         */
        $this->assertNull($caja->descripcion);
    }

    public function test_el_estado_solo_acepta_cero_y_uno(): void
    {
        $this->postJson('/configuracion/cajas', [
            'nombre' => 'Caja con estado raro',
            'estado' => 7,
        ])->assertStatus(422)->assertJsonValidationErrors('estado');
    }

    // ==================================================================
    // El nombre puede repetirse
    // ==================================================================

    public function test_el_nombre_puede_repetirse(): void
    {
        $primera = $this->caja('Caja chica');
        $segunda = $this->caja('Caja chica');

        $this->assertNotSame($primera->id, $segunda->id);
        $this->assertSame('Caja chica', $segunda->nombre);
    }

    public function test_dos_cajas_con_el_mismo_nombre_se_gestan_por_separado(): void
    {
        $conCobros = $this->caja('Caja chica');
        $sinCobros = $this->caja('Caja chica');

        $this->cobro($conCobros);

        // La que tiene cobros no se puede borrar
        $this->deleteJson("/configuracion/cajas/{$conCobros->id}")->assertStatus(422);

        // La otra si, y son dos filas distintas
        $this->deleteJson("/configuracion/cajas/{$sinCobros->id}")->assertOk();

        $this->assertSoftDeleted('cajas', ['id' => $sinCobros->id]);
        $this->assertDatabaseHas('cajas', [
            'id' => $conCobros->id,
            'deleted_at' => null,
        ]);
    }

    // ==================================================================
    // Editar
    // ==================================================================

    public function test_se_puede_editar_una_caja(): void
    {
        $caja = $this->caja('Nombre viejo');

        $this->putJson("/configuracion/cajas/{$caja->id}", [
            'nombre' => 'Nombre nuevo',
            'descripcion' => 'La nueva descripcion',
            'estado' => 1,
        ])->assertOk();

        $caja->refresh();

        $this->assertSame('Nombre nuevo', $caja->nombre);
        $this->assertSame('La nueva descripcion', $caja->descripcion);
    }

    public function test_editar_no_crea_una_caja_nueva(): void
    {
        $antes = $this->cajasIniciales;
        $caja = $this->caja();

        $this->putJson("/configuracion/cajas/{$caja->id}", [
            'nombre' => 'Otro nombre',
            'estado' => 1,
        ])->assertOk();

        $this->assertSame($antes + 1, $this->cajasCreadas());
    }

    public function test_no_se_puede_editar_sin_nombre(): void
    {
        $caja = $this->caja('Nombre que se queda');

        $this->putJson("/configuracion/cajas/{$caja->id}", [
            'nombre' => '',
            'estado' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('nombre');

        $caja->refresh();

        $this->assertSame('Nombre que se queda', $caja->nombre);
    }

    // ==================================================================
    // Activar y desactivar
    // ==================================================================

    public function test_se_puede_desactivar_y_volver_a_activar(): void
    {
        $caja = $this->caja();

        $this->postJson("/configuracion/cajas/{$caja->id}/cambiar-estado")->assertOk();

        $caja->refresh();
        $this->assertFalse((bool) $caja->estado);

        $this->postJson("/configuracion/cajas/{$caja->id}/cambiar-estado")->assertOk();

        $caja->refresh();
        $this->assertTrue((bool) $caja->estado);
    }

    public function test_se_puede_desactivar_una_caja_que_ya_tiene_cobros(): void
    {
        $caja = $this->caja();
        $cobro = $this->cobro($caja);

        /*
         * Desactivar una caja en uso es justamente lo que se quiere hacer: es el
         * otro camino del aviso de "no se puede borrar". Por eso este boton no
         * se apaga cuando hay cobros.
         */
        $this->postJson("/configuracion/cajas/{$caja->id}/cambiar-estado")->assertOk();

        $caja->refresh();

        $this->assertFalse((bool) $caja->estado);
        $this->assertDatabaseHas('cobros', ['id' => $cobro->id, 'deleted_at' => null]);
    }

    public function test_una_caja_desactivada_sale_de_los_registrables_pero_no_se_borra(): void
    {
        $caja = $this->caja();

        $caja->update([Cajas::ESTADO => false]);

        $registrables = Cajas::paraRegistrar()->pluck('id');

        $this->assertNotContains($caja->id, $registrables->all());

        // Y sigue estando: desactivar no es borrar
        $this->assertDatabaseHas('cajas', [
            'id' => $caja->id,
            'deleted_at' => null,
        ]);
    }

    // ==================================================================
    // Borrar
    // ==================================================================

    public function test_una_caja_sin_cobros_se_borra(): void
    {
        $caja = $this->caja();

        $this->deleteJson("/configuracion/cajas/{$caja->id}")->assertOk();

        $this->assertSoftDeleted('cajas', ['id' => $caja->id]);
    }

    public function test_una_caja_con_cobros_no_se_borra(): void
    {
        $caja = $this->caja();
        $this->cobro($caja);

        $respuesta = $this->deleteJson("/configuracion/cajas/{$caja->id}");

        $respuesta->assertStatus(422)->assertJsonValidationErrors(Cajas::NOMBRE);

        $this->assertDatabaseHas('cajas', [
            'id' => $caja->id,
            'deleted_at' => null,
        ]);
    }

    public function test_el_aviso_de_no_se_puede_borrar_dice_donde_esta_en_uso(): void
    {
        $caja = $this->caja();
        $this->cobro($caja);

        $respuesta = $this->deleteJson("/configuracion/cajas/{$caja->id}");

        $mensaje = $respuesta->json('errors.' . Cajas::NOMBRE . '.0');

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
        $caja = $this->caja();

        $cobro = $this->cobro($caja, borrado: true);

        $this->assertNotNull($cobro->fresh()->trashed());

        /*
         * Esto es el fallo mas probable de la pantalla y por eso tiene su propio
         * test. La FK no distingue: la fila borrada sigue escribiendo el numero,
         * y el valor por defecto de MySQL es restringir, o sea que borrar la
         * caja con un cobro dado de baja tambien lo impide. Si aqui se contaran
         * solo los vivos, el boton dejaria pulsar el borrar y reventaria con un
         * error de MySQL en vez de con un aviso que explica.
         */
        $this->deleteJson("/configuracion/cajas/{$caja->id}")->assertStatus(422);

        $this->assertDatabaseHas('cajas', [
            'id' => $caja->id,
            'deleted_at' => null,
        ]);
    }

    public function test_el_aviso_dice_cuantos_cobros_dados_de_baja_hay(): void
    {
        $caja = $this->caja();

        $this->cobro($caja);
        $this->cobro($caja, borrado: true);

        $respuesta = $this->deleteJson("/configuracion/cajas/{$caja->id}");

        $mensaje = $respuesta->json('errors.' . Cajas::NOMBRE . '.0');

        /*
         * Si el usuario ve "en 2 cobros" y despues borra uno, no va a entender
         * por que le sigue dejando el mismo numero. El matiz va escrito.
         */
        $this->assertStringContainsString('dado de baja', $mensaje);
    }

    public function test_borrar_y_volver_a_meter_una_caja_funciona(): void
    {
        $nombre = 'Caja que se borra y vuelve';

        $caja = $this->caja($nombre);
        $this->deleteJson("/configuracion/cajas/{$caja->id}")->assertOk();

        $otra = $this->caja($nombre);

        $this->assertNotSame($caja->id, $otra->id);
        $this->assertNull($otra->fresh()->deleted_at);
    }

    // ==================================================================
    // La ficha
    // ==================================================================

    public function test_la_ficha_trae_los_datos_y_donde_se_usa(): void
    {
        $caja = $this->caja('Caja de la ficha');
        $cobro = $this->cobro($caja);

        $respuesta = $this->getJson("/configuracion/cajas/{$caja->id}", self::CABECERAS);

        $respuesta->assertOk();

        $datos = $respuesta->json();

        $this->assertSame($caja->id, $datos['id']);
        $this->assertSame('Caja de la ficha', $datos['nombre']);
        $this->assertTrue($datos['estado']);
        $this->assertFalse($datos['se_puede_borrar']);
        $this->assertSame(1, $datos['usos'][0]['cuantas']);
        $this->assertStringContainsString('cobro', $datos['frase_de_usos']);
    }

    public function test_la_ficha_de_una_caja_libre_dice_que_se_puede_borrar(): void
    {
        $caja = $this->caja('Caja sin cobros');

        $datos = $this->getJson("/configuracion/cajas/{$caja->id}", self::CABECERAS)->json();

        $this->assertTrue($datos['se_puede_borrar']);
        $this->assertSame([], $datos['usos']);
        $this->assertSame('', $datos['frase_de_usos']);
    }

    public function test_la_frase_de_usos_pone_el_singular_y_el_plural(): void
    {
        $una = $this->caja();
        $this->cobro($una);

        // "1 cobro" y no "1 cobros", que es el fallo clasico de estos avisos
        $this->assertSame('1 cobro', $una->fraseDeUsos());

        $varias = $this->caja();
        $this->cobro($varias);
        $this->cobro($varias);

        $this->assertSame('2 cobros', $varias->fraseDeUsos());
    }

    // ==================================================================
    // La tabla
    // ==================================================================

    public function test_la_tabla_devuelve_las_cajas_por_nombre(): void
    {
        $this->caja('Caja zeta de la prueba');
        $this->caja('Caja alfa de la prueba');

        $respuesta = $this->get(
            '/configuracion/cajas?' . http_build_query([
                'draw' => 1,
                'start' => 0,
                'length' => 50,
                'columns' => [['data' => 'nombre', 'name' => 'nombre']],
            ]),
            self::CABECERAS
        );

        $respuesta->assertOk();

        $nombres = array_column($respuesta->json('data'), 'nombre');

        // En un catalogo el orden por id es el orden en que se crearon, que para
        // el taller no significa nada: lo que se mira es la lista de nombres.
        $this->assertSame('Caja alfa de la prueba', $nombres[0]);
        $this->assertSame('Caja zeta de la prueba', $nombres[1]);
    }

    public function test_la_tabla_no_enseña_las_cajas_dadas_de_baja(): void
    {
        $borrada = $this->caja('Caja borrada de la prueba');
        $borrada->delete();

        $this->caja('Caja viva de la prueba');

        $respuesta = $this->get(
            '/configuracion/cajas?' . http_build_query([
                'draw' => 1,
                'start' => 0,
                'length' => 50,
                'columns' => [['data' => 'nombre', 'name' => 'nombre']],
            ]),
            self::CABECERAS
        );

        $nombres = array_column($respuesta->json('data'), 'nombre');

        $this->assertContains('Caja viva de la prueba', $nombres);
        $this->assertNotContains('Caja borrada de la prueba', $nombres);
    }

    public function test_la_columna_de_cobros_muestra_un_guion_cuando_no_hay_ninguno(): void
    {
        $caja = $this->caja('Caja sin cobros en la lista');

        $respuesta = $this->get(
            '/configuracion/cajas?' . http_build_query([
                'draw' => 1,
                'start' => 0,
                'length' => 50,
                'columns' => [['data' => 'nombre', 'name' => 'nombre']],
            ]),
            self::CABECERAS
        );

        $fila = null;

        foreach ($respuesta->json('data') as $una) {
            if (($una['nombre'] ?? '') === 'Caja sin cobros en la lista') {
                $fila = $una;
            }
        }

        $this->assertNotNull($fila, 'La caja creada no sale en su propia tabla.');

        /*
         * Un guion y no un "0", porque un cero aqui no es un dato: es la
         * ausencia de datos, y escribiendolo como cero parece que se quebro la
         * columna.
         */
        $this->assertStringContainsString('—', $fila['en_uso']);
        $this->assertStringContainsString('se puede borrar', $fila['en_uso']);
    }

    public function test_la_columna_de_cobros_muestra_el_numero_cuando_hay_alguno(): void
    {
        $caja = $this->caja('Caja con cobros en la lista');

        $this->cobro($caja);
        $this->cobro($caja);

        $respuesta = $this->get(
            '/configuracion/cajas?' . http_build_query([
                'draw' => 1,
                'start' => 0,
                'length' => 50,
                'columns' => [['data' => 'nombre', 'name' => 'nombre']],
            ]),
            self::CABECERAS
        );

        $fila = null;

        foreach ($respuesta->json('data') as $una) {
            if (($una['nombre'] ?? '') === 'Caja con cobros en la lista') {
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
        $this->caja();

        $html = $this->htmlSinEscapes(
            $this->get('/configuracion/cajas')->assertOk()->getContent()
        );

        /*
         * El idioma viaja en la configuracion que la pantalla escribe en el
         * html, y no en la respuesta de ajax: la respuesta son las filas en json
         * y ahi no hay ningun texto de DataTables. Por eso se mira la pagina.
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
         * Y la clave "entries" sale con su valor en espanol. La clave no se
         * puede cambiar —es lo que DataTables busca— y el valor es lo que ve el
         * usuario, asi que se comprueban las dos mitades: la clave esta y el
         * valor debajo de ella es el que tiene que estar traducido.
         */
        $this->assertStringContainsString('"entries":{"_":"registros","1":"registro"}', $html);

        /*
         * Los textos en ingles de DataTables, los mismos que prohíbe
         * TablasEnEspanolTest y con la misma lista. La palabra "entries" NO va
         * aqui a proposito: es una clave del json, no un texto, y buscarla
         * hacia que el test se quejara del idioma de una pantalla que lo tiene
         * bien puesto.
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
                'El texto "' . $ingles . '" se ha quedado en ingles en la pantalla de cajas.'
            );
        }
    }

    public function test_la_tabla_de_la_pantalla_trae_los_titulos_en_espanol(): void
    {
        $html = $this->get('/configuracion/cajas')->assertOk()->getContent();

        foreach (['Nombre', 'Descripción', 'Cobros en ella', 'Estado', 'Acciones'] as $titulo) {
            $this->assertStringContainsString(
                $titulo,
                $html,
                'La columna "' . $titulo . '" no sale en la pantalla.'
            );
        }
    }

    public function test_la_pantalla_se_abre(): void
    {
        $this->get('/configuracion/cajas')->assertOk();
    }

    // ==================================================================
    // Los permisos
    // ==================================================================

    public function test_quien_no_tiene_el_permiso_no_llega_a_la_ruta(): void
    {
        $lector = User::create([
            'name' => 'Lector',
            'email' => 'caja-lector-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);

        $lector->givePermissionTo('configuracion.monedas.view');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($lector);

        $antes = $this->cajasIniciales;

        $this->getJson('/configuracion/cajas')->assertForbidden();

        $this->postJson('/configuracion/cajas', [
            'nombre' => 'Caja que no deberia crearse',
            'estado' => 1,
        ])->assertForbidden();

        $this->assertSame($antes, $this->cajasCreadas());
    }
}
