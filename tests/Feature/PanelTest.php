<?php
/**
 * El panel de inicio, de punta a punta.
 *
 * Lo que se comprueba aqui no es que las cifras salgan, que eso lo hace el
 * servicio y sus consultas. Son las cinco cosas que pueden fallar sin que se note,
 * y las cinco estan escritas en el servidor porque se pueden saltar desde cualquier
 * lado.
 *
 * ===========================================================================
 * 1. EL COSTO TOTAL TIENE CUATRO SUMANDOS
 * ===========================================================================
 *
 * Esta es la que mas caro sale si se hace mal, porque no da error: sale un numero.
 *
 * El taller lleva los costos en cuatro sitios y solo uno se llama "costos". La mano
 * de obra esta en trabajos_empleados y la depreciacion en proceso_equipos porque
 * las categorias "Mano de obra", "Materia prima" y "Depreciacion" estan marcadas
 * como automaticas y no salen en el desplegable de la pantalla de costos —si
 * salieran, la misma hora de trabajo se contaria dos veces—. La materia prima sale
 * del almacen y por eso viene del kardex.
 *
 * Sumando solo la tabla que se llama de costos, el total sale en menos de la cuarta
 * parte de lo que es, y no avisa de nada.
 *
 * ===========================================================================
 * 2. EL ESTADO DE UN PROCESO NO ESTA EN LA COLUMNA `estado`
 * ===========================================================================
 *
 * La columna solo vale para decir si el proceso se cancelo a mano. Los otros tres
 * estados se deducen de las fechas, y por eso la columna se queda vieja: hay
 * procesos en la base con `estado` = 1, o sea "pendiente", que tienen fecha de
 * inicio y de fin ya pasada.
 *
 * Si el panel contara con la columna, un taller sin nada a medias apareceria con
 * procesos pendientes. El test mete un proceso con la columna en "pendiente" y las
 * fechas de un trabajo empezado, y comprueba que el panel lo cuenta como en
 * proceso. Si alguien cambia el calculo por un GROUP BY sobre la columna, este test
 * falla.
 *
 * ===========================================================================
 * 3. LO QUE SE QUEDO SIN EQUIVALENTE NO SUMA, Y SE CUENTA
 * ===========================================================================
 *
 * Misma regla que los ingresos: si no hay tipo de cambio, el equivalente en
 * cordobas no se inventa. Pero la fila existe, asi que el panel dice cuantas se
 * quedaron fuera. Un panel que enseña "costo 3.577,96" sin decir que dos filas no
 * entran en esa suma se lee como una suma completa.
 *
 * ===========================================================================
 * 4. LAS LISTAS NO FILTRAN POR PERIODO
 * ===========================================================================
 *
 * Es la diferencia que mas confunde si no se dice, y el test la mira porque es lo
 * que se rompe en silencio. El taller no deja un trabajo a medias porque la orden
 * sea vieja.
 *
 * ===========================================================================
 * 5. SIN SESION NO SE ABRE
 * ===========================================================================
 *
 * Antes "/" estaba fuera del grupo de autenticado, porque era una pagina de
 * bienvenida vacia. Con un panel lleno de cifras de dinero, eso es un agujero, y
 * este test es el que lo tapa.
 *
 * ===========================================================================
 * NUNCA SE CUENTA LA TABLA ENTERA
 * ===========================================================================
 *
 * La base es la de verdad y el taller la usa. assertDatabaseCount pasaria por
 * casualidad mientras el taller no tenga lo que este test necesita, y en cuanto lo
 * tenga empezaria a fallar sin que cambie nada. Cada test compara lo que metio con
 * lo que aparecio, y lo que mete va en la transaccion del test.
 */

namespace Tests\Feature;

use App\Models\Ingreso;
use App\Models\MovimientosCosto;
use App\Models\MovimientosInventario;
use App\Models\OrdenesTrabajo;
use App\Models\ProcesosOrden;
use App\Models\Recuperacione;
use App\Models\TrabajosEmpleado;
use App\Models\User;
use App\Services\PanelDelTaller;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PanelTest extends TestCase
{
    use DatabaseTransactions;

    /** La fecha de hoy, que es contra la que se filtran los periodos. */
    private string $hoy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hoy = now()->toDateString();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'panel-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);

        $this->actingAs($admin);
    }

    // ==================================================================
    // Ayudas
    // ==================================================================

    private function clienteId(): int
    {
        $cliente = DB::table('clientes')->first();

        if ($cliente === null) {
            $this->markTestSkipped('No hay clientes para montar las pruebas del panel.');
        }

        return $cliente->id;
    }

    private function monedaId(): int
    {
        $moneda = DB::table('monedas')->where('estado', 1)->first();

        if ($moneda === null) {
            $this->markTestSkipped('No hay monedas activas para montar las pruebas del panel.');
        }

        return $moneda->id;
    }

    /**
     * Una categoria que se pueda escribir a mano.
     *
     * Se coge una con `automatica` = 0 a proposito: las automaticas son las que el
     * sistema calcula —mano de obra, materia prima, depreciacion— y no se pueden
     * escribir en la pantalla de costos. Meter un costo en una de esas seria una
     * fila que en la vida real no podria existir.
     */
    private function categoriaManualId(): int
    {
        $categoria = DB::table('categorias_costos')->where('automatica', 0)->first();

        if ($categoria === null) {
            $this->markTestSkipped('No hay categorías de costo manuales en el catálogo.');
        }

        return $categoria->id;
    }

    private function etapaId(): int
    {
        $etapa = DB::table('etapas')->first();

        if ($etapa === null) {
            $this->markTestSkipped('No hay etapas para montar las pruebas del panel.');
        }

        return $etapa->id;
    }

    /**
     * Una orden de la prueba, con un codigo unico.
     */
    private function orden(int $estado = OrdenesTrabajo::ESTADO_PENDIENTE, ?string $fecha = null): OrdenesTrabajo
    {
        return OrdenesTrabajo::create([
            OrdenesTrabajo::CODIGO => 'PANEL-' . strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            OrdenesTrabajo::CLIENTE_ID => $this->clienteId(),
            OrdenesTrabajo::FECHA => $fecha ?? $this->hoy,
            OrdenesTrabajo::DESCRIPCION => 'Orden de la prueba del panel',
            OrdenesTrabajo::PESO_MINERAL => 1,
            OrdenesTrabajo::UNIDAD_PESO => 'g',
            OrdenesTrabajo::ESTADO => $estado,
        ]);
    }

    /**
     * Un proceso de la prueba.
     *
     * El `estado` se puede pasar a proposito para comprobar que no se usa: se
     * escribe un valor que no corresponde con las fechas y el panel tiene que dar
     * el que se deduce de ellas.
     */
    private function proceso(
        OrdenesTrabajo $orden,
        ?string $inicio = null,
        ?string $fin = null,
        int $estadoColumna = ProcesosOrden::ESTADO_PENDIENTE
    ): ProcesosOrden {
        return ProcesosOrden::create([
            ProcesosOrden::ORDEN_TRABAJO_ID => $orden->id,
            ProcesosOrden::ETAPA_ID => $this->etapaId(),
            ProcesosOrden::FECHA_INICIO => $inicio,
            ProcesosOrden::FECHA_FIN => $fin,
            ProcesosOrden::ESTADO => $estadoColumna,
            ProcesosOrden::CODIGO => 'P-' . strtoupper(substr(md5(uniqid('', true)), 0, 8)),
        ]);
    }

    /**
     * Un costo escrito a mano, con o sin equivalente en cordobas.
     */
    private function costo(OrdenesTrabajo $orden, float $importe, ?float $equivalente = null): MovimientosCosto
    {
        return MovimientosCosto::create([
            MovimientosCosto::ORDEN_TRABAJO_ID => $orden->id,
            MovimientosCosto::CATEGORIA_COSTO_ID => $this->categoriaManualId(),
            MovimientosCosto::FECHA => $this->hoy,
            MovimientosCosto::DESCRIPCION => 'Costo de la prueba del panel',
            MovimientosCosto::CANTIDAD => 1,
            MovimientosCosto::COSTO_UNITARIO => $importe,
            MovimientosCosto::COSTO_TOTAL => $importe,
            MovimientosCosto::COSTO_UNITARIO_NIO => $equivalente,
            MovimientosCosto::COSTO_TOTAL_NIO => $equivalente,
            MovimientosCosto::MONEDA_ID => $this->monedaId(),
        ]);
    }

    /**
     * Un trabajo de empleado, que va en su propia tabla y no en la de costos.
     */
    private function trabajo(ProcesosOrden $proceso, float $importe, ?float $equivalente = null): TrabajosEmpleado
    {
        $tipoPago = DB::table('tipos_pago_empleado')->first();

        if ($tipoPago === null) {
            $this->markTestSkipped('No hay tipos de pago de empleado para las pruebas del panel.');
        }

        $empleado = DB::table('empleados')->first();

        if ($empleado === null) {
            $this->markTestSkipped('No hay empleados para las pruebas del panel.');
        }

        return TrabajosEmpleado::create([
            TrabajosEmpleado::EMPLEADO_ID => $empleado->id,
            TrabajosEmpleado::PROCESO_ORDEN_ID => $proceso->id,
            TrabajosEmpleado::TIPO_PAGO_ID => $tipoPago->id,
            TrabajosEmpleado::FECHA => $this->hoy,
            TrabajosEmpleado::CANTIDAD => 1,
            TrabajosEmpleado::UNIDAD => 'servicio',
            TrabajosEmpleado::TARIFA => $importe,
            TrabajosEmpleado::TOTAL => $importe,
            TrabajosEmpleado::TARIFA_NIO => $equivalente,
            TrabajosEmpleado::TOTAL_NIO => $equivalente,
            TrabajosEmpleado::MONEDA_ID => $this->monedaId(),
        ]);
    }

    /**
     * El html del panel, con los escapes del unicode deshechos.
     *
     * La configuracion que se le pasa a DataTables y los json que van en los
     * atributos data- son json generados sin JSON_UNESCAPED_UNICODE, con lo que las
     * vocales acentuadas viajan como "u00fc" y no como "u". Buscar en el html tal
     * cual encuentra "Cargando" y no encuentra "gráfica".
     */
    private function panel(?string $periodo = null): string
    {
        $url = '/';

        if ($periodo !== null) {
            $url .= '?periodo=' . $periodo;
        }

        $html = $this->get($url)->assertOk()->getContent();

        return preg_replace_callback(
            '#\\\\u([0-9a-f]{4})#i',
            fn(array $m) => mb_chr((int) hexdec($m[1]), 'UTF-8'),
            $html
        );
    }

    // ==================================================================
    // Que se abre
    // ==================================================================

    public function test_el_panel_se_abre(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_sin_sesion_no_se_abre(): void
    {
        auth()->logout();

        /*
         * "/" estaba FUERA del grupo de autenticado, porque era una pagina de
         * bienvenida vacia y no tenia nada que enseñar. Con un panel lleno de
         * cifras eso es un agujero, y este test es el que lo tapa: si alguien mueve
         * la ruta fuera del grupo otra vez, falla aqui y no en produccion.
         */
        $this->get('/')->assertRedirect();
    }

    public function test_los_tres_roles_abren_el_panel(): void
    {
        foreach (['admin', 'supervisor', 'operador'] as $rol) {
            $usuario = User::create([
                'name' => 'Del rol ' . $rol,
                'email' => 'panel-' . $rol . '-' . uniqid() . '@test.local',
                'password' => Hash::make('prueba-de-prueba'),
            ]);
            $usuario->syncRoles([$rol]);

            $this->actingAs($usuario)->get('/')->assertOk();
        }
    }

    // ==================================================================
    // 1. Los cuatro sumandos del costo
    // ==================================================================

    public function test_el_costo_suma_los_cuatro_sitios_y_no_solo_la_tabla_de_costos(): void
    {
        $orden = $this->orden();
        $proceso = $this->proceso($orden);

        $this->costo($orden, 1000, 1000);
        $this->trabajo($proceso, 2000, 2000);

        $dinero = app(PanelDelTaller::class)->dinero();

        /*
         * Lo que importa no es el total sino que la parte de mano de obra no sea
         * cero. Si alguien quita la mano de obra de la suma, el total baja 2.000 y
         * sigue siendo un numero verosimil: por eso se mira la parte y no solo el
         * total.
         */
        $this->assertSame(2000.0, $dinero['partes']['mano_de_obra']['total']);
        $this->assertSame(1000.0, $dinero['partes']['costos']['total']);

        $this->assertSame(
            3000.0,
            $dinero['partes']['costos']['total']
                + $dinero['partes']['mano_de_obra']['total']
                + $dinero['partes']['depreciacion']['total']
                + $dinero['partes']['materia_prima']['total']
        );
    }

    public function test_los_gramos_de_oro_y_los_finos_no_son_el_mismo_numero(): void
    {
        $orden = $this->orden();

        Recuperacione::create([
            Recuperacione::ORDEN_TRABAJO_ID => $orden->id,
            Recuperacione::FECHA => $this->hoy,
            Recuperacione::GRAMOS => 2,
            Recuperacione::PUREZA => 0.75,
        ]);

        $oro = app(PanelDelTaller::class)->oro();

        /*
         * 2 gramos al 75 % tienen 1,5 gramos de oro fino. Valorar los 2 seria pagar
         * de mas por material que no era oro, que es la razon de que la valoracion
         * tenga su propia regla. Si el panel enseñara solo los gramos, estaria
         * tacitando a valorar el mineral entero.
         */
        $this->assertSame(2.0, $oro['total']);
        $this->assertSame(1.5, $oro['finos']);
        $this->assertSame(1, $oro['conPureza']);
        $this->assertSame(0, $oro['sinPureza']);
    }

    public function test_una_recuperacion_sin_pureza_no_aporta_gramos_finos_ni_cero(): void
    {
        $orden = $this->orden();

        Recuperacione::create([
            Recuperacione::ORDEN_TRABAJO_ID => $orden->id,
            Recuperacione::FECHA => $this->hoy,
            Recuperacione::GRAMOS => 2,
            Recuperacione::PUREZA => null,
        ]);

        $oro = app(PanelDelTaller::class)->oro();

        /*
         * Que no se sepa la pureza y que el oro fuera puro cero son dos cosas
         * distintas. Los finos son cero porque no hay con que multiplicar, y el
         * panel lo dice con "sin medir la pureza" en vez de enseñar un 0,0000 que
         * parece una medida.
         */
        $this->assertSame(2.0, $oro['total']);
        $this->assertSame(0.0, $oro['finos']);
        $this->assertSame(1, $oro['sinPureza']);
    }

    // ==================================================================
    // 2. El estado del proceso se deduce de las fechas
    // ==================================================================

    public function test_el_panel_no_usa_la_columna_de_estado_de_los_procesos(): void
    {
        /*
         * Se mide ANTES de crear nada y se mide DESPUES, y no el numero absoluto.
         *
         * La base tiene procesos del taller y el total depende de cuantos haya, de
         * modo que un assertSame sobre una cifra suelta pasa un dia y falla otro.
         * Y el "antes" tiene que estar cogido antes de crear la fila: si se coge
         * despues, las dos medidas incluyen el proceso nuevo y la diferencia es
         * siempre cero.
         */
        $enProcesoDe = static function (): int {
            return collect((new PanelDelTaller('todo'))->procesosPorEstado())
                ->firstWhere('estado', ProcesosOrden::ESTADO_EN_PROCESO)['cuantas'];
        };

        $pendientesDe = static function (): int {
            return collect((new PanelDelTaller('todo'))->procesosPorEstado())
                ->firstWhere('estado', ProcesosOrden::ESTADO_PENDIENTE)['cuantas'];
        };

        $enProcesoAntes = $enProcesoDe();
        $pendientesAntes = $pendientesDe();

        $orden = $this->orden(OrdenesTrabajo::ESTADO_EN_PROCESO);

        /*
         * La columna dice "pendiente" —1— y las fechas dicen otra cosa: empezo
         * hace doce dias y no tiene fecha de fin. El proceso esta en proceso, y el
         * panel tiene que decirlo asi.
         *
         * Y el periodo es "todo" a proposito. Con el de por defecto, que es "este
         * mes", un proceso que empezo hace doce dias cae fuera del periodo en
         * cuanto el dia del mes es mayor que doce —o sea, casi todos los dias— y
         * el test falla sin que el panel este mal. Un test que depende del dia en
         * que se ejecuta no mide el codigo, mide el calendario.
         */
        $this->proceso(
            $orden,
            inicio: now()->subDays(12)->toDateTimeString(),
            fin: null,
            estadoColumna: ProcesosOrden::ESTADO_PENDIENTE
        );

        $this->assertSame(
            $enProcesoAntes + 1,
            $enProcesoDe(),
            'El proceso que empezó hace doce días y no tiene fin debe contar como en proceso.'
        );

        $this->assertSame(
            $pendientesAntes,
            $pendientesDe(),
            'Con la columna "estado" = 1 el proceso parecería pendiente. La columna no se usa.'
        );
    }

    public function test_un_proceso_con_fecha_de_fin_puesta_para_manana_no_cuenta_como_finalizado(): void
    {
        $orden = $this->orden(OrdenesTrabajo::ESTADO_EN_PROCESO);

        $this->proceso(
            $orden,
            inicio: now()->subDay()->toDateTimeString(),
            fin: now()->addDay()->toDateTimeString(),
            estadoColumna: ProcesosOrden::ESTADO_PENDIENTE
        );

        /*
         * Un proceso planificado para manana no va tarde hoy. Marcarlo como
         * terminado antes de tiempo seria decir que el taller acabo algo que no ha
         * acabado, y es justo lo que se vino a arreglar cuando el estado paso a
         * deducirse de las fechas.
         *
         * Y se mide la diferencia y no el numero absoluto, porque la base tiene
         * procesos del taller y el total depende de cuantos haya.
         */
        $antes = collect((new PanelDelTaller('todo'))->procesosPorEstado())->keyBy('estado');

        $this->proceso(
            $orden,
            inicio: now()->subDay()->toDateTimeString(),
            fin: now()->addDay()->toDateTimeString(),
            estadoColumna: ProcesosOrden::ESTADO_PENDIENTE
        );

        $despues = collect((new PanelDelTaller('todo'))->procesosPorEstado())->keyBy('estado');

        $this->assertSame(
            $antes[ProcesosOrden::ESTADO_EN_PROCESO]['cuantas'] + 1,
            $despues[ProcesosOrden::ESTADO_EN_PROCESO]['cuantas']
        );

        $this->assertSame(
            $antes[ProcesosOrden::ESTADO_FINALIZADO]['cuantas'],
            $despues[ProcesosOrden::ESTADO_FINALIZADO]['cuantas'],
            'Un proceso que termina mañana no puede contar como finalizado.'
        );
    }

    public function test_el_avance_de_la_orden_no_cuenta_como_terminado_lo_que_termina_en_el_futuro(): void
    {
        $orden = $this->orden(OrdenesTrabajo::ESTADO_EN_PROCESO);

        $this->proceso($orden, now()->subDays(3)->toDateTimeString(), now()->subDay()->toDateTimeString());
        $this->proceso($orden, now()->toDateTimeString(), now()->addDays(2)->toDateTimeString());

        $abierta = collect(app(PanelDelTaller::class)->ordenesAbiertas())
            ->firstWhere('id', $orden->id);

        $this->assertNotNull($abierta);
        $this->assertSame(2, (int) $abierta->procesos_total);
        $this->assertSame(
            1,
            (int) $abierta->procesos_terminados,
            'Solo uno de los dos ha terminado de verdad: el otro termina dentro de dos días.'
        );
    }

    // ==================================================================
    // 3. Lo que se quedó sin equivalente
    // ==================================================================

    public function test_un_costo_sin_tipo_de_cambio_no_suma_pero_se_cuenta(): void
    {
        $orden = $this->orden();

        $this->costo($orden, 1000, 1000);
        $this->costo($orden, 555, null);

        $dinero = app(PanelDelTaller::class)->dinero();

        /*
         * El 555 no se inventa. Y el numero de filas sin equivalente sube, porque
         * un panel que enseña "1.000" sin decir que hay otra fila de 555 que no ha
         * entrado se lee como una suma completa cuando no lo es.
         */
        $this->assertSame(1000.0, $dinero['partes']['costos']['total']);
        $this->assertSame(2, $dinero['partes']['costos']['filas']);
        $this->assertSame(1, $dinero['partes']['costos']['sinEquivalente']);
        $this->assertGreaterThanOrEqual(1, $dinero['costo_sin_equivalente']);
    }

    public function test_el_panel_avisa_de_las_filas_sin_equivalente_en_la_pantalla(): void
    {
        $orden = $this->orden();
        $this->costo($orden, 555, null);

        $html = $this->panel();

        /*
         * El aviso tiene que SALTAR en la pantalla, no solo existir en el servicio.
         * Un numero que se calcula bien y que nadie ve es un numero que no existe
         * para el que lo necesita.
         */
        $this->assertStringContainsString('sin tipo de cambio', $html);
        $this->assertStringContainsString('no suma', $html);
    }

    // ==================================================================
    // 4. El periodo
    // ==================================================================

    public function test_un_periodo_inventado_cae_en_el_que_hay_por_defecto(): void
    {
        $panel = new PanelDelTaller('lo-que-sea');

        /*
         * El parametro viene de la url, y una url se puede escribir a mano. Si un
         * periodo desconocido tirara una excepcion, el panel devolveria un error 500
         * por buscar "?periodo=ayer".
         */
        $this->assertSame('mes', $panel->periodo());
    }

    public function test_las_cifras_cambian_con_el_periodo_y_las_listas_no(): void
    {
        $esteMes = new PanelDelTaller('mes');
        $esteAno = new PanelDelTaller('anio');
        $todo = new PanelDelTaller('todo');

        $enProcesoDe = static function (PanelDelTaller $panel): int {
            return collect($panel->procesosPorEstado())
                ->firstWhere('estado', ProcesosOrden::ESTADO_EN_PROCESO)['cuantas'];
        };

        $antesMes = $enProcesoDe($esteMes);
        $antesAno = $enProcesoDe($esteAno);
        $antesTodo = $enProcesoDe($todo);
        $atrasadosAntes = $todo->procesosAtrasados()->count();

        /*
         * Un proceso que empezo dentro de este mes y sigue sin terminar.
         *
         * La fecha se calcula a partir del mes, no con "hace X dias", porque hace X
         * dias cae fuera del mes en cuanto el dia del mes es mayor que X. Y el dia
         * 1 no hay ningun dia del mes que ya haya pasado: por eso se usa el dia 1
         * del mes, que siempre esta dentro del periodo y siempre esta en el pasado
         * o en el mismo dia.
         */
        $orden = $this->orden(OrdenesTrabajo::ESTADO_EN_PROCESO);

        $this->proceso(
            $orden,
            inicio: now()->startOfMonth()->toDateTimeString(),
            fin: null
        );

        $this->assertSame(
            $antesMes + 1,
            $enProcesoDe($esteMes),
            'Un proceso empezado este mes tiene que sumar en el periodo de este mes.'
        );

        $this->assertSame(
            $antesAno + 1,
            $enProcesoDe($esteAno),
            'Y tambien en el del año, porque este mes esta dentro de este año.'
        );

        $this->assertSame($antesTodo + 1, $enProcesoDe($todo));

        // Y la lista de atrasados sale igual en los tres periodos
        $this->assertSame(
            $atrasadosAntes + 1,
            $esteMes->procesosAtrasados()->count(),
            'Las listas son de ahora mismo y no pueden cambiar con el periodo.'
        );

        $this->assertSame($atrasadosAntes + 1, $esteAno->procesosAtrasados()->count());
    }

    public function test_la_pantalla_dice_que_las_listas_no_cambian_con_el_periodo(): void
    {
        $this->panel();

        /*
         * Sin esta linea, el taller cambia el periodo un dia, ve que las listas de
         * abajo no se mueven y piensa que el panel esta roto. Que avise es mas
         * barato que un parte de "el filtro no funciona".
         */
        $html = $this->panel();

        $this->assertStringContainsString('no</strong> cambian con el periodo', $html);
    }

    public function test_el_periodo_este_mes_no_cuenta_lo_del_mes_pasado(): void
    {
        $mes = new PanelDelTaller('mes');
        $anio = new PanelDelTaller('anio');

        /*
         * 1. El punto de partida, antes de meter nada.
         *
         * La base tiene costos del taller que no tocan, de modo que el total
         * absoluto no es un numero que este test pueda saber. Lo que se mira es la
         * diferencia con y sin la fila, y por eso el "antes" se coge antes de
         * crearla: si se coge despues, las dos medidas la incluyen y la diferencia
         * es siempre cero.
         *
         * Y se coge el "antes" de los DOS periodos, porque el taller tiene costos
         * del mes pasado —que el año ve y el mes no— y comparar el total del año
         * con el total del mes seria comparar dos cosas distintas y concluir que
         * el filtro falla.
         */
        $antes = $mes->dinero()['partes']['costos'];
        $antesAnio = $anio->dinero()['partes']['costos'];

        $orden = $this->orden();
        $costo = $this->costo($orden, 1000, 1000);

        // 2. Con la fecha de hoy, el costo esta en el mes y suma.
        $this->assertSame(
            $antes['total'] + 1000,
            $mes->dinero()['partes']['costos']['total'],
            'Un costo con la fecha de hoy tiene que sumar en la cuenta de este mes.'
        );

        // 3. Y en cuanto su fecha sale del mes, deja de sumar.
        $costo->update([MovimientosCosto::FECHA => now()->subMonths(2)->toDateString()]);

        /*
         * Con el filtro por mes, un costo de hace dos meses sale de la cuenta de
         * este. Si el filtro no se aplicara, el taller veria el gasto de hace dos
         * meses en el total de este y no sabria de donde sale la diferencia.
         */
        $this->assertSame(
            $antes['total'],
            $mes->dinero()['partes']['costos']['total'],
            'Un costo con la fecha de hace dos meses no tiene que sumar en este mes.'
        );

        // Y el total del año si lo ve, porque hace dos meses esta dentro del año
        $this->assertSame(
            $antesAnio['total'] + 1000,
            $anio->dinero()['partes']['costos']['total'],
            'Y el periodo del año tiene que seguir viendo la fila: es la diferencia entre los dos periodos.'
        );
    }

    public function test_el_periodo_todo_no_cuenta_lo_que_no_existe_todavia(): void
    {
        $todo = new PanelDelTaller('todo');

        $antes = $todo->dinero()['partes']['costos'];

        $orden = $this->orden();
        $costo = $this->costo($orden, 1000, 1000);

        // Con fecha de hoy, el costo SI cuenta
        $this->assertSame(
            $antes['total'] + 1000,
            $todo->dinero()['partes']['costos']['total'],
            'Un costo con la fecha de hoy tiene que sumar.'
        );

        $costo->update([MovimientosCosto::FECHA => now()->addDays(3)->toDateString()]);

        /*
         * Y con fecha de futuro deja de contar, incluso en el periodo "todo".
         *
         * El limite de arriba es siempre hoy. Una fila con fecha de futuro es un
         * error de tecleo, y sumarla haria que un mes que todavia no ha pasado
         * enseñara dinero que no se ha gastado.
         *
         * Y se compara con lo que habia antes y no con cero, porque la base tiene
         * costos del taller que no tocan. Comparar contra cero en una tabla que el
         * taller esta usando no mide el codigo: pide que el taller no tenga datos.
         */
        $despues = $todo->dinero()['partes']['costos'];

        $this->assertSame($antes['total'], $despues['total']);
        $this->assertSame($antes['filas'], $despues['filas']);
    }

    // ==================================================================
    // 5. Las graficas y los datos que viajan
    // ==================================================================

    public function test_las_graficas_reciben_sus_datos_en_el_html(): void
    {
        $this->orden();

        $html = $this->panel();

        /*
         * Los datos van en atributos data- y no por ajax, y hay que comprobar que
         * viajan: una gráfica que espera un fetch que nadie hace se queda con el
         * texto de "cargando" para siempre, y eso no da error de javascript.
         */
        $this->assertStringContainsString('data-panel-ordenes=', $html);
        $this->assertStringContainsString('data-panel-serie=', $html);
        $this->assertStringContainsString('id="graficaEstados"', $html);
        $this->assertStringContainsString('id="graficaMeses"', $html);
    }

    public function test_los_botones_del_periodo_salen_los_tres_y_marca_el_que_esta(): void
    {
        $html = $this->panel('anio');

        foreach (['Este mes', 'Este año', 'Todo'] as $texto) {
            $this->assertStringContainsString($texto, $html);
        }

        $this->assertStringContainsString('periodo=mes', $html);
        $this->assertStringContainsString('periodo=todo', $html);

        // Y el que esta pulsado es el de la url, marcado con aria-current
        $this->assertStringContainsString('aria-current="page"', $html);
    }

    // ==================================================================
    // Lo que la pantalla enseña
    // ==================================================================

    public function test_los_numeros_salen_con_el_formato_de_nicaragua(): void
    {
        $orden = $this->orden();
        $this->costo($orden, 1546.5, 1546.5);

        $html = $this->panel();

        /*
         * Coma en los miles y punto en los decimales. Es el formato de Nicaragua y
         * es el que hace php con number_format a secas, que es como lo escriben
         * todas las tablas de la aplicacion. Si aqui saliera 1.546,50 el taller
         * veria el numero de dos maneras en la misma pagina.
         */
        $this->assertStringContainsString('1,546.50', $html);
        $this->assertStringNotContainsString('1.546,50', $html);
    }

    public function test_el_costo_no_aparece_con_un_margen_al_lado(): void
    {
        $html = $this->panel();

        /*
         * No hay margen, y no es un olvido. Lo que entra y lo que sale son numeros
         * de sistemas distintos: la mano de obra no se le cobra al cliente como un
         * porcentaje del oro, y restarlos daria un margen que en este taller no
         * significa nada. Las dos sumas estan a la vista una al lado de la otra,
         * que es como se ve un margen si se quiere ver.
         */
        $this->assertStringNotContainsString('Margen', $html);
        $this->assertStringNotContainsString('margen', $html);
    }

    public function test_la_pantalla_no_enseña_el_costo_total_del_taller_cuando_no_hay_cifras(): void
    {
        $html = $this->panel();

        /*
         * Con la base vacia el panel tiene que abrirse y mostrar ceros, no reventar.
         * Un panel que sale en blanco cuando no hay datos es un panel que el taller
         * ve roto la primera vez que lo abre con la base recien puesta.
         */
        $this->assertStringContainsString('Costo total', $html);
        $this->assertStringContainsString('C$ 0.00', $html);
        $this->assertStringContainsString('Ingreso facturado', $html);
        $this->assertStringContainsString('Oro recuperado', $html);
    }

    public function test_las_listas_vacias_dicen_una_cosa_concreta(): void
    {
        $html = $this->panel();

        /*
         * "No hay datos" no dice que hacer. Un proceso atrasado que no hay ninguno es
         * una buena noticia y se dice: nada se quedo a medias.
         */
        $this->assertStringContainsString('Nada se quedó a medias.', $html);
    }

    public function test_los_estados_desconocidos_no_se_esconden(): void
    {
        $orden = $this->orden();

        /*
         * Se escribe un estado que no esta en el catalogo. El panel tiene que
         * decirlo, no esconder esa orden: si el modelo cambia y alguien mete un
         * estado nuevo, un panel que lo oculta diremia que no hay nada.
         */
        $orden->update([OrdenesTrabajo::ESTADO => 9]);

        $html = $this->panel();

        $this->assertStringContainsString('Desconocido', $html);
    }
}
