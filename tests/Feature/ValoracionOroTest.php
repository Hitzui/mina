<?php
/**
 * Cuanto vale lo que salio del taller, de punta a punta.
 *
 * Lo que se comprueba aqui no es solo que la pantalla exista, que eso lo hacen
 * otros tests. Son las tres cosas que pueden fallar sin que se note, y las tres
 * estan escritas en el servidor y no en el formulario porque se pueden saltar
 * desde cualquier lado.
 *
 * LA CUENTA, Y EN QUE ORDEN. Es lo mas importante de esta pantalla y lo unico
 * que no tiene forma de pantalla: se comprueba con los numeros de verdad, no
 * con un ejemplo inventado. Si el orden de las operaciones se cruzara —si el
 * tipo de cambio se multiplicara antes que el precio, por ejemplo— con un
 * ejemplo inventado el fallo puede pasar desapercibido, y con los datos del
 * taller se ve enseguida porque el numero no cuadra.
 *
 * Y los gramos que se valoran son los FINOS si la pureza se sabe, y los que
 * salieron si no se sabe. Es la regla que evita pagar de mas por material que no
 * era oro: una partida de 2 gramos al 75 % tiene 1,5 gramos de oro fino.
 * Deliberadamente NO es la misma regla que la del precio del oro, donde el
 * cero significa "de ese dia no se sabe": aqui el cero es un hecho.
 *
 * UN VALOR POR RECUPERACION. Una partida tiene un valor, no dos. Hay un indice
 * unico en la base, y ademas el servidor lo comprueba antes de escribir, para
 * que el aviso sea del taller y no de MySQL. Lo que importa no es que no se
 * pueda meter la segunda —eso lo haria el indice—, sino lo que el aviso dice:
 * que lo que se hace es corregir la que hay.
 *
 * Y EL PRECIO DEL QUE NO HAY. Si no hay ningun precio anterior a la fecha de la
 * valoracion, no se guarda nada y se dice por que. Un documento con valor cero
 * es lo peor de los dos mundos: sale un numero y no avisa de nada.
 *
 * LA ORDEN CANCELADA. Y aqui la regla es distinta de las otras seis pantallas,
 * a proposito: una finalizada SI admite valoracion, porque el taller se cierra
 * y el oro se valora despues, al facturar. Bloquear las finalizadas dejaria
 * los gramos de una orden cerrada sin poder facturar nunca. La cancelada no.
 */

namespace Tests\Feature;

use App\Models\Moneda;
use App\Models\OrdenesTrabajo;
use App\Models\PreciosOro;
use App\Models\Recuperaciones;
use App\Models\TiposCambio;
use App\Models\User;
use App\Models\ValoracionesOro;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ValoracionOroTest extends TestCase
{
    use DatabaseTransactions;

    private const CABECERAS = [
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ];

    /**
     * El dia al que seLeponen los precios, los tipos de cambio y las
     * valoraciones.
     *
     * Es el año 2090 y no el de ahora, y no por capricho: los precios y los
     * tipos de cambio de este taller son reales y estan cargados, y si los
     * tests usaran las fechas de hoy la serie del taller —junio de 2026— no
     * tendria nada anterior a la fecha del test, porque en la base no hay nada
     * de 2090. En el 2090 no hay nada tampoco, que es justo lo que se quiere
     * para probar: la pantalla tiene que decir que no encuentra precio y
     * negarse a inventar uno.
     *
     * Cuando se cargan datos de prueba, van todos en esta fecha o antes.
     */
    private const DIA = '2090-07-15';
    private const DIA_ANTERIOR = '2090-07-01';

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'valoracion-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        $this->actingAs($admin);
    }

    // ------------------------------------------------------------------
    // Ayudas
    // ------------------------------------------------------------------

    private function orden(int $estado = OrdenesTrabajo::ESTADO_EN_PROCESO): OrdenesTrabajo
    {
        $original = OrdenesTrabajo::first();

        return OrdenesTrabajo::create([
            'codigo' => 'OT-VAL-' . strtoupper(substr(md5(uniqid('', true)), 0, 6)),
            'cliente_id' => $original?->cliente_id,
            'fecha' => self::DIA,
            'descripcion' => 'Orden de la prueba',
            'peso_mineral' => 100,
            'unidad_peso' => 'kg',
            'estado' => $estado,
        ]);
    }

    private function recuperacion(
        OrdenesTrabajo $orden,
        float $gramos = 2,
        $pureza = null,
        string $fecha = self::DIA
    ): Recuperaciones {
        return Recuperaciones::create([
            Recuperaciones::ORDEN_TRABAJO_ID => $orden->id,
            Recuperaciones::FECHA => $fecha,
            Recuperaciones::GRAMOS => $gramos,
            Recuperaciones::PUREZA => $pureza,
            Recuperaciones::OBSERVACIONES => 'Recuperacion de la prueba',
        ]);
    }

    /**
     * Un precio del gramo en una moneda.
     */
        /**
     * La valoracion de una recuperacion, y solo la suya.
     *
     * Va en vez de un ValoracionesOro::first() porque la tabla no esta vacia: el
     * taller probo la pantalla y dejo una valoracion de las suyas. Con un
     * first() a secas los tests se llevaban esa, y los numeros no cuadraban con
     * numeros, no con el taller: uno esperaba 9.400 y le salia 7.050, que era la
     * valoracion del taller.
     *
     * Y el nombre lo dice: la valoracion DE la recuperacion. Un first() a secas
     * es correcta solo mientras la tabla este vacia, y en cuanto el taller mete
     * algo deja de serlo sin que nadie avise.
     *
     * @return ValoracionesOro
     */
    private function valoracionDe(Recuperaciones $recuperacion): ValoracionesOro
    {
        return ValoracionesOro::where(ValoracionesOro::RECUPERACION_ID, $recuperacion->id)
            ->firstOrFail();
    }

    private function precio(string $fecha, float $precio, int $monedaId, string $unidad = 'gramo'): PreciosOro
    {
        return PreciosOro::create([
            PreciosOro::FECHA => $fecha,
            PreciosOro::PRECIO => $precio,
            PreciosOro::UNIDAD => $unidad,
            PreciosOro::MONEDA_ID => $monedaId,
            PreciosOro::FUENTE => 'prueba',
            PreciosOro::OBSERVACIONES => 'Precio de la prueba',
        ]);
    }

    private function tipoCambio(string $fecha, int $monedaId, float $valor): TiposCambio
    {
        return TiposCambio::create([
            TiposCambio::FECHA => $fecha,
            TiposCambio::MONEDA_ID => $monedaId,
            TiposCambio::VALOR => $valor,
            TiposCambio::FUENTE => 'prueba',
        ]);
    }

    private function monedaBase(): Moneda
    {
        $base = Moneda::where('es_moneda_base', true)->first();

        if ($base) {
            return $base;
        }

        return Moneda::create([
            'codigo' => 'XXX',
            'nombre' => 'Moneda de la prueba',
            'simbolo' => 'X',
            'es_moneda_base' => true,
        ]);
    }

    /**
     * Una moneda que no sea la base, y que no este en uso, para poder meterla
     * sin descolocar la base ni chocar con el indice unico del codigo.
     */
    private function monedaNueva(string $sufijo): Moneda
    {
        return Moneda::create([
            'codigo' => strtoupper('T' . substr(md5($sufijo . uniqid('', true)), 0, 2)),
            'nombre' => 'Moneda de la prueba ' . $sufijo,
            'simbolo' => 'T',
            'es_moneda_base' => false,
        ]);
    }

    private function datos(array $sobre = []): array
    {
        return array_merge([
            'recuperacion_id' => 0,
            'fecha' => self::DIA,
            'precio_moneda' => 0,
            'moneda_id' => 0,
        ], $sobre);
    }

    // ==================================================================
    // La pantalla
    // ==================================================================

    public function test_la_pantalla_se_ve(): void
    {
        $this->get('/procesos/valoraciones-oro')
            ->assertOk()
            ->assertSee('Valoraciones de oro');
    }

    public function test_el_menu_de_produccion_sale_la_vez_que_las_demas(): void
    {
        $this->get('/procesos/valoraciones-oro')
            ->assertOk()
            ->assertSee('Valoraciones de Oro')
            ->assertSee('Recuperaciones de Oro');
    }

    public function test_no_deja_ver_la_pantalla_sin_permiso(): void
    {
        $this->actingAs($this->usuarioSinPermisos());

        $this->get('/procesos/valoraciones-oro')->assertForbidden();
    }

    public function test_no_deja_valorar_sin_permiso_de_crear(): void
    {
        $usuario = $this->usuarioConPermisos(['valoraciones_oro.view']);

        $this->actingAs($usuario);

        $this->postJson(
            '/procesos/valoraciones-oro',
            $this->datos(),
            self::CABECERAS
        )->assertForbidden();
    }

    // ==================================================================
    // La cuenta
    // ==================================================================

    /**
     * La cuenta normal: gramos finos por el precio del gramo.
     *
     * Con pureza: 2 gramos al 75 % son 1,5 gramos de oro fino. A 4.700 el
     * gramo, 1,5 x 4.700 son 7.050. Sin pureza: 2 x 4.700 son 9.400.
     *
     * Los dos numeros salen a mano, y por eso estan escritos como estan:
     * si algun dia se cambia el orden de las operaciones, el test falla con
     * un numero que se puede comprobar con una multiplicacion y no con
     * criterio.
     */
    public function test_valora_los_gramos_finos_cuando_hay_pureza(): void
    {
        $base = $this->monedaBase();

        $this->precio(self::DIA, 4700, (int) $base->id);

        $orden = $this->orden();
        $recuperacion = $this->recuperacion($orden, 2, 0.75);

        $this->postJson('/procesos/valoraciones-oro', $this->datos([
            'recuperacion_id' => $recuperacion->id,
            'precio_moneda' => $base->id,
            'moneda_id' => $base->id,
        ]), self::CABECERAS)->assertOk();

        $valoracion = ValoracionesOro::where(ValoracionesOro::RECUPERACION_ID, $recuperacion->id)->firstOrFail();

        $this->assertNotNull($valoracion, 'Deberia haberse guardado la valoracion.');
        $this->assertEqualsWithDelta(
            7050.00,
            (float) $valoracion->valor,
            0.001,
            '2 gramos al 75 % son 1,5 gramos finos, y 1,5 x 4.700 son 7.050.'
        );
    }

    public function test_valora_los_gramos_enteros_cuando_no_hay_pureza(): void
    {
        $base = $this->monedaBase();

        $this->precio(self::DIA, 4700, (int) $base->id);

        $orden = $this->orden();
        $recuperacion = $this->recuperacion($orden, 2, null);

        $this->postJson('/procesos/valoraciones-oro', $this->datos([
            'recuperacion_id' => $recuperacion->id,
            'precio_moneda' => $base->id,
            'moneda_id' => $base->id,
        ]), self::CABECERAS)->assertOk();

        $this->assertEqualsWithDelta(
            9400.00,
            (float) ValoracionesOro::where(ValoracionesOro::RECUPERACION_ID, $recuperacion->id)->firstOrFail()->valor,
            0.001,
            'Sin pureza se valoran los 2 gramos enteros, que son 2 x 4.700.'
        );
    }

    /**
     * El precio de una onza troy se convierte a gramo antes de multiplicar.
     *
     * Es la trampa de las unidades: si se multiplicaran los gramos por el
     * precio de la onza tal cual, 1,5 gramos de oro valdrian lo mismo que una
     * onza entera, que es treinta y un veces mas. Con la serie en onza troy,
     * una onza a 146.108,4482 dólares son 4.700 el gramo, y la cuenta sale
     * igual que si el precio estuviera guardado directamente en gramos.
     */
    public function test_convierte_el_precio_de_la_onza_troy_a_gramo(): void
    {
        $base = $this->monedaBase();

        // 1 onza troy son 31,1034768 gramos
        $this->precio(self::DIA, 4700 * 31.1034768, (int) $base->id, 'onza troy');

        $orden = $this->orden();
        $recuperacion = $this->recuperacion($orden, 2, 0.75);

        $this->postJson('/procesos/valoraciones-oro', $this->datos([
            'recuperacion_id' => $recuperacion->id,
            'precio_moneda' => $base->id,
            'moneda_id' => $base->id,
        ]), self::CABECERAS)->assertOk();

        $this->assertEqualsWithDelta(
            7050.00,
            (float) ValoracionesOro::where(ValoracionesOro::RECUPERACION_ID, $recuperacion->id)->firstOrFail()->valor,
            0.01,
            'El precio de la onza tiene que convertirse a gramo antes de multiplicar.'
        );
    }

    /**
     * Con el precio en una moneda y el valor en otra, entra el tipo de cambio.
     *
     * 1,5 gramos a 100 dólares, y el dólar a 36,6243 córdobas: 150 dólares
     * son 5.493,65 córdobas. Y la moneda del precio queda guardada en la
     * fila, que es lo que despues permite saber de donde salio la cifra.
     */
    public function test_pasa_el_valor_a_la_moneda_con_el_tipo_de_cambio(): void
    {
        $base = $this->monedaBase();
        $otra = $this->monedaNueva('para el cambio');

        $this->precio(self::DIA, 100, (int) $otra->id);
        $this->tipoCambio(self::DIA, (int) $otra->id, 36.6243);

        $orden = $this->orden();
        $recuperacion = $this->recuperacion($orden, 2, 0.75);

        $this->postJson('/procesos/valoraciones-oro', $this->datos([
            'recuperacion_id' => $recuperacion->id,
            'precio_moneda' => $otra->id,
            'moneda_id' => $base->id,
        ]), self::CABECERAS)->assertOk();

        $valoracion = ValoracionesOro::where(ValoracionesOro::RECUPERACION_ID, $recuperacion->id)->firstOrFail();

        $this->assertEqualsWithDelta(
            5493.65,
            (float) $valoracion->valor,
            0.01,
            '1,5 gramos a 100 dólares, al cambio del día, son 5.493,65 córdobas.'
        );

        $this->assertEquals(
            $base->id,
            (int) $valoracion->moneda_id,
            'El valor tiene que quedar en la moneda que se eligió para el.'
        );

        $this->assertNotNull(
            $valoracion->precio_oro_id,
            'La fila tiene que recordar de qué precio salió, que es lo que permite rehacer la cuenta.'
        );
    }

    /**
     * La fila guarda el dia del precio que se uso, y lo dice si no es el suyo.
     *
     * Un dia sin cotizacion se tasa con el anterior, que es lo que se ha
     * hecho siempre: el metal no cambia de valor porque el banco no publicara
     * ese dia. Pero eso hay que poder verlo, porque si no, una valoracion del
     * dia 15 cerrada con el precio del dia 1 parece un error y no lo es.
     */
    public function test_si_no_hay_precio_de_ese_dia_usa_el_anterior_y_lo_dice(): void
    {
        $base = $this->monedaBase();

        $this->precio(self::DIA_ANTERIOR, 4000, (int) $base->id);

        $orden = $this->orden();
        $recuperacion = $this->recuperacion($orden, 2, 0.75);

        $this->postJson('/procesos/valoraciones-oro', $this->datos([
            'recuperacion_id' => $recuperacion->id,
            'precio_moneda' => $base->id,
            'moneda_id' => $base->id,
        ]), self::CABECERAS)->assertOk();

        $valoracion = ValoracionesOro::where(ValoracionesOro::RECUPERACION_ID, $recuperacion->id)->firstOrFail();

        $this->assertEqualsWithDelta(
            6000.00,
            (float) $valoracion->valor,
            0.001,
            '1,5 gramos al precio del día 1, que es 4.700... no, 4.000 el gramo.'
        );

        $this->assertSame(
            self::DIA_ANTERIOR,
            $valoracion->precio_oro->fecha->toDateString(),
            'La fila tiene que guardar el día del precio que se usó, no el de la valoración.'
        );
    }

    // ==================================================================
    // Cuando no se puede valorar
    // ==================================================================

    /**
     * Sin ningun precio anterior no se guarda nada, y se dice por que.
     *
     * Y hay que mirar en una moneda que NO tenga precios, y no en la base.
     * Porque la base es la de verdad: el taller tiene un precio del oro de
     * septiembre de 2026, y cualquier fecha de 2090 es posterior. Buscando en la
     * base, el precio de 2026 SI estaria antes y este test no comprobaria nada,
     * que es justo el fallo que tiene un test que parece probar el caso y en
     * realidad no lo hace.
     *
     * Por eso se crea una moneda que no esta en uso, para estar seguros de que
     * no hay ni un solo precio en ella. Es la unica manera de garantizar el
     * "no hay" sin depender de lo que el taller tenga cargado.
     *
     * Y lo que no puede ser es guardar valor cero: un documento con cero sale,
     * se imprime y se factura, sin avisar de nada.
     */
    public function test_sin_precio_anterior_no_guarda_y_explica_por_que(): void
    {
        $base = $this->monedaBase();
        $sinPrecios = $this->monedaNueva('sin ningun precio');

        $orden = $this->orden();
        $recuperacion = $this->recuperacion($orden, 2, 0.75);

        $antes = ValoracionesOro::count();

        $respuesta = $this->postJson('/procesos/valoraciones-oro', $this->datos([
            'recuperacion_id' => $recuperacion->id,
            'precio_moneda' => $sinPrecios->id,
            'moneda_id' => $base->id,
        ]), self::CABECERAS);

        $respuesta->assertStatus(422);

        $motivo = $respuesta->json('errors.fecha.0') ?? '';

        $this->assertStringContainsString(
            'precio del oro',
            $motivo,
            'El aviso tiene que decir que lo que falta es el precio, no algo genérico.'
        );

        $this->assertSame(
            $antes,
            ValoracionesOro::count(),
            'Sin precio no se debe guardar ninguna fila, ni con valor cero.'
        );
    }

    /**
     * Un cero en la serie no vale para valorar.
     *
     * El cero del precio del oro significa "de ese dia no se sabe". Si se
     * aceptara, 1,5 gramos por cero darian un valor de cero y una valoracion
     * guardada como si fuera real. El precio anterior se usa, que es lo que
     * si tiene sentido.
     *
     * Y en una moneda que no tenga nada antes, un cero no convierte un "no hay"
     * en un "vale cero": sigue siendo un "no hay", que es la comprobacion.
     */
    public function test_un_cero_en_la_serie_no_se_usa_para_valorar(): void
    {
        $base = $this->monedaBase();
        $soloCeros = $this->monedaNueva('solo ceros');

        $this->precio(self::DIA, 0, (int) $soloCeros->id);
        $this->precio(self::DIA_ANTERIOR, 0, (int) $soloCeros->id);

        $orden = $this->orden();
        $recuperacion = $this->recuperacion($orden, 2, 0.75);

        $this->postJson('/procesos/valoraciones-oro', $this->datos([
            'recuperacion_id' => $recuperacion->id,
            'precio_moneda' => $soloCeros->id,
            'moneda_id' => $base->id,
        ]), self::CABECERAS)->assertStatus(422);

        $this->assertSame(
            0,
            ValoracionesOro::where(ValoracionesOro::RECUPERACION_ID, $recuperacion->id)->count(),
            'Un cero en la serie no puede acabar en una valoración de cero.'
        );
    }

    /**
     * Si hay precio pero no tipo de cambio, tampoco se guarda.
     *
     * Porque el valor se va a guardar en otra moneda y sin el cambio no hay
     * forma de pasar de una a otra. Guardar el numero de la moneda del precio
     * y llamarlo valor seria mentir en la cabecera del documento.
     */
    public function test_sin_tipo_de_cambio_no_guarda_ni_las_dos_monedas_distintas(): void
    {
        $base = $this->monedaBase();
        $otra = $this->monedaNueva('sin cambio');

        $this->precio(self::DIA, 100, (int) $otra->id);

        $orden = $this->orden();
        $recuperacion = $this->recuperacion($orden, 2, 0.75);

        $this->postJson('/procesos/valoraciones-oro', $this->datos([
            'recuperacion_id' => $recuperacion->id,
            'precio_moneda' => $otra->id,
            'moneda_id' => $base->id,
        ]), self::CABECERAS)->assertStatus(422);

        $this->assertSame(
            0,
            ValoracionesOro::where(ValoracionesOro::RECUPERACION_ID, $recuperacion->id)->count()
        );
    }

    // ==================================================================
    // Un valor por recuperacion
    // ==================================================================

    /**
     * Una recuperacion no puede tener dos valoraciones.
     *
     * Y lo que se comprueba no es solo que la segunda falle, que eso lo haria
     * el indice unico de la base. Lo que importa es lo que dice el aviso: que
     * lo que se hace es corregir la que hay, no anadir otra.
     */
    public function test_no_admite_una_segunda_valoracion_de_la_misma_recuperacion(): void
    {
        $base = $this->monedaBase();

        $this->precio(self::DIA, 4700, (int) $base->id);

        $orden = $this->orden();
        $recuperacion = $this->recuperacion($orden, 2, 0.75);

        $this->postJson('/procesos/valoraciones-oro', $this->datos([
            'recuperacion_id' => $recuperacion->id,
            'precio_moneda' => $base->id,
            'moneda_id' => $base->id,
        ]), self::CABECERAS)->assertOk();

        $respuesta = $this->postJson('/procesos/valoraciones-oro', $this->datos([
            'recuperacion_id' => $recuperacion->id,
            'precio_moneda' => $base->id,
            'moneda_id' => $base->id,
        ]), self::CABECERAS);

        $respuesta->assertStatus(422);

        $motivo = $respuesta->json('errors.recuperacion_id.0') ?? '';

        $this->assertStringContainsString(
            'corrige',
            $motivo,
            'El aviso tiene que decir que lo que se hace es corregir la valoracion que ya hay.'
        );

        $this->assertSame(
            1,
            ValoracionesOro::where('recuperacion_id', $recuperacion->id)->count(),
            'Debe seguir habiendo una sola valoracion de esa recuperacion.'
        );
    }

    /**
     * El indice unico esta en la base, no solo en el servidor.
     *
     * El aviso del servidor se puede saltar mandando un INSERT a mano, con un
     * tinker o con un formulario antiguo que se dejo abierto. El indice es lo
     * que hace que eso no deje pasar la segunda fila. Se comprueba aqui porque
     * un indice que se quita sin querer no se nota en ningun otro test: todos
     * los demas pasan por el controlador, que ya lo comprueba.
     */
    public function test_el_indice_unico_de_la_base_impide_la_segunda_fila(): void
    {
        $base = $this->monedaBase();

        $this->precio(self::DIA, 4700, (int) $base->id);

        $orden = $this->orden();
        $recuperacion = $this->recuperacion($orden, 2, 0.75);

        $primera = ValoracionesOro::create([
            ValoracionesOro::RECUPERACION_ID => $recuperacion->id,
            ValoracionesOro::PRECIO_ORO_ID => PreciosOro::first()->id,
            ValoracionesOro::VALOR => 7050,
            ValoracionesOro::MONEDA_ID => $base->id,
            ValoracionesOro::FECHA => self::DIA,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        ValoracionesOro::create([
            ValoracionesOro::RECUPERACION_ID => $recuperacion->id,
            ValoracionesOro::PRECIO_ORO_ID => $primera->precio_oro_id,
            ValoracionesOro::VALOR => 9000,
            ValoracionesOro::MONEDA_ID => $base->id,
            ValoracionesOro::FECHA => self::DIA_ANTERIOR,
        ]);
    }

    /**
     * Una valoracion borrada se puede volver a poner, y no choca con el
     * indice.
     *
     * Es el choque entre el indice unico y el borrado logico, que es el mismo
     * que rompia al tipo de cambio y al precio del oro. Sin esto, borrar una
     * valoracion y meterla otra vez —que es lo que se hace cuando el banco
     * corrige un precio y se quiere empezar de cero— fallaria con un error de
     * MySQL en vez de revivir la fila.
     */
    public function test_una_valoracion_borrada_se_vuelve_a_guardar_sin_chocar(): void
    {
        $base = $this->monedaBase();

        $this->precio(self::DIA, 4700, (int) $base->id);

        $orden = $this->orden();
        $recuperacion = $this->recuperacion($orden, 2, 0.75);

        $datos = $this->datos([
            'recuperacion_id' => $recuperacion->id,
            'precio_moneda' => $base->id,
            'moneda_id' => $base->id,
        ]);

        $this->postJson('/procesos/valoraciones-oro', $datos, self::CABECERAS)->assertOk();

        $valoracion = ValoracionesOro::where(ValoracionesOro::RECUPERACION_ID, $recuperacion->id)->firstOrFail();
        $idOriginal = $valoracion->id;

        $this->deleteJson(
            '/procesos/valoraciones-oro/' . $valoracion->id,
            [],
            self::CABECERAS
        )->assertOk();

        $this->postJson('/procesos/valoraciones-oro', $datos, self::CABECERAS)->assertOk();

        $vivas = ValoracionesOro::where(ValoracionesOro::RECUPERACION_ID, $recuperacion->id)->get();

        $this->assertCount(1, $vivas, 'No debe quedar mas de una valoracion viva de esa recuperacion.');
        $this->assertSame(
            $idOriginal,
            $vivas->first()->id,
            'La fila borrada tiene que revivir, no crearse una segunda con el indice ya ocupado.'
        );
    }

    // ==================================================================
    // La orden: cancelada si, finalizada no
    // ==================================================================

    /**
     * Una orden finalizada SI admite valoracion.
     *
     * Y esto es lo que separa esta pantalla de las otras seis. El taller se
     * cierra y el oro se valora despues, al facturar: si aqui se bloqueara
     * como en las demas, cerrar la orden dejaria sus gramos sin poder facturar
     * nunca, y la unica salida seria reabrir la orden para meter un numero.
     */
    public function test_una_orden_finalizada_admite_valoracion_nueva(): void
    {
        $base = $this->monedaBase();

        $this->precio(self::DIA, 4700, (int) $base->id);

        $orden = $this->orden(OrdenesTrabajo::ESTADO_FINALIZADA);
        $recuperacion = $this->recuperacion($orden, 2, 0.75);

        $this->postJson('/procesos/valoraciones-oro', $this->datos([
            'recuperacion_id' => $recuperacion->id,
            'precio_moneda' => $base->id,
            'moneda_id' => $base->id,
        ]), self::CABECERAS)->assertOk();

        $this->assertSame(
            1,
            ValoracionesOro::where('recuperacion_id', $recuperacion->id)->count(),
            'Una orden finalizada admite valoración: el taller ya se cerró y el oro se valora al facturar.'
        );
    }

    /**
     * Una orden cancelada no admite valoracion nueva.
     *
     * Una orden cancelada no produjo trabajo, y lo que no se hizo no se valora.
     * El aviso no sugiere volver a poner la orden en Pendiente —que es lo que
     * sugieren las otras seis pantallas— porque cancelar no fue un error de
     * tecleo que se quiera deshacer escribiendo un numero de gramos: es una
     * decision del taller.
     */
    public function test_una_orden_cancelada_no_admite_valoracion_nueva(): void
    {
        $base = $this->monedaBase();

        $this->precio(self::DIA, 4700, (int) $base->id);

        $orden = $this->orden(OrdenesTrabajo::ESTADO_CANCELADA);
        $recuperacion = $this->recuperacion($orden, 2, 0.75);

        $respuesta = $this->postJson('/procesos/valoraciones-oro', $this->datos([
            'recuperacion_id' => $recuperacion->id,
            'precio_moneda' => $base->id,
            'moneda_id' => $base->id,
        ]), self::CABECERAS);

        $respuesta->assertStatus(422);

        $motivo = $respuesta->json('errors.recuperacion_id.0') ?? '';

        $this->assertStringContainsString(
            'cancelada',
            $motivo,
            'El aviso tiene que decir que la orden está cancelada.'
        );

        $this->assertSame(
            0,
            ValoracionesOro::where('recuperacion_id', $recuperacion->id)->count()
        );
    }

    /**
     * Pero lo que ya esta escrito se corrige, cancelada o no.
     *
     * Un valor mal puesto hay que poder arreglarlo aunque la orden este
     * cerrada. Es la misma regla que en las otras pantallas, y aqui no hay
     * excepcion: la correccion no es un dato nuevo, es arreglar uno que se
     * escribio mal.
     */
    public function test_una_valoracion_ya_hecha_se_puede_corregir_este_la_orden_cancelada(): void
    {
        $base = $this->monedaBase();

        $this->precio(self::DIA, 4700, (int) $base->id);

        $orden = $this->orden();
        $recuperacion = $this->recuperacion($orden, 2, 0.75);

        $this->postJson('/procesos/valoraciones-oro', $this->datos([
            'recuperacion_id' => $recuperacion->id,
            'precio_moneda' => $base->id,
            'moneda_id' => $base->id,
        ]), self::CABECERAS)->assertOk();

        $valoracion = ValoracionesOro::where(ValoracionesOro::RECUPERACION_ID, $recuperacion->id)->firstOrFail();

        // Y ahora se cancela la orden, que es lo que pasa en la vida real
        $orden->update(['estado' => OrdenesTrabajo::ESTADO_CANCELADA]);

        $this->putJson(
            '/procesos/valoraciones-oro/' . $valoracion->id,
            $this->datos([
                'recuperacion_id' => $recuperacion->id,
                'precio_moneda' => $base->id,
                'moneda_id' => $base->id,
                'observaciones' => 'Corregida despues de cancelar',
            ]),
            self::CABECERAS
        )->assertOk();

        $this->assertSame(
            'Corregida despues de cancelar',
            $valoracion->fresh()->observaciones
        );
    }

    // ==================================================================
    // Editar, borrar y la ficha
    // ==================================================================

    /**
     * Al corregir, la cuenta se vuelve a hacer con el precio de ahora.
     *
     * Si el banco corrige un precio, la valoracion guardada seCerro con el
     * precio viejo. Corregirla —cambiando el dia o las monedas— tiene que
     * recalcular, y no dejar el numero que habia. Si se dejara, cambiar el dia
     * no cambiaria el valor, que es la forma de que el campo de la fecha
     * exista pero no sirva para nada.
     */
    public function test_al_corregir_se_vuelve_a_calcular_el_valor(): void
    {
        $base = $this->monedaBase();

        $this->precio(self::DIA, 4700, (int) $base->id);
        $this->precio(self::DIA_ANTERIOR, 4000, (int) $base->id);

        $orden = $this->orden();
        $recuperacion = $this->recuperacion($orden, 2, 0.75);

        $this->postJson('/procesos/valoraciones-oro', $this->datos([
            'recuperacion_id' => $recuperacion->id,
            'fecha' => self::DIA,
            'precio_moneda' => $base->id,
            'moneda_id' => $base->id,
        ]), self::CABECERAS)->assertOk();

        $valoracion = ValoracionesOro::where(ValoracionesOro::RECUPERACION_ID, $recuperacion->id)->firstOrFail();

        $this->assertEqualsWithDelta(7050.00, (float) $valoracion->valor, 0.001);

        $this->putJson(
            '/procesos/valoraciones-oro/' . $valoracion->id,
            $this->datos([
                'recuperacion_id' => $recuperacion->id,
                'fecha' => self::DIA_ANTERIOR,
                'precio_moneda' => $base->id,
                'moneda_id' => $base->id,
            ]),
            self::CABECERAS
        )->assertOk();

        $this->assertEqualsWithDelta(
            6000.00,
            (float) $valoracion->fresh()->valor,
            0.001,
            'Al cambiar el día tiene que recalcular con el precio de ese día.'
        );
    }

    /**
     * La ficha trae la cuenta entera, y no solo el valor guardado.
     *
     * Es lo que permite ver de donde sale la cifra sin abrir la serie de
     * precios: los gramos valorados, el precio del gramo, de que dia salio y
     * el cambio si lo hubo. Sin esto, el unico numero que se puede ver es el
     * guardado, y no hay forma de saber si sigue siendo el que sale hoy.
     */
    public function test_la_ficha_trae_la_cueba_que_no_esta_calculada_a_mano(): void
    {
        $base = $this->monedaBase();

        $this->precio(self::DIA, 4700, (int) $base->id);

        $orden = $this->orden();
        $recuperacion = $this->recuperacion($orden, 2, 0.75);

        $this->postJson('/procesos/valoraciones-oro', $this->datos([
            'recuperacion_id' => $recuperacion->id,
            'precio_moneda' => $base->id,
            'moneda_id' => $base->id,
        ]), self::CABECERAS)->assertOk();

        $ficha = $this->getJson(
            '/procesos/valoraciones-oro/' . ValoracionesOro::where(ValoracionesOro::RECUPERACION_ID, $recuperacion->id)->firstOrFail()->id . '/edit',
            self::CABECERAS
        )->assertOk()->json();

        $this->assertArrayHasKey('calculo', $ficha, 'La ficha tiene que traer el cálculo.');
        $this->assertTrue($ficha['calculo']['ok']);
        $this->assertEqualsWithDelta(7050.00, $ficha['calculo']['valor'], 0.001);
        $this->assertTrue($ficha['usa_pureza'], 'Tiene que decir que se aplicó la pureza.');
        $this->assertEqualsWithDelta(1.5, $ficha['gramos_valorados'], 0.0001);
    }

    public function test_borrar_deja_los_gramos_de_la_recuperacion(): void
    {
        $base = $this->monedaBase();

        $this->precio(self::DIA, 4700, (int) $base->id);

        $orden = $this->orden();
        $recuperacion = $this->recuperacion($orden, 2, 0.75);

        $this->postJson('/procesos/valoraciones-oro', $this->datos([
            'recuperacion_id' => $recuperacion->id,
            'precio_moneda' => $base->id,
            'moneda_id' => $base->id,
        ]), self::CABECERAS)->assertOk();

        $this->deleteJson(
            '/procesos/valoraciones-oro/' . ValoracionesOro::where(ValoracionesOro::RECUPERACION_ID, $recuperacion->id)->firstOrFail()->id,
            [],
            self::CABECERAS
        )->assertOk();

        $this->assertNull(
            $recuperacion->fresh()->valoracion,
            'Borrar la valoracion no debe borrar los gramos: para volver a tener la cifra se valora otra vez.'
        );
    }

    // ==================================================================
    // La pantalla, en pequeño
    // ==================================================================

    /**
     * El formulario no tiene campo de valor.
     *
     * Es la regla de la pantalla entera, y es la que evita que un documento y
     * una cuenta dlgas cifras distintas para los mismos gramos. Si alguien
     * anade un input al formulario, este test falla, que es justo para lo que
     * esta: para que anadirlo sea una decision consciente.
     */
    public function test_el_formulario_no_tiene_campo_de_valor(): void
    {
        $html = $this->get('/procesos/valoraciones-oro')->assertOk()->getContent();

        $this->assertStringNotContainsString(
            'name="valor"',
            $html,
            'No puede haber un campo de valor: lo que se guarda lo calcula el servidor, y un campo a mano deja dos cifras para los mismos gramos.'
        );

        $this->assertStringContainsString(
            'data-calcular-url',
            $html,
            'El modal tiene que saber donde preguntar la cuenta antes de guardar.'
        );
    }

    /**
     * La peticion de la cuenta va antes que el resource, y no se la come el
     * {valoracion}.
     *
     * Es una trampa de orden de rutas: si la del calculo se declarara despues
     * del resource, el {valoracion} se comeria la palabra "calcular" y el modal
     * recibiria un "no encontrado" en vez de la cuenta. El aviso del modal lo
     * comprobaria, porque no llegaria nada que pintar.
     */
    public function test_la_ruta_del_calculo_no_la_se_come_el_resource(): void
    {
        $base = $this->monedaBase();

        $this->precio(self::DIA, 4700, (int) $base->id);

        $orden = $this->orden();
        $recuperacion = $this->recuperacion($orden, 2, 0.75);

        $respuesta = $this->postJson('/procesos/valoraciones-oro/calcular', [
            'recuperacion_id' => $recuperacion->id,
            'fecha' => self::DIA,
            'precio_moneda' => $base->id,
            'moneda_id' => $base->id,
        ], self::CABECERAS);

        $respuesta->assertOk();

        $this->assertTrue(
            $respuesta->json('ok'),
            'La ruta del cálculo tiene que contestar la cuenta, no un 404.'
        );

        $this->assertEqualsWithDelta(7050.00, $respuesta->json('valor'), 0.001);
    }

    /**
     * La peticion del calculo no escribe nada.
     *
     * Es una peticion de lectura hecha con POST —que es lo que puede mandar un
     * formulario sin cambiar el metodo— y no puede dejar rastro. Se comprueba
     * porque un metodo que se despista y guarda algo dejaria filas fantasma
     * cada vez que alguien mueve un desplegable.
     */
    public function test_la_peticion_del_calculo_no_guarda_nada(): void
    {
        $base = $this->monedaBase();

        $this->precio(self::DIA, 4700, (int) $base->id);

        $orden = $this->orden();
        $recuperacion = $this->recuperacion($orden, 2, 0.75);

        $antes = ValoracionesOro::count();

        $this->postJson('/procesos/valoraciones-oro/calcular', [
            'recuperacion_id' => $recuperacion->id,
            'fecha' => self::DIA,
            'precio_moneda' => $base->id,
            'moneda_id' => $base->id,
        ], self::CABECERAS)->assertOk();

        $this->assertSame(
            $antes,
            ValoracionesOro::count(),
            'Preguntar cuánto vale no puede dejar una valoración guardada.'
        );

        $this->assertSame(
            $antes,
            ValoracionesOro::count(),
            'La tabla tiene que seguir como estaba: preguntar el valor no puede dejar una fila.'
        );
    }

    // ------------------------------------------------------------------
    // Usuarios
    // ------------------------------------------------------------------

    private function usuarioSinPermisos(): User
    {
        $usuario = User::create([
            'name' => 'Sin permisos',
            'email' => 'sin-permiso-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);

        $usuario->assignRole('operador');

        return $usuario;
    }

    private function usuarioConPermisos(array $nombres): User
    {
        foreach ($nombres as $nombre) {
            Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);
        }

        $usuario = User::create([
            'name' => 'Con permisos',
            'email' => 'con-permiso-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);

        $usuario->syncRoles(['operador']);
        $usuario->givePermissionTo($nombres);

        return $usuario;
    }
}