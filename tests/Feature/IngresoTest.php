<?php
/**
 * Los ingresos de una orden, de punta a punta.
 *
 * Lo que se comprueba aqui no es que la pantalla exista, que eso lo comprueban
 * otros tests. Son las cuatro cosas que aqui pueden fallar sin que se note, y
 * las cuatro estan escritas en el servidor y no en el formulario porque se
 * pueden saltar desde cualquier lado.
 *
 * EL TOTAL NO SE ESCRIBE, SE CALCULA. Sale de multiplicar la cantidad por el
 * precio unitario. Si se recibiera ya calculado, un valor equivocado en el
 * formulario se guardaria como si fuera verdad y no habria forma de saber que
 * no cuadra con la multiplicacion. Y esto vale mas aqui que en los costos,
 * porque el total de los ingresos es dinero que el taller le esta cobrando al
 * cliente. El test manda un total inventado y comprueba que no se guarda.
 *
 * Y CON LA MONEDA BASE EL TIPO DE CAMBIO ES UNO, y no ausencia de tipo de
 * cambio. Es la diferencia entre esta pantalla y el resto del sistema, y va
 * escrito porque si no el total en cordoba de una orden facturada en cordoba
 * saldria vacio, la suma de abajo no cuadraria con las filas que se ven, y no
 * habria ni un aviso que dijera por que.
 *
 * EL TIPO DE CAMBIO ES EL DE LA FECHA DEL INGRESO, no el de hoy ni el ultimo
 * de la tabla. Eso es lo que mas caro sale en un taller que cobra en dolares y
 * lleva la contabilidad en cordoba: un ingreso del dia 3 convertido con el
 * cambio de hoy cambia lo facturado de toda la orden, y el error no se ve
 * hasta el trimestre siguiente. El test pone un tipo de cambio DESPUES de la
 * fecha del ingreso y comprueba que ese no se aplica, que es justo el fallo.
 *
 * Y SI NO HAY TIPO DE CAMBIO, EL INGRESO SE GUARDA IGUAL Y SE DICE POR QUE. El
 * total en la moneda del ingreso si se sabe; lo que no se encuentra es la
 * conversion. Un ingreso guardado sin el equivalente y sin avisar parece un
 * ingreso de cero, asi que el equivalente se queda vacio pero el aviso explica
 * lo que pasa, y la ficha cuenta cuantos se quedaron fuera de la suma.
 *
 * LAS FECHAS DE LAS PRUEBAS.
 *
 * El tipo de cambio va en junio de 2090, como en las pruebas del catalogo, y el
 * ingreso en el 2090-08-01. Es de 2090 y no de 2026 por dos motivos:
 *
 *  - Para no chocar con los datos de verdad. El taller carga el tipo de cambio
 *    del mes que esta viviendo a mano, y un test que usara las mismas fechas se
 *    meteria en ellas.
 *
 *  - Y para que exista una fecha SIN tipo de cambio que se pueda probar. La
 *    busqueda toma el ultimo registro anterior a la fecha pedida, con lo que
 *    cualquier fecha posterior a los datos del taller los encuentra siempre. El
 *    2089 esta antes de todos, y ahi no hay nada.
 *
 * Y NUNCA SE CUENTA LA TABLA ENTERA. La base es la de verdad y el taller la
 * usa: assertDatabaseCount pasaria por casualidad mientras el taller no tenga
 * ingresos, y en cuanto tenga uno empezaria a fallar sin que cambie nada. Cada
 * test compara las filas que ha creado el mismo.
 */

namespace Tests\Feature;

use App\Models\Ingreso;
use App\Models\Moneda;
use App\Models\OrdenesTrabajo;
use App\Models\TiposCambio;
use App\Models\TiposIngreso;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class IngresoTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * El mes en que se cargan los tipos de cambio de la prueba.
     *
     * El mismo que usan las pruebas del catalogo de tipos de cambio, por lo
     * mismo: las fechas reales las cargo el taller a mano.
     */
    private const MES_CAMBIO = '2090-06';

    /** El dia del ingreso de la prueba. */
    private const DIA = '2090-08-01';

    /*
     * NO HAY NINGUNA FECHA "SIN NADA", y se que antes la habia.
     *
     * La idea era buscar una fecha tan antigua que no hubiera ningun tipo de
     * cambio antes, y se puso el 2089 pensando que estaba antes que los datos
     * del taller. Esta al reves: el taller carga los tipos de cambio de 2026, y
     * la busqueda toma "el ultimo registro anterior a la fecha pedida", con lo
     * que en el 2089 encuentra el de septiembre de 2026. La prueba daba error
     * con un valor de verdad —36,6243—, que es la senal de que la fecha estaba
     * mal elegida.
     *
     * Y bajarla mas no arregla nada, porque dejaria de funcionar el dia que
     * el taller cargara un tipo de cambio de 1990.
     *
     * Lo que se usa es una MONEDA QUE NO TIENE TIPOS DE CAMBIO, que es el caso
     * real: un tipo nuevo que todavia no se ha cargado. Asi el test depende de
     * la prueba y no de que fechas haya en la base.
     */

    private const CABECERAS = [
        'X-Requested-With' => 'XMLHttpRequest',
        'Accept' => 'application/json',
    ];

    private OrdenesTrabajo $orden;
    private Moneda $base;
    private Moneda $extranjera;
    private TiposIngreso $tipo;

    /** Cuantos ingresos habia antes de que esta prueba empezara. */
    private int $ingresosIniciales;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ingresosIniciales = Ingreso::withTrashed()->count();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'ingreso-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        $this->actingAs($admin);

        $this->orden = OrdenesTrabajo::firstOrFail();

        $this->base = Moneda::where('es_moneda_base', true)->firstOrFail();
        $this->extranjera = Moneda::where('es_moneda_base', false)
            ->where('estado', true)
            ->firstOrFail();

        /*
         * El tipo sale del catalogo del taller, no se crea uno.
         *
         * Los tipos de ingreso los puso el taller a mano y son decision suya
         * cuales hay. Un test que rellenara el catalogo se metería en la
         * pantalla de configuración del taller y le añadiría filas que él no
         * pidió; además de no ser asunto del test, es tocarle los datos. Si no
         * hubiera ninguno habría que mirar otra cosa, pero hay cuatro.
         */
        $this->tipo = TiposIngreso::paraRegistrar()->firstOrFail();
    }

    // ==================================================================
    // Ayudas
    // ==================================================================

    /**
     * El texto del aviso de la ultima accion, el que se ve en pantalla.
     *
     * El paquete de sweet-alert lo guarda en la sesion bajo la clave "alert",
     * y dentro hay un array cuyas claves son el tipo del aviso. Cada valor es
     * un json, y ese json tiene dentro otro "config" con los datos: el texto
     * largo que escribe el controlador va en "title", porque el aviso se pinta
     * como un toast con una sola linea.
     *
     *   alert['config'] = {"config": {"title": "Ingreso registrado: ..."},
     *                       "type": "config"}
     *
     * Se recorren todas las entradas y en todas las claves del config, en vez
     * de leer "config" y "title" a pelo: el tipo cambia segun si el aviso es un
     * toast o un alert, y porque el texto largo se puede mandar como "message"
     * o como "title" segun como se llame al metodo. Con esto se lee de las dos
     * maneras.
     */
    private function mensajeDelAviso(): string
    {
        $alert = session('alert');

        if (! is_array($alert)) {
            return '';
        }

        foreach ($alert as $envoltorio) {
            $envoltorio = is_array($envoltorio)
                ? $envoltorio
                : json_decode((string) $envoltorio, true);

            if (! is_array($envoltorio)) {
                continue;
            }

            $datos = $envoltorio['config'] ?? $envoltorio;

            if (! is_array($datos)) {
                continue;
            }

            foreach (['title', 'message'] as $clave) {
                if (! empty($datos[$clave])) {
                    return (string) $datos[$clave];
                }
            }
        }

        return '';
    }

    /**
     * La url de una accion de los ingresos.
     *
     * El "edit" va DETRAS del ingreso y los demas delante, porque es como las
     * tiene el resource: /ingresos/create, /ingresos/193, /ingresos/193/edit.
     * Con todo delante, la del edit salia /ingresos/edit/193, que no existe, y
     * el test fallaba con un "Undefined array key id" en vez de decir que la
     * ruta no existe —que es lo que habria que arreglar si el fallo fuera de
     * verdad.
     */
    private function ruta(string $accion = '', ?Ingreso $ingreso = null): string
    {
        $url = "/procesos/ordenes-trabajo/{$this->orden->id}/ingresos";

        // La barra se quita, porque se llama con "/edit" y con "edit"
        $accion = ltrim($accion, '/');

        if ($accion === 'edit') {
            return $url . '/' . ($ingreso?->id ?? '') . '/edit';
        }

        if ($accion !== '') {
            $url .= '/' . $accion;
        }

        if ($ingreso !== null) {
            $url .= '/' . $ingreso->id;
        }

        return $url;
    }

    /**
     * Los datos de un alta, con lo que viene del formulario y solo eso.
     *
     * Deliberadamente NO incluye total, total_nio ni precio_unitario_nio: si el
     * formulario no los manda, el servidor los tiene que poner. Un test que los
     * mandara estaria probando una pantalla en la que el total se escribe, que
     * es justo lo que no puede ser.
     */
    private function datos(array $cambia = []): array
    {
        return array_merge([
            'tipo_ingreso_id' => $this->tipo->id,
            'fecha' => self::DIA,
            'descripcion' => 'Prueba',
            'cantidad' => 10,
            'unidad_medida' => 'g',
            'precio_unitario' => 50,
            'moneda_id' => $this->base->id,
            'observaciones' => '',
        ], $cambia);
    }

    /**
     * Una moneda que no tiene ningun tipo de cambio.
     *
     * Es el caso real de "no se encuentra el tipo de cambio": un tipo nuevo que
     * el taller todavia no ha cargado. Y es la unica forma de probarlo sin
     * depender de las fechas que haya en la base, que es lo que pasaba al
     * buscar una fecha antigua.
     *
     * El codigo son tres letras, que es lo que admite la columna, y lleva un
     * hash porque en monedas SI hay indice unico del codigo —es el ISO— y dos
     * pruebas a la vez no pueden poner el mismo. Con cuatro caracteres fallaba
     * con un "Data too long for column 'codigo'", que no dice nada del codigo
     * ni de la longitud si no se mira la consulta.
     */
    private function monedaSinCambio(): Moneda
    {
        return Moneda::create([
            'codigo' => strtoupper(substr(md5(uniqid('', true)), 0, 3)),
            'nombre' => 'Moneda de la prueba sin tipo de cambio',
            'simbolo' => '?',
            'es_moneda_base' => false,
            'estado' => true,
        ]);
    }

    /**
     * Crea un tipo de cambio para la prueba.
     *
     * Con fecha del MES_CAMBIO, que es donde trabajan las pruebas del catalogo.
     */
    private function tipoCambio(string $fecha, float $valor, ?Moneda $moneda = null): TiposCambio
    {
        return TiposCambio::create([
            TiposCambio::MONEDA_ID => ($moneda ?? $this->extranjera)->id,
            TiposCambio::FECHA => $fecha,
            TiposCambio::VALOR => $valor,
        ]);
    }

    /**
     * Un ingreso creado por la propia prueba, guardado directo.
     *
     * Para los tests que necesitan una fila previa —corregirla, borrarla, o
     * mirar el total de la ficha— y no quieren pasar por el formulario, que ya
     * tiene su propio test.
     */
    private function ingreso(array $cambia = []): Ingreso
    {
        $datos = $this->datos($cambia);

        $ingreso = new Ingreso();
        $ingreso->orden_trabajo_id = $this->orden->id;
        $ingreso->tipo_ingreso_id = $datos['tipo_ingreso_id'];
        $ingreso->fecha = $datos['fecha'];
        $ingreso->descripcion = $datos['descripcion'];
        $ingreso->cantidad = $datos['cantidad'];
        $ingreso->unidad_medida = $datos['unidad_medida'];
        $ingreso->precio_unitario = $datos['precio_unitario'];
        $ingreso->moneda_id = $datos['moneda_id'];
        $ingreso->estado = 1;
        $ingreso->observaciones = $datos['observaciones'];

        /*
         * El tipo de cambio se calcula aqui con la misma regla que el
         * servidor: uno con la moneda base, y el de la fecha con las demas.
         *
         * Estaba puesto a 1.0 fijo, y con eso los tests de moneda extranjera
         * guardaban el equivalente igual al total. Los dos tests del total de
         * la ficha sumaban cifras que no eran las que habian creado y
         * fallaban por una razon que no tenia nada que ver con lo que
         * comprobaban.
         */
        $ingreso->calcularTotales(
            (int) $ingreso->moneda_id === (int) $this->base->id
                ? 1.0
                : TiposCambio::vigentePara((int) $ingreso->moneda_id, (string) $ingreso->fecha)
        );

        $ingreso->save();

        return $ingreso;
    }

    /**
     * Cuantos ingresos creo esta prueba.
     *
     * Cuenta tambien los dados de baja, para que un borrado mal hecho tampoco
     * pase por alto: lo que importa es cuantas filas toco el test.
     */
    private function ingresosCreados(): int
    {
        return Ingreso::withTrashed()->count() - $this->ingresosIniciales;
    }

    /**
     * El ingreso que se acaba de crear, cogido por sus propios datos y no con
     * first().
     *
     * La base es la de verdad y el taller la usa: si un dia tiene ingresos
     * propios, "el primero" seria suyo y el test estaria mirando su fila sin
     * querer.
     */
    private function guardado(string $fecha = self::DIA): Ingreso
    {
        return Ingreso::where('orden_trabajo_id', $this->orden->id)
            ->where('fecha', $fecha)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    // ==================================================================
    // El total
    // ==================================================================

    public function test_el_total_se_calcula_en_el_servidor_y_no_se_acepta_del_formulario(): void
    {
        $this->post(
            $this->ruta(),
            $this->datos([
                'cantidad' => 10,
                'precio_unitario' => 50,
                // Lo que un formulario manipulado intentaria colar
                'total' => 999999,
                'total_nio' => 999999,
                'precio_unitario_nio' => 999999,
            ])
        )->assertRedirect(route('procesos.ordenes_trabajo.show', $this->orden));

        $ingreso = $this->guardado();

        $this->assertSame(500.0, (float) $ingreso->total);

        /*
         * Y con la base el equivalente es el mismo numero, no 999999. Es la
         * comprobacion de que los tres campos se calcularon aqui.
         */
        $this->assertSame(500.0, (float) $ingreso->total_nio);
        $this->assertSame(50.0, (float) $ingreso->precio_unitario_nio);
    }

    public function test_el_total_redondea_a_dos_decimales(): void
    {
        $this->post($this->ruta(), $this->datos([
            'cantidad' => 3.3333,
            'precio_unitario' => 7.77,
        ]));

        // 3.3333 x 7.77 = 25.899741
        $this->assertSame(25.9, (float) $this->guardado()->total);
    }

    public function test_el_precio_unitario_nio_tiene_cuatro_decimales_y_el_total_nio_dos(): void
    {
        $this->tipoCambio(self::MES_CAMBIO . '-01', 36.123456);

        $this->post($this->ruta(), $this->datos([
            'moneda_id' => $this->extranjera->id,
            'cantidad' => 2,
            'precio_unitario' => 10,
        ]));

        $ingreso = $this->guardado();

        // El unitario sale a 4 decimales: 361.2346
        $this->assertSame(361.2346, (float) $ingreso->precio_unitario_nio);

        // El total, que aqui es justo, sale a 2: 722.47
        $this->assertSame(722.47, (float) $ingreso->total_nio);
    }

    public function test_una_cantidad_con_mas_de_cuatro_decimales_no_pierde_precision_al_total(): void
    {
        $this->post($this->ruta(), $this->datos([
            'cantidad' => 0.0001,
            'precio_unitario' => 1000,
        ]));

        $this->assertSame(0.1, (float) $this->guardado()->total);
    }

    // ==================================================================
    // El tipo de cambio
    // ==================================================================

    public function test_con_la_moneda_base_el_equivalente_es_el_mismo_numero(): void
    {
        $this->tipoCambio(self::MES_CAMBIO . '-01', 36.5);

        $this->post($this->ruta(), $this->datos([
            'moneda_id' => $this->base->id,
            'cantidad' => 4,
            'precio_unitario' => 125,
        ]));

        $ingreso = $this->guardado();

        $this->assertSame(500.0, (float) $ingreso->total);
        $this->assertSame(500.0, (float) $ingreso->total_nio);
        $this->assertSame(125.0, (float) $ingreso->precio_unitario_nio);
    }

    public function test_el_tipo_de_cambio_es_el_de_la_fecha_del_ingreso_y_no_el_ultimo_de_la_tabla(): void
    {
        /*
         * El de la fecha, en junio, que es el que le toca al ingreso de agosto.
         */
        $this->tipoCambio(self::MES_CAMBIO . '-01', 36.0);

        /*
         * Y otro DESPUES, en septiembre. Este es el que se colaria si la
         * busqueda tomara el ultimo de la tabla en vez del ultimo anterior a la
         * fecha, que es el fallo que este test existe para cazar.
         */
        $this->tipoCambio('2090-09-01', 40.0);

        $this->post($this->ruta(), $this->datos([
            'moneda_id' => $this->extranjera->id,
            'cantidad' => 1,
            'precio_unitario' => 100,
        ]));

        $ingreso = $this->guardado();

        $this->assertSame(3600.0, (float) $ingreso->total_nio);
        $this->assertNotSame(4000.0, (float) $ingreso->total_nio);
    }

    public function test_sin_tipo_de_cambio_el_ingreso_se_guarda_y_el_equivalente_queda_vacio(): void
    {
        $antes = $this->ingresosIniciales;

        $this->post($this->ruta(), $this->datos([
            'moneda_id' => $this->monedaSinCambio()->id,
            'cantidad' => 5,
            'precio_unitario' => 20,
        ]))->assertRedirect(route('procesos.ordenes_trabajo.show', $this->orden));

        // El ingreso se guardo: el total en su moneda si se sabe.
        $this->assertSame($antes + 1, $this->ingresosCreados());

        $ingreso = $this->guardado();

        $this->assertSame(100.0, (float) $ingreso->total);
        $this->assertNull($ingreso->total_nio);
        $this->assertNull($ingreso->precio_unitario_nio);
    }

    public function test_sin_tipo_de_cambio_el_aviso_dice_que_no_cuenta_en_el_total(): void
    {
        $this->post($this->ruta(), $this->datos([
            'moneda_id' => $this->monedaSinCambio()->id,
        ]));

        /*
         * El aviso es lo que evita que un ingreso sin equivalente parezca un
         * ingreso de cero. Se comprueba que lo dice, y no solo que hay un
         * aviso: un aviso generico deja al usuario igual que sin aviso.
         */
        $mensaje = $this->mensajeDelAviso();

        $this->assertStringContainsString(
            'tipo de cambio',
            $mensaje,
            'El aviso no menciona que no se encontró el tipo de cambio.'
        );

        // Y que dice que el equivalente se quedo vacio, que es la consecuencia
        $this->assertStringContainsString('equivalente', $mensaje);
    }

    public function test_el_equivalente_guardado_no_se_recalcula_al_leer(): void
    {
        $this->tipoCambio(self::MES_CAMBIO . '-01', 36.0);

        $this->post($this->ruta(), $this->datos([
            'moneda_id' => $this->extranjera->id,
            'cantidad' => 1,
            'precio_unitario' => 100,
        ]));

        $ingreso = $this->guardado();
        $antes = (float) $ingreso->total_nio;

        $this->assertSame(3600.0, $antes);

        /*
         * Ahora se corrige el tipo de cambio de ese dia, como pasaria si el
         * banco publicara un valor distinto al que se cogio. El ingreso es un
         * documento, no una consulta: no se mueve.
         */
        TiposCambio::where('fecha', self::MES_CAMBIO . '-01')
            ->where('moneda_id', $this->extranjera->id)
            ->update(['valor' => 99.0]);

        $ingreso->refresh();

        $this->assertSame($antes, (float) $ingreso->total_nio);
    }

    public function test_cambiar_la_fecha_cambia_el_tipo_de_cambio_al_corregir(): void
    {
        $this->tipoCambio(self::MES_CAMBIO . '-01', 36.0);
        $this->tipoCambio('2090-08-15', 37.5);

        $this->post($this->ruta(), $this->datos([
            'moneda_id' => $this->extranjera->id,
            'cantidad' => 1,
            'precio_unitario' => 100,
        ]));

        $ingreso = $this->guardado();
        $this->assertSame(3600.0, (float) $ingreso->total_nio);

        /*
         * Se corrige la fecha a una en la que ya hay otro tipo de cambio.
         *
         * La cantidad y el precio van tambien, y no por sofisticacion: al
         * corregirse un ingreso se manda el formulario entero, y si aqui solo
         * se mandan la fecha y la moneda, lo que falte vuelve a los valores por
         * defecto de datos() y el total sale de otra multiplicacion. El
         * servidor hizo bien la cuenta con lo que le llego.
         */
        $this->put(
            $this->ruta('', $ingreso),
            $this->datos([
                'moneda_id' => $this->extranjera->id,
                'fecha' => '2090-08-20',
                'cantidad' => 1,
                'precio_unitario' => 100,
            ])
        )->assertRedirect(route('procesos.ordenes_trabajo.show', $this->orden));

        $ingreso->refresh();

        $this->assertSame(3750.0, (float) $ingreso->total_nio);
    }

    // ==================================================================
    // Lo que no se admite
    // ==================================================================

    public function test_una_cantidad_cero_no_se_admite_y_el_aviso_dice_que_haga(): void
    {
        $antes = $this->ingresosIniciales;

        $response = $this->postJson($this->ruta(), $this->datos(['cantidad' => 0]));

        $response->assertStatus(422)->assertJsonValidationErrors('cantidad');

        $this->assertSame($antes, $this->ingresosCreados());
    }

    public function test_una_cantidad_negativa_no_se_admite(): void
    {
        $this->postJson($this->ruta(), $this->datos(['cantidad' => -5]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('cantidad');
    }

    public function test_un_precio_unitario_negativo_no_se_admite(): void
    {
        $this->postJson($this->ruta(), $this->datos(['precio_unitario' => -1]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('precio_unitario');
    }

    public function test_un_precio_unitario_en_cero_si_se_admite(): void
    {
        /*
         * A diferencia de la cantidad, el precio en cero se deja. Puede ser una
         * cosa que se regala o que entra por otra parte, y en un taller de
         * joyeria regalar la mano de obra es normal. El total saldra a cero y
         * el ingreso quedara a la vista, que es lo que tiene que pasar.
         */
        $this->post($this->ruta(), $this->datos([
            'precio_unitario' => 0,
            'cantidad' => 3,
        ]))->assertRedirect(route('procesos.ordenes_trabajo.show', $this->orden));

        $this->assertSame(0.0, (float) $this->guardado()->total);
    }

    public function test_la_descripcion_es_opcional_a_diferencia_de_la_del_costo(): void
    {
        $this->post($this->ruta(), $this->datos(['descripcion' => null]))
            ->assertRedirect(route('procesos.ordenes_trabajo.show', $this->orden));

        $this->assertNull($this->guardado()->descripcion);
    }

    public function test_la_unidad_de_medida_es_opcional(): void
    {
        $this->post($this->ruta(), $this->datos(['unidad_medida' => null]))
            ->assertRedirect(route('procesos.ordenes_trabajo.show', $this->orden));

        $this->assertNull($this->guardado()->unidad_medida);
    }

    public function test_sin_tipo_de_ingreso_no_se_admite(): void
    {
        $this->postJson($this->ruta(), $this->datos(['tipo_ingreso_id' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('tipo_ingreso_id');
    }

    public function test_un_tipo_de_ingreso_desactivado_no_se_admite(): void
    {
        $estadoOriginal = $this->tipo->estado;

        $this->tipo->estado = false;
        $this->tipo->save();

        $antes = $this->ingresosIniciales;

        $this->postJson($this->ruta(), $this->datos())
            ->assertStatus(422)
            ->assertJsonValidationErrors('tipo_ingreso_id');

        $this->assertSame($antes, $this->ingresosCreados());

        $this->tipo->estado = $estadoOriginal;
        $this->tipo->save();
    }

    public function test_una_moneda_desactivada_no_se_admite(): void
    {
        $estadoOriginal = $this->extranjera->estado;

        $this->extranjera->estado = false;
        $this->extranjera->save();

        $this->postJson($this->ruta(), $this->datos(['moneda_id' => $this->extranjera->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('moneda_id');

        $this->extranjera->estado = $estadoOriginal;
        $this->extranjera->save();
    }

    // ==================================================================
    // La orden cerrada
    // ==================================================================

    public function test_una_orden_cerrada_no_admite_ingresos_nuevos(): void
    {
        $estadoOriginal = $this->orden->estado;

        $this->orden->estado = OrdenesTrabajo::ESTADO_FINALIZADA;
        $this->orden->save();

        $antes = $this->ingresosIniciales;

        $this->post(
            $this->ruta(),
            $this->datos()
        )->assertRedirect(route('procesos.ordenes_trabajo.show', $this->orden));

        $this->assertSame($antes, $this->ingresosCreados());

        $this->orden->estado = $estadoOriginal;
        $this->orden->save();
    }

    public function test_una_orden_cancelada_tampoco_admite_ingresos_nuevos(): void
    {
        $estadoOriginal = $this->orden->estado;

        $this->orden->estado = OrdenesTrabajo::ESTADO_CANCELADA;
        $this->orden->save();

        $antes = $this->ingresosIniciales;

        $this->post($this->ruta(), $this->datos())
            ->assertRedirect(route('procesos.ordenes_trabajo.show', $this->orden));

        $this->assertSame($antes, $this->ingresosCreados());

        $this->orden->estado = $estadoOriginal;
        $this->orden->save();
    }

    public function test_una_orden_cerrada_si_puede_corregir_un_ingreso_que_ya_esta(): void
    {
        $ingreso = $this->ingreso(['cantidad' => 2, 'precio_unitario' => 10]);

        $estadoOriginal = $this->orden->estado;

        $this->orden->estado = OrdenesTrabajo::ESTADO_FINALIZADA;
        $this->orden->save();

        $this->put(
            $this->ruta('', $ingreso),
            $this->datos(['cantidad' => 4, 'precio_unitario' => 10])
        )->assertRedirect(route('procesos.ordenes_trabajo.show', $this->orden));

        $ingreso->refresh();

        // Un total mal puesto se corrige aunque la orden se cerrara en su dia
        $this->assertSame(40.0, (float) $ingreso->total);

        $this->orden->estado = $estadoOriginal;
        $this->orden->save();
    }

    public function test_un_ingreso_se_borra_aunque_la_orden_este_cerrada(): void
    {
        $ingreso = $this->ingreso();

        $estadoOriginal = $this->orden->estado;

        $this->orden->estado = OrdenesTrabajo::ESTADO_FINALIZADA;
        $this->orden->save();

        $this->delete($this->ruta('', $ingreso))
            ->assertRedirect(route('procesos.ordenes_trabajo.show', $this->orden));

        $this->assertSoftDeleted('ingresos', ['id' => $ingreso->id]);

        $this->orden->estado = $estadoOriginal;
        $this->orden->save();
    }

    // ==================================================================
    // El ingreso tiene que ser de esa orden
    // ==================================================================

    public function test_no_se_puede_ver_un_ingreso_de_otra_orden(): void
    {
        $otra = $this->otraOrden();

        $ingreso = new Ingreso();
        $ingreso->orden_trabajo_id = $otra->id;
        $ingreso->tipo_ingreso_id = $this->tipo->id;
        $ingreso->fecha = self::DIA;
        $ingreso->cantidad = 1;
        $ingreso->precio_unitario = 10;
        $ingreso->moneda_id = $this->base->id;
        $ingreso->estado = 1;
        /*
         * El tipo de cambio se calcula aqui con la misma regla que el
         * servidor: uno con la moneda base, y el de la fecha con las demas.
         *
         * Estaba puesto a 1.0 fijo, y con eso los tests de moneda extranjera
         * guardaban el equivalente igual al total. Los dos tests del total de
         * la ficha sumaban cifras que no eran las que habian creado y
         * fallaban por una razon que no tenia nada que ver con lo que
         * comprobaban.
         */
        $ingreso->calcularTotales(
            (int) $ingreso->moneda_id === (int) $this->base->id
                ? 1.0
                : TiposCambio::vigentePara((int) $ingreso->moneda_id, (string) $ingreso->fecha)
        );
        $ingreso->save();

        $this->get($this->ruta('', $ingreso))->assertNotFound();
    }

    public function test_no_se_puede_borrar_un_ingreso_de_otra_orden(): void
    {
        $otra = $this->otraOrden();

        $ingreso = new Ingreso();
        $ingreso->orden_trabajo_id = $otra->id;
        $ingreso->tipo_ingreso_id = $this->tipo->id;
        $ingreso->fecha = self::DIA;
        $ingreso->cantidad = 1;
        $ingreso->precio_unitario = 10;
        $ingreso->moneda_id = $this->base->id;
        $ingreso->estado = 1;
        /*
         * El tipo de cambio se calcula aqui con la misma regla que el
         * servidor: uno con la moneda base, y el de la fecha con las demas.
         *
         * Estaba puesto a 1.0 fijo, y con eso los tests de moneda extranjera
         * guardaban el equivalente igual al total. Los dos tests del total de
         * la ficha sumaban cifras que no eran las que habian creado y
         * fallaban por una razon que no tenia nada que ver con lo que
         * comprobaban.
         */
        $ingreso->calcularTotales(
            (int) $ingreso->moneda_id === (int) $this->base->id
                ? 1.0
                : TiposCambio::vigentePara((int) $ingreso->moneda_id, (string) $ingreso->fecha)
        );
        $ingreso->save();

        $this->delete($this->ruta('', $ingreso))->assertNotFound();

        $this->assertDatabaseHas('ingresos', [
            'id' => $ingreso->id,
            'deleted_at' => null,
        ]);
    }

    public function test_no_se_puede_corregir_un_ingreso_de_otra_orden(): void
    {
        $otra = $this->otraOrden();

        $ingreso = new Ingreso();
        $ingreso->orden_trabajo_id = $otra->id;
        $ingreso->tipo_ingreso_id = $this->tipo->id;
        $ingreso->fecha = self::DIA;
        $ingreso->cantidad = 1;
        $ingreso->precio_unitario = 10;
        $ingreso->moneda_id = $this->base->id;
        $ingreso->estado = 1;
        /*
         * El tipo de cambio se calcula aqui con la misma regla que el
         * servidor: uno con la moneda base, y el de la fecha con las demas.
         *
         * Estaba puesto a 1.0 fijo, y con eso los tests de moneda extranjera
         * guardaban el equivalente igual al total. Los dos tests del total de
         * la ficha sumaban cifras que no eran las que habian creado y
         * fallaban por una razon que no tenia nada que ver con lo que
         * comprobaban.
         */
        $ingreso->calcularTotales(
            (int) $ingreso->moneda_id === (int) $this->base->id
                ? 1.0
                : TiposCambio::vigentePara((int) $ingreso->moneda_id, (string) $ingreso->fecha)
        );
        $ingreso->save();

        $this->put(
            $this->ruta('', $ingreso),
            $this->datos(['cantidad' => 999])
        )->assertNotFound();

        $ingreso->refresh();

        $this->assertSame(10.0, (float) $ingreso->total);
    }

    /**
     * Una orden que no es la de la prueba.
     *
     * Se busca entre las que existen y, si no hay ninguna mas, se copia la
     * primera con otro codigo. Hace falta una segunda porque sin ella no se
     * puede probar que la comprobacion de pertenencia existe: haria falta el
     * caso de un ingreso que no es de la orden de la url, y con una sola orden
     * ese caso no se puede construir.
     */
    private function otraOrden(): OrdenesTrabajo
    {
        $otra = OrdenesTrabajo::where('id', '!=', $this->orden->id)->first();

        if ($otra !== null) {
            return $otra;
        }

        $cliente = DB::table('clientes')->first();

        if ($cliente === null) {
            $this->markTestSkipped('No hay ni una segunda orden ni clientes para hacer una.');
        }

        $orden = new OrdenesTrabajo();
        $orden->cliente_id = $cliente->id;
        $orden->codigo = 'OT-PRUEBA-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $orden->fecha = now();
        $orden->descripcion = 'Orden de la prueba de ingresos';
        $orden->peso_mineral = 1;
        $orden->unidad_peso = 'gramos';
        $orden->estado = OrdenesTrabajo::ESTADO_PENDIENTE;
        $orden->save();

        return $orden;
    }

    // ==================================================================
    // La tabla
    // ==================================================================

    public function test_la_tabla_devuelve_los_ingresos_de_esa_orden_y_solo_esos(): void
    {
        $mio = $this->ingreso(['fecha' => self::DIA]);

        $otra = $this->otraOrden();

        $ajeno = new Ingreso();
        $ajeno->orden_trabajo_id = $otra->id;
        $ajeno->tipo_ingreso_id = $this->tipo->id;
        $ajeno->fecha = self::DIA;
        $ajeno->cantidad = 7;
        $ajeno->precio_unitario = 100;
        $ajeno->moneda_id = $this->base->id;
        $ajeno->estado = 1;
        $ajeno->calcularTotales(1.0);
        $ajeno->save();

        $respuesta = $this->get(
            "/procesos/ordenes-trabajo/{$this->orden->id}/ingresos?" . http_build_query([
                'draw' => 1,
                'start' => 0,
                'length' => 50,
                'columns' => [['data' => 'fecha', 'name' => 'fecha']],
            ]),
            self::CABECERAS
        );

        $respuesta->assertOk();

        $datos = json_decode($respuesta->getContent(), true);

        /*
         * Los identificadores se comparan como enteros. json_decode saca los
         * numeros de la respuesta como numeros, y compararlos con cadenas
         * haria que el test fallara por el tipo y no por lo que comprueba.
         */
        $ids = array_map('intval', array_column($datos['data'], 'id'));

        $this->assertContains($mio->id, $ids, 'El ingreso de la orden no sale en su tabla.');
        $this->assertNotContains($ajeno->id, $ids, 'La tabla enseña un ingreso de otra orden.');
    }

    public function test_la_tabla_ordena_por_fecha_de_mas_reciente_a_mas_antigua(): void
    {
        $viejo = $this->ingreso(['fecha' => '2090-08-01']);
        $nuevo = $this->ingreso(['fecha' => '2090-08-20']);

        $respuesta = $this->get(
            "/procesos/ordenes-trabajo/{$this->orden->id}/ingresos?" . http_build_query([
                'draw' => 1,
                'start' => 0,
                'length' => 50,
                'columns' => [['data' => 'fecha', 'name' => 'fecha']],
            ]),
            self::CABECERAS
        );

        $datos = json_decode($respuesta->getContent(), true);

        $ids = array_map('intval', array_column($datos['data'], 'id'));

        $this->assertSame(
            [$nuevo->id, $viejo->id],
            $ids,
            'La tabla deberia traer primero el ingreso mas reciente.'
        );
    }

    public function test_la_tabla_no_enseña_los_ingresos_dados_de_baja(): void
    {
        $borrado = $this->ingreso();
        $borrado->delete();

        $vivo = $this->ingreso(['fecha' => '2090-08-10']);

        $respuesta = $this->get(
            "/procesos/ordenes-trabajo/{$this->orden->id}/ingresos?" . http_build_query([
                'draw' => 1,
                'start' => 0,
                'length' => 50,
                'columns' => [['data' => 'fecha', 'name' => 'fecha']],
            ]),
            self::CABECERAS
        );

        $datos = json_decode($respuesta->getContent(), true);

        $ids = array_map('intval', array_column($datos['data'], 'id'));

        $this->assertContains($vivo->id, $ids);
        $this->assertNotContains(
            $borrado->id,
            $ids,
            'La tabla enseña un ingreso que esta dado de baja.'
        );
    }

    public function test_la_tabla_muestra_el_dash_en_lugar_de_guion_cuando_no_hay_equivalente(): void
    {
        $this->ingreso([
            'moneda_id' => $this->monedaSinCambio()->id,
        ]);

        $respuesta = $this->get(
            "/procesos/ordenes-trabajo/{$this->orden->id}/ingresos?" . http_build_query([
                'draw' => 1,
                'start' => 0,
                'length' => 50,
                'columns' => [['data' => 'total_nio', 'name' => 'total_nio']],
            ]),
            self::CABECERAS
        );

        $datos = json_decode($respuesta->getContent(), true);
        $celda = $datos['data'][0]['total_nio'] ?? '';

        /*
         * Con el motivo en el title, que es lo unico que explica un guion. Un
         * guion sin explicacion deja como si faltara el dato, y quien lo mira no
         * sabe si es un error o que no se ha buscado.
         *
         * El guion que se busca es el em, que es el que pinta la tabla, y no un
         * guion corto: en el title y en los numeros hay guiones cortos a
         * montones, y con uno de esos el test pasaria siempre sin comprobar
         * nada. Al leerlo por la consola parece que hay un guion corto, porque
         * Windows no lo pinta; el hex es E28094.
         */
        $this->assertStringContainsString('—', $celda);
        $this->assertStringContainsString('tipo de cambio', $celda);
    }

    public function test_la_tabla_es_en_espanol(): void
    {
        $this->ingreso();

        $respuesta = $this->get(
            "/procesos/ordenes-trabajo/{$this->orden->id}/ingresos?" . http_build_query([
                'draw' => 1,
                'start' => 0,
                'length' => 50,
                'columns' => [['data' => 'total', 'name' => 'total']],
            ]),
            self::CABECERAS
        );

        /*
         * Los titulos NO VIENEN EN LA RESPUESTA DE AJAX. Van en la configuracion
         * que la tabla escribe en el html de la ficha, no en el json de las
         * filas. Buscarlos en la respuesta de ajax es buscar algo que no esta
         * nunca, y el test falla siempre sin comprobar nada.
         *
         * Se comprueba, por una parte, que la respuesta de ajax trae las filas
         * con los nombres de columna que la tabla declara —que es lo que
         * Yajra necesita para saber que columna es cual— y por otra, que en el
         * html de la ficha estan los titulos en espanol.
         */
        $datos = json_decode($respuesta->getContent(), true);

        $this->assertArrayHasKey('data', $datos);
        $this->assertNotEmpty($datos['data']);

        foreach (['fecha', 'tipo', 'descripcion', 'cantidad_unidad', 'total', 'total_nio', 'action'] as $columna) {
            $this->assertArrayHasKey(
                $columna,
                $datos['data'][0],
                'La fila no trae la columna ' . $columna
            );
        }

        $html = $this->get(
            route('procesos.ordenes_trabajo.show', $this->orden)
        )->assertOk()->getContent();

        foreach (['Fecha', 'Tipo', 'Descripción', 'Cantidad', 'Total', 'Acciones'] as $titulo) {
            $this->assertStringContainsString($titulo, $html);
        }
    }

    // ==================================================================
    // La ficha
    // ==================================================================

    public function test_la_ficha_devuelve_el_tipo_de_cambio_con_el_que_se_calculo(): void
    {
        $this->tipoCambio(self::MES_CAMBIO . '-01', 36.0);

        $ingreso = $this->ingreso([
            'moneda_id' => $this->extranjera->id,
            'cantidad' => 1,
            'precio_unitario' => 100,
        ]);

        $respuesta = $this->get($this->ruta('', $ingreso), self::CABECERAS);

        $respuesta->assertOk();

        $datos = json_decode($respuesta->getContent(), true);

        $this->assertSame(3600.0, (float) $datos['total_nio']);
        $this->assertSame(36.0, (float) $datos['tipo_cambio']);

        /*
         * Y el tipo de cambio se saca de los numeros guardados, no de volver a
         * buscar el de la tabla: si se buscara, corregir el tipo de cambio
         * haria que un ingreso viejo enseñara un cambio que no es el suyo.
         */
        TiposCambio::where('fecha', self::MES_CAMBIO . '-01')
            ->where('moneda_id', $this->extranjera->id)
            ->update(['valor' => 99.0]);

        $otra = json_decode(
            $this->get($this->ruta('', $ingreso), self::CABECERAS)->getContent(),
            true
        );

        $this->assertSame(36.0, (float) $otra['tipo_cambio']);
    }

    public function test_la_ficha_trae_los_datos_para_editar_incluido_el_equivalente_anterior(): void
    {
        $this->tipoCambio(self::MES_CAMBIO . '-01', 36.0);

        $ingreso = $this->ingreso([
            'moneda_id' => $this->extranjera->id,
            'cantidad' => 3,
            'precio_unitario' => 100,
        ]);

        $datos = json_decode(
            $this->get($this->ruta('/edit', $ingreso), self::CABECERAS)->getContent(),
            true
        );

        $this->assertSame($ingreso->id, $datos['id']);
        $this->assertSame(self::DIA, $datos['fecha']);
        $this->assertSame((int) $this->tipo->id, (int) $datos['tipo_ingreso_id']);
        $this->assertSame(10800.0, (float) $datos['total_nio']);
    }

    // ==================================================================
    // El total de la ficha
    // ==================================================================

    public function test_el_total_de_la_ficha_suma_los_equivalentes_de_la_orden(): void
    {
        $this->tipoCambio(self::MES_CAMBIO . '-01', 36.0);

        // 100 en dolares = 3600
        $this->ingreso([
            'moneda_id' => $this->extranjera->id,
            'cantidad' => 1,
            'precio_unitario' => 100,
            'fecha' => self::DIA,
        ]);

        // 250 en cordoba = 250
        $this->ingreso([
            'moneda_id' => $this->base->id,
            'cantidad' => 1,
            'precio_unitario' => 250,
            'fecha' => '2090-08-05',
        ]);

        $html = $this->get(
            route('procesos.ordenes_trabajo.show', $this->orden)
        )->assertOk()->getContent();

        /*
         * El total sale con los separadores que pone number_format sin
         * argumentos de separador, que es como los pone el resto del proyecto:
         * la coma en los miles y el punto en los decimales. Aqui esta escrito
         * "1,546.00" y no "1.546,00", y no por error del formato sino porque
         * las 124 llamadas a number_format del proyecto van asi.
         */
        $this->assertStringContainsString('3,850.00', $html);
    }

    public function test_el_total_de_la_ficha_avisa_de_los_ingresos_sin_equivalente(): void
    {
        $this->ingreso([
            'moneda_id' => $this->base->id,
            'cantidad' => 1,
            'precio_unitario' => 500,
            'fecha' => self::DIA,
        ]);

        // Este se queda fuera de la suma
        $this->ingreso([
            'moneda_id' => $this->monedaSinCambio()->id,
        ]);

        $html = $this->get(
            route('procesos.ordenes_trabajo.show', $this->orden)
        )->assertOk()->getContent();

        $this->assertStringContainsString('500.00', $html);
        $this->assertStringContainsString('sin equivalente en córdobas', $html);
    }

    public function test_una_orden_sin_ingresos_muestra_guion_y_no_cero(): void
    {
        $html = $this->get(
            route('procesos.ordenes_trabajo.show', $this->orden)
        )->assertOk()->getContent();

        $this->assertStringContainsString('Sin ingresos registrados', $html);
    }

    // ==================================================================
    // Los permisos
    // ==================================================================

    public function test_quien_no_tiene_el_permiso_no_llega_a_la_ruta(): void
    {
        $lector = User::create([
            'name' => 'Lector',
            'email' => 'ingreso-lector-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);

        // Sin el permiso de ingresos, aunque tenga el de ordenes de trabajo
        Permission::firstOrCreate(['name' => 'ordenes_trabajo.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'ingresos.create', 'guard_name' => 'web']);
        $lector->givePermissionTo('ordenes_trabajo.view');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($lector);

        $this->get($this->ruta(), self::CABECERAS)->assertForbidden();

        $antes = Ingreso::withTrashed()->count();

        $this->post($this->ruta(), $this->datos())->assertForbidden();

        $this->assertSame($antes, Ingreso::withTrashed()->count());
    }
}
