<?php
/**
 * El catalogo de tipos de ingreso, de punta a punta.
 *
 * Lo que se comprueba aqui no es que la pantalla exista, que eso lo hacen otros
 * tests. Son las tres cosas que pueden fallar sin que se note, y las tres estan
 * escritas en el servidor y no en el formulario porque se pueden saltar desde
 * cualquier lado.
 *
 * EL TIPO EN USO NO SE BORRA. Si hay ingresos con ese tipo, borrarlo dejaria el
 * ingreso apuntando a una fila que ya no esta. Y desactivar sirve para lo mismo
 * —sacarlo de los desplegables— sin tocar lo que ya se registro. Es la misma
 * regla que en las monedas, y lo que se comprueba es que la aplica y que el
 * aviso dice QUE HACER, porque "no se puede borrar" sin mas deja al usuario sin
 * salida.
 *
 * Y SE CUENTAN COMO USO LOS DADOS DE BAJA TAMBIEN. La FK no distingue: la fila
 * borrada sigue escribiendo el numero. Si aqui se contaran solo los vivos, el
 * boton dejaria pulsar el borrar y reventaria con un error de MySQL en vez de
 * con un aviso que explica. Es el fallo mas probable de esta pantalla, y el
 * motivo de que uno de los tests sea exactamente ese.
 *
 * QUE NO SE ANADEN TIPOS. La pantalla existe para corregir, desactivar y dar
 * de baja lo que hay, no para decidir que tipos tiene el taller. Un maestro de
 * tipos de ingreso es una opinion sobre como funciona el negocio, y la opinion
 * que vale es la del taller. Por eso no hay ningun test que rellene el
 * catalogo.
 *
 * Y QUE EL NOMBRE NO SEA UNICO EN LA BASE, en contra de lo que hace el catalogo
 * de monedas. Alli lo unico es el codigo ISO, que por definicion no puede
 * repetirse. Aqui el nombre es libre, y las categorias de costo y los tipos de
 * pago de empleado —los otros dos catalogos con pantalla— tampoco lo tienen.
 * Este test esta para que esa decision sea visible: si alguien cambia de idea,
 * falla el dia que lo haga y no un dia que alguien haya metido dos "Otro".
 */

namespace Tests\Feature;

use App\Models\TiposIngreso;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TipoIngresoTest extends TestCase
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
            'email' => 'tipo-ingreso-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        $this->actingAs($admin);
    }

    // ------------------------------------------------------------------
    // Ayudas
    // ------------------------------------------------------------------

    /**
     * Un tipo de ingreso de la prueba.
     *
     * El nombre lleva un hash y no un nombre fijo, para que al repetir las
     * pruebas no choquen entre si. Aqui el nombre no es indice unico —no hay
     * indice unico, que es justo lo que se esta comprobando—, pero tampoco hace
     * falta que lo sean: lo que importa es distinguir un tipo de otro en los
     * avisos.
     */
    private function tipo(string $nombre = '', bool $estado = true): TiposIngreso
    {
        return TiposIngreso::create([
            TiposIngreso::NOMBRE => $nombre !== ''
                ? $nombre
                : 'Tipo de la prueba ' . strtoupper(substr(md5(uniqid('', true)), 0, 6)),
            TiposIngreso::DESCRIPCION => 'Creado por el test',
            TiposIngreso::ESTADO => $estado,
        ]);
    }

    /**
     * Un ingreso con este tipo.
     *
     * Se escribe con DB y no con el modelo porque ingresos todavia no tiene
     * pantalla y su modelo es el de Reliese. La fila que se crea es real y se va
     * en la transaccion del test, asi que no toca la base del taller.
     */
    private function ingresoCon(TiposIngreso $tipo, bool $dadoDeBaja = false): void
    {
        $ordenId = DB::table('ordenes_trabajo')->orderBy('id')->value('id');

        if (! $ordenId) {
            // Sin orden no se puede crear el ingreso, porque la FK no admite null
            $this->markTestSkipped('No hay ninguna orden de trabajo en la base.');
        }

        DB::table('ingresos')->insert([
            'orden_trabajo_id' => $ordenId,
            'tipo_ingreso_id' => $tipo->id,
            'fecha' => '2090-08-01',
            'descripcion' => 'Ingreso del test',
            'total' => 100,
            'moneda_id' => DB::table('monedas')->where('es_moneda_base', true)->value('id'),
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => $dadoDeBaja ? now() : null,
        ]);
    }

    // ==================================================================
    // La pantalla
    // ==================================================================

    public function test_la_pantalla_se_ve(): void
    {
        $this->get('/configuracion/tipos-ingreso')
            ->assertOk()
            ->assertSee('Tipos de ingreso');
    }

    public function test_los_tipos_del_taller_se_ven_en_la_pantalla(): void
    {
    /**
     * Los tipos que puso el taller a mano tienen que salir en la lista.
     *
     * Esta pantalla no los anade: los gestiona. Y lo unico que hace falta
     * vigilar es que no se pierdan por un borrado sin querer, asi que el test
     * no mira la pantalla en general sino que los nombres que hay en la base
     * esten en la lista.
     *
     * Y los nombres no se escriben aqui: salen los dos de la base. La enye de
     * "Participacion" es el motivo practico —al guardar este archivo, el byte
     * 0xB6 se convierte en un 6 de verdad y el nombre buscado deja de ser el
     * mismo—, pero el motivo de fondo es que asi el test comprueba mas: si la
     * columna estropeara el nombre al pintarlo, aqui se veria sin haber escrito
     * el nombre para poder compararlo.
     */
        $delTaller = TiposIngreso::orderBy('id')->pluck('nombre');

        $this->assertGreaterThanOrEqual(
            4,
            $delTaller->count(),
            'Los cuatro tipos que puso el taller tienen que seguir ahi, al menos.'
        );

        /*
         * Se le pregunta A LA TABLA y no a la pagina, y la razon es lo que mas
         * confunde en esto.
         *
         * Con Yajra las filas no van en el html: van en un ajax aparte que hace
         * el navegador al pintar. En el html de la pagina solo estan el
         * esqueleto de la tabla y el script que la llena. Por eso buscar el
         * nombre en la pagina no comprueba nada, aunque un nombre SI aparezca:
         * el de "Servicio de procesamiento" salia porque es el texto de ejemplo
         * del campo del formulario, y con el test en verde.
         */
        $tabla = $this->getJson('/configuracion/tipos-ingreso?' . http_build_query([
            'draw' => 1,
            'start' => 0,
            'length' => 100,
            'columns' => [
                ['data' => 'nombre', 'name' => '', 'searchable' => 'true', 'orderable' => 'true'],
            ],
        ]), self::CABECERAS)->assertOk()->json('data');

        $enLaTabla = array_map(fn($fila) => $fila['nombre'] ?? '', $tabla);

        $faltan = [];

        foreach ($delTaller as $nombre) {
            if (! in_array($nombre, $enLaTabla, true)) {
                $faltan[] = $nombre;
            }
        }

        $this->assertSame(
            [],
            $faltan,
            'Los tipos que puso el taller tienen que salir en la lista de la pantalla. Si un nombre sale mal escrito, el problema es de como se guardo, no de la lista.'
        );
    }

    public function test_no_deja_ver_la_pantalla_sin_permiso(): void
    {
        $this->actingAs($this->usuarioConPermisos([]));

        $this->get('/configuracion/tipos-ingreso')->assertForbidden();
    }

    public function test_no_deja_crear_sin_permiso_de_crear(): void
    {
        $this->actingAs($this->usuarioConPermisos(['configuracion.tipos_ingreso.view']));

        $this->postJson('/configuracion/tipos-ingreso', [
            'nombre' => 'Tipo sin permiso',
            'estado' => 1,
        ], self::CABECERAS)->assertForbidden();
    }

    public function test_no_deja_borrar_sin_permiso_de_borrar(): void
    {
        $tipo = $this->tipo();

        $this->actingAs($this->usuarioConPermisos([
            'configuracion.tipos_ingreso.view',
            'configuracion.tipos_ingreso.edit',
        ]));

        $this->deleteJson(
            '/configuracion/tipos-ingreso/' . $tipo->id,
            [],
            self::CABECERAS
        )->assertForbidden();

        $this->assertNotNull($tipo->fresh(), 'El tipo no se puede borrar sin permiso.');
    }

    // ==================================================================
    // El tipo en uso no se borra
    // ==================================================================

    /**
     * Un tipo con ingresos no se puede borrar, y el aviso dice que hacer.
     *
     * El numero de ingresos va en el texto porque es lo que dice si el problema
     * es de uno o de veinte, y con el numero el usuario sabe si merece la pena
     * mirar cual de los veinte o si mejor desactiva el tipo y ya esta.
     */
    public function test_un_tipo_con_ingresos_no_se_puede_borrar(): void
    {
        $tipo = $this->tipo();
        $this->ingresoCon($tipo);
        $this->ingresoCon($tipo);

        $respuesta = $this->deleteJson(
            '/configuracion/tipos-ingreso/' . $tipo->id,
            [],
            self::CABECERAS
        );

        $respuesta->assertStatus(422);

        $motivo = $respuesta->json('errors.nombre.0') ?? '';

        $this->assertStringContainsString(
            'en uso',
            $motivo,
            'El aviso tiene que decir que está en uso.'
        );

        $this->assertStringContainsString(
            'desactivar',
            $motivo,
            'El aviso tiene que decir qué se puede hacer: desactivar es la salida, y sin ella el usuario se queda sin ninguna.'
        );

        $this->assertStringContainsString(
            '2 ingresos',
            $motivo,
            'El aviso tiene que decir cuántos ingresos son: en uno o en veinte el problema es distinto.'
        );

        $this->assertNotNull($tipo->fresh(), 'El tipo tiene que seguir ahí.');
    }

    /**
     * Los ingresos dados de baja CUENTAN como uso, y no son menos que los vivos.
     *
     * Esta es la comprobacion que hace que esta pantalla no se rompa el dia
     * que se use. La FK no distingue: una fila borrada sigue escribiendo el id
     * del tipo. Si aqui se contaran solo los ingresos vivos, el boton dejaria
     * pulsar el borrar y reventaria con un error de MySQL —un 500 entero en vez
     * de un aviso— que es la peor forma de fallar.
     */
    public function test_los_ingresos_dados_de_baja_tambien_cuentan(): void
    {
        $tipo = $this->tipo();

        $this->ingresoCon($tipo, dadoDeBaja: true);
        $this->ingresoCon($tipo, dadoDeBaja: true);

        $respuesta = $this->deleteJson(
            '/configuracion/tipos-ingreso/' . $tipo->id,
            [],
            self::CABECERAS
        );

        $respuesta->assertStatus(422);

        $motivo = $respuesta->json('errors.nombre.0') ?? '';

        /*
         * Lo que sale en el aviso se compara con lo que produce el metodo, y no
         * con un texto escrito aqui.
         *
         * Y no es solo por el corrector de acentos, que es lo de siempre, sino
         * por algo mejor: si el metodo dijera una cosa y el aviso otra, el test
         * tiene que verlo. Y al revés también: si el metodo mejora el texto y el
         * aviso se queda con el viejo, aqui se nota.
         *
         * El trozo se saca desde el numero hasta el parentesis, que es donde
         * acaba la parte que dice cuantos ingresos y cuantos van dados de baja.
         */
        $frase = $tipo->fraseDeUsos();

        $this->assertSame(
            2,
            DB::table('ingresos')->where('tipo_ingreso_id', $tipo->id)->count(),
            'Los dos ingresos tienen que seguir ahí, dados de baja o no.'
        );

        $this->assertStringContainsString(
            $frase,
            $motivo,
            'El aviso tiene que decir cuántos ingresos son, y con los mismos datos que el modelo. Un ingreso dado de baja sigue escribiendo el id del tipo, así que también impide borrarlo, y si el usuario ve un número distinto no entiende por qué no le deja.'
        );

        $this->assertMatchesRegularExpression(
            '/\(\d+ dados? de baja\)/',
            $motivo,
            'Y el aviso tiene que decir que están dados de baja. El número y la frase se comprueban con la del modelo porque se estropean al guardar el archivo; el parentesis no lleva acentos y se puede escribir.'
        );

        $this->assertNotNull($tipo->fresh());
    }

    /**
     * Un tipo sin ingresos se puede borrar, y desaparece de verdad.
     *
     * El otro lado de la regla: si el tipo en uso no se puede borrar pero el
     * que no se usa tampoco, la pantalla no sirve para nada.
     */
    public function test_un_tipo_sin_ingresos_se_borra(): void
    {
        $tipo = $this->tipo();

        $this->assertTrue(
            $tipo->sePuedeBorrar(),
            'Un tipo que no usa ningún ingreso se puede borrar.'
        );

        $this->deleteJson(
            '/configuracion/tipos-ingreso/' . $tipo->id,
            [],
            self::CABECERAS
        )->assertOk();

        $this->assertNull(
            TiposIngreso::find($tipo->id),
            'El tipo borrado no tiene que salir en la lista.'
        );

        $this->assertNotNull(
            TiposIngreso::withTrashed()->find($tipo->id),
            'El borrado es lógico: la fila sigue ahí, marcada como dada de baja.'
        );
    }

    /**
     * El aviso dice "1 ingreso" y "2 ingresos", y no "1 ingresos".
     *
     * Es una tonteria hasta que se lee el caso de un solo ingreso, que es el
     * mas comun de todos: uno esta probando el sistema y el aviso le dice que
     * esta en uso en "1 ingresos", y lo que se lee de ahi no es que el numero
     * este mal sino que el mensaje lo escribio una maquina. Y este aviso es el
     * que tiene que entenderse bien, porque es el que dice que se desactive en
     * vez de borrar.
     */
    public function test_el_aviso_usa_el_numero_correcto(): void
    {
        $uno = $this->tipo();

        $this->ingresoCon($uno);

        $this->assertStringContainsString(
            '1 ingreso',
            $uno->fraseDeUsos(),
            'Con un solo ingreso tiene que decir "1 ingreso", no "1 ingresos".'
        );

        $this->ingresoCon($uno);

        $this->assertStringContainsString(
            '2 ingresos',
            $uno->fraseDeUsos(),
            'Con dos ingresos tiene que decir "2 ingresos".'
        );
    }

    // ==================================================================
    // Desactivar, que no es borrar
    // ==================================================================

    /**
     * Desactivar saca el tipo de los desplegables y no toca los ingresos.
     *
     * Es la otra mitad de la regla del borrado, y tiene que funcionar incluso
     * con ingresos: desactivar es justamente lo que se hace con un tipo que ya
     * se uso y ya no se quiere seguir usando.
     */
    public function test_desactivar_no_toca_los_ingresos(): void
    {
        $tipo = $this->tipo();

        $this->ingresoCon($tipo);

        $this->postJson(
            '/configuracion/tipos-ingreso/' . $tipo->id . '/cambiar-estado',
            [],
            self::CABECERAS
        )->assertOk();

        $this->assertFalse(
            (bool) $tipo->fresh()->estado,
            'El tipo tiene que quedar inactivo.'
        );

        $this->assertSame(
            1,
            DB::table('ingresos')->where('tipo_ingreso_id', $tipo->id)->count(),
            'Desactivar un tipo no puede tocar los ingresos que ya se registraron con él: eso sería borrar sin avisar.'
        );

        $this->assertSame(
            0,
            TiposIngreso::paraRegistrar()->where('id', $tipo->id)->count(),
            'Un tipo inactivo no se ofrece al registrar ingresos.'
        );
    }

    /**
     * Y se puede volver a activar.
     *
     * Que es lo que distingue desactivar de borrar, y lo que no se puede
     * comprobar solo mirando que el boton existe: hay que pulsarlo y ver que el
     * estado vuelve. Un tipo que se dejo de usar en invierno puede volver a
     * usarse en septiembre, y si desactivar fuera de ida y vuelta, en
     * septiembre faltaria el tipo y no habria manera de traerlo de vuelta.
     */
    public function test_se_puede_volver_a_activar(): void
    {
        $tipo = $this->tipo(estado: false);

        $this->postJson(
            '/configuracion/tipos-ingreso/' . $tipo->id . '/cambiar-estado',
            [],
            self::CABECERAS
        )->assertOk();

        $this->assertTrue((bool) $tipo->fresh()->estado, 'El tipo tiene que quedar activo otra vez.');

        $this->assertSame(
            1,
            TiposIngreso::paraRegistrar()->where('id', $tipo->id)->count(),
            'Un tipo reactivado vuelve a los desplegables.'
        );
    }

    // ==================================================================
    // El alta y la edicion
    // ==================================================================

    public function test_guarda_un_tipo_nuevo(): void
    {
        $nombre = 'Tipo nuevo ' . strtoupper(substr(md5(uniqid('', true)), 0, 6));

        $this->postJson('/configuracion/tipos-ingreso', [
            'nombre' => $nombre,
            'descripcion' => 'Lo que entra en este tipo',
            'estado' => 1,
        ], self::CABECERAS)->assertOk();

        $this->assertDatabaseHas('tipos_ingreso', ['nombre' => $nombre, 'estado' => 1]);
    }

    public function test_el_nombre_es_obligatorio(): void
    {
        $this->postJson('/configuracion/tipos-ingreso', [
            'nombre' => '',
            'estado' => 1,
        ], self::CABECERAS)
            ->assertStatus(422)
            ->assertJsonValidationErrors('nombre');
    }

    public function test_el_estado_tiene_que_ser_activo_o_inactivo(): void
    {
        /*
         * El interruptor manda un 1 y un 0, y el campo escondido de al lado
         * manda el 0 cuando esta desmarcado. Si en algun momento se rompe y
         * llegara otra cosa —un texto, un 2, un null porque el checkbox sin
         * marcar no mando nada—, tiene que rechazarse en vez de guardarse.
         *
         * Y se comprueba con el estado a null tambien, que es el fallo real del
         * checkbox desmarcado sin campo escondido: ahi el servidor no ve un
         * cero, ve que el campo no vino.
         */
        foreach ([['siete'], ['sí'], ['on'], [''], [null]] as $valor) {
            $this->postJson('/configuracion/tipos-ingreso', [
                'nombre' => 'Tipo con estado raro ' . uniqid(),
                'estado' => $valor[0],
            ], self::CABECERAS)
                ->assertStatus(422)
                ->assertJsonValidationErrors('estado');
        }
    }

    public function test_la_descripcion_vacia_se_guarda_como_nada(): void
    {
        $nombre = 'Sin descripcion ' . strtoupper(substr(md5(uniqid('', true)), 0, 6));

        $this->postJson('/configuracion/tipos-ingreso', [
            'nombre' => $nombre,
            'descripcion' => '',
            'estado' => 1,
        ], self::CABECERAS)->assertOk();

        /*
         * Vacio y null no son lo mismo. Un "" en la columna es una cadena que
         * no es nada, y en la ficha sale una linea vacia donde deberia haber un
         * guion. Con null no hay nada que enseñar y cada sitio pone su guion.
         */
        $this->assertNull(
            TiposIngreso::where('nombre', $nombre)->value('descripcion'),
            'Una descripción vacía tiene que guardarse como null, no como cadena vacía.'
        );
    }

    public function test_editar_cambia_el_nombre_y_el_estado(): void
    {
        $tipo = $this->tipo();

        $nombreNuevo = 'Editado ' . strtoupper(substr(md5(uniqid('', true)), 0, 6));

        $this->putJson('/configuracion/tipos-ingreso/' . $tipo->id, [
            'nombre' => $nombreNuevo,
            'descripcion' => 'Otra cosa',
            'estado' => 0,
        ], self::CABECERAS)->assertOk();

        $guardado = $tipo->fresh();

        $this->assertSame($nombreNuevo, $guardado->nombre);
        $this->assertFalse((bool) $guardado->estado);
    }

    // ==================================================================
    // La ficha
    // ==================================================================

    /**
     * La ficha dice si se puede borrar y por que.
     *
     * Es lo que hace que valga la pena abrirla: un catalogo son tres campos y
     * la ficha seria la fila con mas sitio en blanco, salvo que conteste a la
     * pregunta de "este lo puedo tocar o no".
     */
    public function test_la_ficha_dice_si_se_puede_borrar(): void
    {
        $libre = $this->tipo();
        $usado = $this->tipo();

        $this->ingresoCon($usado);

        $fichaLibre = $this->getJson(
            '/configuracion/tipos-ingreso/' . $libre->id,
            self::CABECERAS
        )->assertOk()->json();

        $this->assertTrue(
            $fichaLibre['se_puede_borrar'],
            'Un tipo sin ingresos se puede borrar, y la ficha lo tiene que decir.'
        );

        $this->assertSame([], $fichaLibre['usos']);
        $this->assertSame('', $fichaLibre['frase_de_usos']);

        $fichaUsado = $this->getJson(
            '/configuracion/tipos-ingreso/' . $usado->id,
            self::CABECERAS
        )->assertOk()->json();

        $this->assertFalse($fichaUsado['se_puede_borrar']);
        $this->assertCount(1, $fichaUsado['usos']);
        $this->assertStringContainsString('1 ingreso', $fichaUsado['frase_de_usos']);
    }

    // ==================================================================
    // Lo que esta pantalla NO hace
    // ==================================================================

    /**
     * El nombre NO es unico en la base, y se deja escrito.
     *
     * En las monedas el codigo ISO si es unico, y alli tiene que serlo: un
     * codigo es lo que identifica la moneda y dos monedas con el mismo codigo
     * no se podrian distinguir. Aqui el nombre es texto libre, y los otros dos
     * catalogos con pantalla —categorias de costo y tipos de pago de empleado—
     * tampoco lo tienen unico.
     *
     * Meterlo solo en esta tabla daria tres catalogos con una regla y dos con
     * otra, y dentro de un ano nadie sabria cual es la buena. Este test esta
     * para que la decision se vea: el dia que se quiera cambiar, falla aqui y
     * no un dia que alguien haya metido dos "Otro" sin enterarse.
     */
    public function test_el_nombre_no_es_unico_y_a_proposito(): void
    {
        $nombre = 'Repetido ' . strtoupper(substr(md5(uniqid('', true)), 0, 6));

        $this->tipo($nombre);
        $this->tipo($nombre);

        $this->assertSame(
            2,
            TiposIngreso::where('nombre', $nombre)->count(),
            'Dos tipos con el mismo nombre tienen que poder existir, como en los otros catálogos. Si algún día se quiere que no, este test es el que hay que cambiar.'
        );
    }

    /**
     * La pantalla esta en Configuracion y no colgando de los ingresos.
     *
     * ingresos es una pantalla de la orden, con cabecera y detalle, y un
     * catalogo de cuatro nombres no cabe ahi dentro. Y los datos maestros van
     * todos en Configuracion, que es donde uno va a buscarlos.
     */
    public function test_va_en_configuracion_y_no_en_ingresos(): void
    {
        $this->get('/configuracion/tipos-ingreso')
            ->assertOk()
            ->assertSee('Configuración');

        /*
         * La url de verdad, no el nombre de la ruta contra si mismo.
         *
         * Comparar route('tal.cosa') con 'tal.cosa' sale verde siempre, porque
         * route() devuelve lo que le pidan: el test comprobaba que el corrector
         * de textos no habia estropeado una cadena. La url es lo que de verdad
         * demuestra donde vive la pantalla, y es lo que hace el menu.
         */
        $this->assertSame(
            url('configuracion/tipos-ingreso'),
            route('configuracion.tipos_ingreso.index'),
            'La pantalla del catálogo tiene que estar en configuracion, no colgando de los ingresos.'
        );

        $this->get('/configuracion/tipos-ingreso')
            ->assertOk()
            ->assertSee(route('configuracion.tipos_ingreso.index'))
            ->assertSee('Tipos de Ingreso');
    }

    // ------------------------------------------------------------------
    // Usuarios
    // ------------------------------------------------------------------

    private function usuarioConPermisos(array $nombres): User
    {
        foreach ($nombres as $nombre) {
            Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);
        }

        $usuario = User::create([
            'name' => 'Con permisos',
            'email' => 'tipo-ingreso-permiso-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);

        $usuario->syncRoles(['operador']);
        $usuario->givePermissionTo($nombres);

        return $usuario;
    }
}
