<?php
/**
 * El oro que sale del taller, de punta a punta.
 *
 * Lo que se comprueba aqui no es que la pantalla exista, que eso lo hacen
 * otros tests. Son las dos reglas que pueden fallar sin que se note, y las dos
 * estan escritas en el servidor y no en el formulario porque se pueden saltar
 * desde cualquier lado.
 *
 * LA PUREZA. La columna guarda una fracción —0,915 es el 91,5 %— y esa es la
 * regla que mas cara sale si no se entiende. Un 91,5 tecleado tal cual se
 * guardaria como el 9100 % y no daria error de validacion si el campo no
 * estuviera acotado: el fallo no se veria hasta que una valoracion saliese
 * absurda, y para entonces ya habria documentos escritos. Por eso el maximo
 * es uno y el aviso dice las dos formas de escribirlo.
 *
 * Y el otro lado de la misma regla: dejar la pureza en blanco guarda null y
 * no cero. Que no se sepa la pureza y que el oro fuera puro cero son dos
 * cosas distintas, y en un numero tan pequeño la diferencia no se ve si
 * ——como en el precio del oro— el cero significa "no se sabe" en una tabla y
 * "no salio nada" en la otra. Aqui el cero en los gramos SI es un hecho y se
 * guarda, y en la pureza no cabe esa lectura y se guarda la falta.
 *
 * LA ORDEN CERRADA. Una orden finalizada o cancelada no admite recuperaciones
 * nuevas, pero si admite corregirlas. Es la misma regla de las otras seis
 * pantallas que cuelgan de una orden y sale del trait OrdenCerrada; lo que se
 * comprueba aqui es que esta pantalla la aplica, y que no se extiende al borrado.
 */

namespace Tests\Feature;

use App\Models\OrdenesTrabajo;
use App\Models\Recuperaciones;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RecuperacionTest extends TestCase
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
            'email' => 'recuperacion-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        $this->actingAs($admin);
    }

    // ------------------------------------------------------------------
    // Ayudas
    // ------------------------------------------------------------------

    /**
     * Una orden de trabajo, en el estado que se le pida.
     *
     * El codigo se inventa con un hash y no se deja que lo ponga el sistema,
     * porque el generador de codigos vive en el controlador y no en el
     * modelo —las ordenes no usan el trait GeneraCodigo, que es de materiales
     * y proveedores—. Es lo que hacen los demas tests que crean ordenes, y
     * lo que hace el indice unico del codigo no se pise.
     */
    private function orden(int $estado = OrdenesTrabajo::ESTADO_EN_PROCESO): OrdenesTrabajo
    {
        $original = OrdenesTrabajo::first();

        return OrdenesTrabajo::create([
            'codigo' => 'OT-REC-' . strtoupper(substr(md5(uniqid('', true)), 0, 6)),
            'cliente_id' => $original?->cliente_id,
            'fecha' => '2090-05-01',
            'descripcion' => 'Orden de la prueba',
            'peso_mineral' => 100,
            'unidad_peso' => 'kg',
            'estado' => $estado,
        ]);
    }

    private function crear(OrdenesTrabajo $orden, string $fecha, float $gramos, $pureza = null): Recuperaciones
    {
        return Recuperaciones::create([
            Recuperaciones::ORDEN_TRABAJO_ID => $orden->id,
            Recuperaciones::FECHA => $fecha,
            Recuperaciones::GRAMOS => $gramos,
            Recuperaciones::PUREZA => $pureza,
            Recuperaciones::OBSERVACIONES => 'Recuperacion de la prueba',
        ]);
    }

    // ==================================================================
    // La pantalla y el alta
    // ==================================================================

    public function test_la_pantalla_se_ve(): void
    {
        $this->get('/procesos/recuperaciones')
            ->assertOk()
            ->assertSee('Recuperaciones de oro', false);
    }

    public function test_se_guarda_una_recuperacion(): void
    {
        $orden = $this->orden();

        $this->post('/procesos/recuperaciones', [
            'orden_trabajo_id' => $orden->id,
            'fecha' => '2090-05-10',
            'gramos' => 124.5678,
            'pureza' => 0.915,
            'observaciones' => 'Primera colada',
        ], self::CABECERAS)->assertOk();

        $recuperacion = Recuperaciones::where('fecha', '2090-05-10')->first();

        $this->assertNotNull($recuperacion, 'La recuperacion deberia haberse guardado');
        $this->assertSame((int) $orden->id, (int) $recuperacion->orden_trabajo_id);
        $this->assertSame(124.5678, (float) $recuperacion->gramos, 'Los gramos deberian guardar sus cuatro decimales');
        $this->assertSame(0.915, (float) $recuperacion->pureza, 'La pureza deberia guardarse como fraccion');
    }

    public function test_una_orden_admite_varias_recuperaciones(): void
    {
        $orden = $this->orden();

        $this->crear($orden, '2090-05-10', 100.0);
        $this->crear($orden, '2090-05-20', 50.0);

        /*
         * No hay indice unico en esta tabla, y no es un descuido: una orden
         * puede tener varias recuperaciones, una por cada vez que se lavo el
         * mineral, y poner un indice por orden dejaria fuera la segunda
         * partida. Por eso el borrado de un dia no tapa a otro.
         */
        $this->assertSame(
            2,
            Recuperaciones::where('orden_trabajo_id', $orden->id)->count(),
            'Una orden puede tener varias recuperaciones, una por partida'
        );
    }

    // ==================================================================
    // La pureza, que es la regla cara
    // ==================================================================

    public function test_la_pureza_va_de_cero_a_uno(): void
    {
        $orden = $this->orden();

        /*
         * El 91,5 % tecleado tal cual es el error que esta regla evita. Con el
         * maximo en uno, el servidor lo rechaza; sin el, se guardaria el 9100 %
         * y no se veria hasta que una valoracion saliese absurda.
         */
        foreach ([91, 91.5, 100, 1.5] as $pureza) {
            $this->post('/procesos/recuperaciones', [
                'orden_trabajo_id' => $orden->id,
                'fecha' => '2090-05-10',
                'gramos' => 100,
                'pureza' => $pureza,
            ], self::CABECERAS)
                ->assertStatus(422)
                ->assertJsonValidationErrors('pureza');
        }
    }

    public function test_el_aviso_de_la_pureza_dice_las_dos_formas_de_escribirla(): void
    {
        $orden = $this->orden();

        $respuesta = $this->post('/procesos/recuperaciones', [
            'orden_trabajo_id' => $orden->id,
            'fecha' => '2090-05-10',
            'gramos' => 100,
            'pureza' => 91.5,
        ], self::CABECERAS)->assertStatus(422);

        $mensaje = $respuesta->json('errors.pureza.0');

        /*
         * El aviso tiene que decir las dos formas, porque el usuario que acaba
         * de escribir 91,5 no sabe si el campo quiere el 91,5 o el 0,915, y
         * con un "el valor no es valido" se queda dudando entre las dos y Prueba otra vez
         * abiertas y prueba otra vez lo mismo.
         */
        $this->assertStringContainsString('0,915', $mensaje, 'El aviso deberia decir como se escribe el 91,5 %');
        $this->assertStringContainsString('91,5', $mensaje, 'El aviso deberia repetir el numero que se ha escrito');
    }

    public function test_la_pureza_en_blanco_queda_sin_valor_y_no_en_cero(): void
    {
        $orden = $this->orden();

        $this->post('/procesos/recuperaciones', [
            'orden_trabajo_id' => $orden->id,
            'fecha' => '2090-05-10',
            'gramos' => 100,
            'pureza' => '',
        ], self::CABECERAS)->assertOk();

        $recuperacion = Recuperaciones::where('fecha', '2090-05-10')->first();

        $this->assertNotNull($recuperacion);
        $this->assertNull(
            $recuperacion->pureza,
            'Que no se haya medido la pureza no es que valga cero: es no saberlo'
        );
        $this->assertNull(
            $recuperacion->purezaEnPorcentaje(),
            'Y en pantalla tampoco sale un cero por ciento, sino nada'
        );
    }

    public function test_la_pureza_se_enseña_en_porcentaje(): void
    {
        $orden = $this->orden();

        $recuperacion = $this->crear($orden, '2090-05-10', 100.0, 0.915);

        /*
         * En la columna se guarda 0,915 porque es una fraccion, pero en
         * pantalla nadie razona en fracciones: se dice 91,5 %. La conversion
         * va en el modelo y no en la vista porque la necesitan tres sitios, y
         * en cuanto se escribe en dos uno se queda atras.
         */
        $this->assertSame(91.5, $recuperacion->purezaEnPorcentaje());
    }

    // ==================================================================
    // Los gramos, donde el cero si es un hecho
    // ==================================================================

    public function test_los_gramos_en_cero_se_guardan(): void
    {
        $orden = $this->orden();

        $this->post('/procesos/recuperaciones', [
            'orden_trabajo_id' => $orden->id,
            'fecha' => '2090-05-10',
            'gramos' => 0,
        ], self::CABECERAS)->assertOk();

        $this->assertSame(
            1,
            Recuperaciones::where('fecha', '2090-05-10')->count(),
            'Una partida de la que no salio oro es un dato real, no una falta de dato'
        );

        $this->assertEqualsWithDelta(
            0.0,
            (float) Recuperaciones::where('fecha', '2090-05-10')->value('gramos'),
            0.00001
        );
    }

    public function test_los_gramos_no_pueden_ser_negativos(): void
    {
        $orden = $this->orden();

        $this->post('/procesos/recuperaciones', [
            'orden_trabajo_id' => $orden->id,
            'fecha' => '2090-05-10',
            'gramos' => -5,
        ], self::CABECERAS)
            ->assertStatus(422)
            ->assertJsonValidationErrors('gramos');
    }

    public function test_el_aviso_de_los_gramos_dice_que_el_cero_sirve(): void
    {
        $orden = $this->orden();

        $respuesta = $this->post('/procesos/recuperaciones', [
            'orden_trabajo_id' => $orden->id,
            'fecha' => '2090-05-10',
            'gramos' => -5,
        ], self::CABECERAS)->assertStatus(422);

        /*
         * El mensaje de los gramos negativos tiene que ofrecer el cero como
         * salida, porque es lo que el usuario quiere escribir cuando teclea un
         * guion por error. Un "no puede ser negativo" a secas le deja pensando
         * que hay un dato que no se puede registrar.
         */
        $this->assertStringContainsString(
            'cero',
            $respuesta->json('errors.gramos.0'),
            'El aviso deberia decir que el cero si vale'
        );
    }

    // ==================================================================
    // La orden cerrada
    // ==================================================================

    public function test_una_orden_finalizada_no_admite_recuperaciones_nuevas(): void
    {
        $orden = $this->orden(OrdenesTrabajo::ESTADO_FINALIZADA);

        $this->post('/procesos/recuperaciones', [
            'orden_trabajo_id' => $orden->id,
            'fecha' => '2090-05-10',
            'gramos' => 100,
        ], self::CABECERAS)
            ->assertStatus(422)
            ->assertJsonValidationErrors('orden_trabajo_id');

        $this->assertSame(
            0,
            Recuperaciones::where('orden_trabajo_id', $orden->id)->count(),
            'No deberia haberse guardado nada en una orden finalizada'
        );
    }

    public function test_una_orden_cancelada_tampoco_admite_recuperaciones(): void
    {
        $orden = $this->orden(OrdenesTrabajo::ESTADO_CANCELADA);

        $this->post('/procesos/recuperaciones', [
            'orden_trabajo_id' => $orden->id,
            'fecha' => '2090-05-10',
            'gramos' => 100,
        ], self::CABECERAS)
            ->assertStatus(422)
            ->assertJsonValidationErrors('orden_trabajo_id');
    }

    public function test_una_orden_cerrada_si_admite_corregir_lo_que_ya_esta(): void
    {
        $orden = $this->orden(OrdenesTrabajo::ESTADO_FINALIZADA);

        $recuperacion = $this->crear($orden, '2090-05-10', 100.0, 0.9);

        /*
         * Cerrada no quiere decir intocable. Un numero de gramos mal tecleado
         * se corrige aunque la orden se cerrara en su dia, y si no el error
         * seria imposible de arreglar justo cuando mas urge.
         *
         * Es la misma regla de las otras seis pantallas que cuelgan de una
         * orden, y el orden cerrado no se extiende a la edicion.
         */
        $this->put('/procesos/recuperaciones/' . $recuperacion->id, [
            'orden_trabajo_id' => $orden->id,
            'fecha' => '2090-05-10',
            'gramos' => 187.25,
            'pureza' => 0.9,
        ], self::CABECERAS)->assertOk();

        $this->assertSame(
            187.25,
            (float) $recuperacion->fresh()->gramos,
            'Una orden cerrada debe admitir que se corrija lo que ya esta escrito'
        );
    }

    public function test_el_aviso_de_orden_cerrada_dice_que_se_puede_corregir(): void
    {
        $orden = $this->orden(OrdenesTrabajo::ESTADO_FINALIZADA);

        $respuesta = $this->post('/procesos/recuperaciones', [
            'orden_trabajo_id' => $orden->id,
            'fecha' => '2090-05-10',
            'gramos' => 100,
        ], self::CABECERAS)->assertStatus(422);

        $mensaje = $respuesta->json('errors.orden_trabajo_id.0');

        /*
         * El texto sale del trait OrdenCerrada, el mismo que usan las otras
         * seis pantallas. Y dice tres cosas: que no se puede, por que, y que se
         * puede hacer. Sin la tercera el usuario se queda mirando un aviso
         * sin salida, cuando la salida existe y es una linea de la ficha de la
         * orden: volver a ponerla en Pendiente.
         */
        $this->assertStringContainsString('recuperación', $mensaje);
        $this->assertStringContainsString($orden->codigo, $mensaje);
        $this->assertStringContainsString(
            'editar',
            $mensaje,
            'El aviso deberia decir que lo que ya esta escrito si se puede editar'
        );
        $this->assertStringContainsString(
            'Pendiente',
            $mensaje,
            'El aviso deberia decir como se abre la salida: volver a ponerla en Pendiente'
        );
    }

    public function test_el_borrado_no_se_bloquea_por_la_orden_cerrada(): void
    {
        $orden = $this->orden(OrdenesTrabajo::ESTADO_FINALIZADA);

        $recuperacion = $this->crear($orden, '2090-05-10', 100.0);

        /*
         * El borrado no mira el estado de la orden, a diferencia del alta. Un
         * registro mal puesto tiene que poder quitarse aunque la orden se
         * cerrara: si no, un error de tecleo del mes en que se cerro la orden
         * seria imposible de arreglar, que es justo cuando urge.
         */
        $this->delete('/procesos/recuperaciones/' . $recuperacion->id, [], self::CABECERAS)
            ->assertOk();

        $this->assertNotNull(
            $recuperacion->fresh()->deleted_at,
            'Una recuperacion mal puesta se debe poder borrar aunque la orden este cerrada'
        );
    }

    public function test_mover_una_recuperacion_a_una_orden_cerrada_no_se_puede(): void
    {
        $abierta = $this->orden();
        $cerrada = $this->orden(OrdenesTrabajo::ESTADO_FINALIZADA);

        $recuperacion = $this->crear($abierta, '2090-05-10', 100.0);

        $this->put('/procesos/recuperaciones/' . $recuperacion->id, [
            'orden_trabajo_id' => $cerrada->id,
            'fecha' => '2090-05-10',
            'gramos' => 100,
        ], self::CABECERAS)
            ->assertStatus(422)
            ->assertJsonValidationErrors('orden_trabajo_id');

        $this->assertSame(
            (int) $abierta->id,
            (int) $recuperacion->fresh()->orden_trabajo_id,
            'La recuperacion deberia quedarse en la orden que tenia'
        );
    }

    public function test_una_orden_borrada_no_admite_recuperaciones(): void
    {
        $orden = $this->orden();
        $orden->delete();

        /*
         * La regla exists de Laravel mira la tabla entera, borrados incluidos,
         * asi que sin el whereNull una orden dada de baja seguiria contando.
         * Con el desplegable no se puede elegir, asi que solo se podria hacer
         * a mano —con un formulario viejo que quedara abierto en otra
         * pestaña— y de ahi el nombre "a mano" incluye todo.
         */
        $this->post('/procesos/recuperaciones', [
            'orden_trabajo_id' => $orden->id,
            'fecha' => '2090-05-10',
            'gramos' => 100,
        ], self::CABECERAS)
            ->assertStatus(422)
            ->assertJsonValidationErrors('orden_trabajo_id');
    }

    // ==================================================================
    // Los permisos
    // ==================================================================

    public function test_quien_no_tiene_permiso_no_entra(): void
    {
        $this->actingAs($this->usuarioCon(['ordenes_trabajo.view']))
            ->get('/procesos/recuperaciones')
            ->assertForbidden();
    }

    public function test_quien_solo_mira_no_puede_registrar_una_recuperacion(): void
    {
        $this->actingAs($this->usuarioCon(['recuperaciones.view']));

        $this->get('/procesos/recuperaciones')->assertOk();

        $orden = $this->orden();

        /*
         * Se cuenta lo que hay antes y se compara, y no se comprueba que la
         * tabla este vacia. La base de datos es la de verdad y puede tener
         * recuperaciones de verdad: este taller ya ha registrado alguna, y un
         * "assertSame(0, ...)" aqui no estaria comprobando que el permiso
         * funciona, estaria comprobando que el taller no ha trabajado nunca.
         *
         * Y fallaria el dia que alguien registre su primera recuperacion, que
         * es justo el dia en el que este test empezaria a dar problemas.
         */
        $antes = Recuperaciones::count();

        $this->post('/procesos/recuperaciones', [
            'orden_trabajo_id' => $orden->id,
            'fecha' => '2090-05-10',
            'gramos' => 100,
        ], self::CABECERAS)->assertForbidden();

        $this->assertSame(
            $antes,
            Recuperaciones::count(),
            'Quien solo puede mirar no deberia poder registrar una recuperacion'
        );

        $this->assertSame(
            0,
            Recuperaciones::where('orden_trabajo_id', $orden->id)->count(),
            'La orden de la prueba no deberia tener ninguna recuperacion'
        );
    }

    public function test_quien_puede_crear_tambien_puede_editar_y_borrar(): void
    {
        $usuario = $this->usuarioCon([
            'recuperaciones.view',
            'recuperaciones.create',
            'recuperaciones.edit',
            'recuperaciones.delete',
        ]);

        $orden = $this->orden();

        $this->actingAs($usuario)
            ->post('/procesos/recuperaciones', [
                'orden_trabajo_id' => $orden->id,
                'fecha' => '2090-05-10',
                'gramos' => 100,
            ], self::CABECERAS)
            ->assertOk();

        $recuperacion = Recuperaciones::first();

        $this->assertNotNull($recuperacion);

        $this->actingAs($usuario)
            ->put('/procesos/recuperaciones/' . $recuperacion->id, [
                'orden_trabajo_id' => $orden->id,
                'fecha' => '2090-05-10',
                'gramos' => 150.5,
            ], self::CABECERAS)
            ->assertOk();

        $this->assertSame(150.5, (float) $recuperacion->fresh()->gramos);

        $this->actingAs($usuario)
            ->delete('/procesos/recuperaciones/' . $recuperacion->id, [], self::CABECERAS)
            ->assertOk();

        $this->assertNotNull($recuperacion->fresh()->deleted_at);
    }

    /**
     * @param  array<int, string>  $permisos
     */
    private function usuarioCon(array $permisos): User
    {
        foreach ($permisos as $nombre) {
            Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);
        }

        $usuario = User::create([
            'name' => 'Sin roles',
            'email' => 'recuperacion-sin-roles-' . uniqid() . '@test.local',
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
        $orden = $this->orden();

        $recuperacion = $this->crear($orden, '2090-05-10', 124.5678, 0.915);

        $json = $this->get('/procesos/recuperaciones', self::CABECERAS)
            ->assertOk()
            ->json();

        $fila = $this->filaDe($json['data'], $recuperacion->id);

        $this->assertNotNull($fila, 'Deberia salir la recuperacion en la tabla');

        $this->assertSame(
            124.5678,
            (float) strip_tags($fila['gramos']),
            'Los gramos deberian salir tal como se guardaron, con sus cuatro decimales'
        );

        $this->assertStringContainsString($orden->codigo, $fila['orden']);
        $this->assertStringContainsString('91.50', $fila['pureza'], 'La pureza deberia salir en porcentaje');
        $this->assertStringContainsString('bi-pencil', $fila['action']);
    }

    public function test_una_pureza_sin_medir_sale_que_no_se_midio(): void
    {
        $orden = $this->orden();

        $recuperacion = $this->crear($orden, '2090-05-10', 100.0, null);

        $json = $this->get('/procesos/recuperaciones', self::CABECERAS)
            ->assertOk()
            ->json();

        $fila = $this->filaDe($json['data'], $recuperacion->id);

        $this->assertNotNull($fila);

        /*
         * Y no sale un 0 %. Que no se haya medido la pureza y que el oro fuera
         * puro cero son dos cosas distintas, y en un numero tan pequeño como
         * este la diferencia no se ve si no se dice con palabras.
         */
        $this->assertStringContainsString('sin medir', $fila['pureza']);
        $this->assertStringNotContainsString('0.00 %', $fila['pureza']);
    }

    public function test_la_tabla_muestra_el_estado_de_la_orden(): void
    {
        $orden = $this->orden(OrdenesTrabajo::ESTADO_FINALIZADA);

        $recuperacion = $this->crear($orden, '2090-05-10', 100.0);

        $json = $this->get('/procesos/recuperaciones', self::CABECERAS)
            ->assertOk()
            ->json();

        $fila = $this->filaDe($json['data'], $recuperacion->id);

        $this->assertNotNull($fila);

        /*
         * La columna del estado de la orden no esta de adorno. Es lo que hace
         * que se entienda la regla de la orden cerrada sin abrir la orden: si
         * la de arriba esta finalizada, esa recuperacion se puede tocar pero no
         * se pueden anadir mas debajo.
         */
        $this->assertStringContainsString(
            $orden->estadoTexto(),
            $fila['estado_orden'],
            'La fila deberia enseñar el estado de la orden tal cual lo escribe la orden'
        );
    }

    public function test_los_botones_de_la_fila_llevan_su_url(): void
    {
        $orden = $this->orden();

        $recuperacion = $this->crear($orden, '2090-05-10', 100.0);

        $json = $this->get('/procesos/recuperaciones', self::CABECERAS)->json();

        $fila = $this->filaDe($json['data'], $recuperacion->id);

        $this->assertNotNull($fila);

        $this->assertStringContainsString(route('procesos.recuperaciones.edit', $recuperacion), $fila['action']);
        $this->assertStringContainsString(route('procesos.recuperaciones.destroy', $recuperacion), $fila['action']);
    }

    public function test_la_pantalla_trae_los_modales_y_el_javascript(): void
    {
        $html = $this->get('/procesos/recuperaciones')->assertOk()->getContent();

        $this->assertStringContainsString('id="modalRecuperacion"', $html);
        $this->assertStringContainsString('id="formRecuperacion"', $html);
        $this->assertStringContainsString('id="btnNuevaRecuperacion"', $html);

        /*
         * Y el hueco del aviso de la pureza. Es un elemento escondido al que
         * el javascript le escribe mientras se teclea, y si no esta en el
         * html no se ve ningun error: el javascript le escribe a un elemento
         * que no existe y no pasa nada, que es el peor fallo posible porque
         * parece que funciona.
         */
        $this->assertStringContainsString('id="purezaAviso"', $html);

        $this->assertStringContainsString('js/procesos/recuperaciones.js', $html);
        $this->assertStringContainsString(route('procesos.recuperaciones.store'), $html);
    }

    public function test_el_modal_de_editar_recibe_los_datos(): void
    {
        $orden = $this->orden();

        $recuperacion = $this->crear($orden, '2090-05-10', 124.5678, 0.915);

        $datos = $this->get('/procesos/recuperaciones/' . $recuperacion->id . '/edit', self::CABECERAS)
            ->assertOk()
            ->json();

        $this->assertSame((int) $orden->id, $datos['orden_trabajo_id']);
        $this->assertSame('2090-05-10', $datos['fecha']);
        $this->assertSame(124.5678, (float) $datos['gramos']);

        /*
         * La pureza se manda tal cual esta guardada —0.915— y no dividida
         * entre cien. Si el modal recibiera el 91,5 y lo escribiera en el
         * campo, al guardar sin tocarlo se guardaria 91,5 que el servidor
         * rechaza; y si lo dividiera para enseñarlo, el campo tendria un
         * numero que el usuario no reconoce.
         */
        $this->assertSame(0.915, (float) $datos['pureza']);
    }

    /**
     * La fila de la tabla que corresponde a una recuperacion.
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
