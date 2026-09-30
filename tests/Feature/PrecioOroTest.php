<?php
/**
 * La serie del precio del oro, de punta a punta.
 *
 * Lo que se comprueba aqui no es que las pantallas existan, que eso lo hacen
 * otros tests. Es la regla que hace que esta tabla sirva para algo y la que
 * puede fallar sin que se note: un precio en cero.
 *
 * En el tipo de cambio el cero no se admite, y alli esta bien, porque un cero
 * ahi deja una compra convertida a cero. En el precio del oro el cero si se
 * admite, y tambien esta bien, pero por un motivo distinto del que parece: un
 * cero en esta tabla no es una cotizacion de cero, es la forma de decir "de
 * este dia no se sabe el precio", que pasa y es un dato real. Lo que no puede
 * es que ese cero salga multiplicado por los gramos de una recuperacion.
 *
 * De ahi salen los dos caminos que se comprueban mas abajo y que son los
 * importantes:
 *
 *  - Al buscar el precio de un dia para valorar, los ceros se saltan. Con un
 *    cero el dia 15, el dia 16 se tasa con el precio del dia 14, que es lo que
 *    se ha hecho siempre en el taller: el metal no cambia de valor de un dia
 *    a otro porque el taller no consultara el banco. Y si no hay ninguno
 *    anterior, no se inventa nada: se avisa de que no hay con que valorar.
 *
 *  - Al importar un archivo, un cero no pisa un precio que ya se sabe. El
 *    archivo del banco no pone ceros nunca; si aparece uno, es que la celda
 *    venia vacia y el lector la leyo como cero. Dejarlo pasaria deja el mes
 *    entero sin precios con la pantalla llena de ceros que parecen
 *    cotizaciones.
 */

namespace Tests\Feature;

use App\Models\Moneda;
use App\Models\PreciosOro;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PrecioOroTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * El mes en el que trabajan las pruebas.
     *
     * Es de 2090 y distinto del que usan las del tipo de cambio a proposito:
     * las dos series son por dias y si compartieran mes, un dia de prueba del
     * oro se cruzaria con uno del tipo de cambio y los tests se pisarian sin
     * que se notara por que.
     */
    private const MES_PRUEBA = '2090-07';

    /**
     * Una fecha anterior a cualquier precio guardado, para comprobar que
     * cuando no hay nada no se inventa un valor.
     */
    private const FECHA_SIN_NADA = '2089-01-15';

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
            'email' => 'oro-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        $this->actingAs($admin);
    }

    // ------------------------------------------------------------------
    // Ayudas
    // ------------------------------------------------------------------

    /**
     * La moneda extranjera. La base es el cordoba, que no tiene precio de oro
     * propio: el precio del oro se cotiza en dolares y su equivalencia en
     * cordoba sale con el tipo de cambio de ese dia.
     */
    private function dolar(): Moneda
    {
        return Moneda::where('codigo', 'USD')->first()
            ?? Moneda::create([
                'codigo' => 'USD',
                'nombre' => 'Dolares de la prueba',
                'simbolo' => 'U$',
                'es_moneda_base' => false,
                'estado' => true,
            ]);
    }

    private function crear(
        string $fecha,
        float $precio,
        string $unidad = 'gramo',
        ?int $monedaId = null
    ): PreciosOro {
        return PreciosOro::create([
            PreciosOro::FECHA => $fecha,
            PreciosOro::PRECIO => $precio,
            PreciosOro::UNIDAD => $unidad,
            PreciosOro::MONEDA_ID => $monedaId ?? $this->dolar()->id,
            PreciosOro::FUENTE => 'Banco Central de Nicaragua',
        ]);
    }

    private function excel(array $filas, string $nombre = 'precio-oro.xlsx'): UploadedFile
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Julio');

        foreach ($filas as $indice => $celdas) {
            $numero = $indice + 1;

            foreach ($celdas as $columna => $valor) {
                $hoja->setCellValue(chr(65 + $columna) . $numero, $valor);
            }
        }

        $ruta = sys_get_temp_dir() . '/precio-oro-' . uniqid() . '.xlsx';

        (new Xlsx($libro))->save($ruta);

        $libro->disconnectWorksheets();

        return new UploadedFile($ruta, $nombre, null, null, true);
    }

    private function mesDePrueba(int $desde = 1, int $hasta = 5): array
    {
        $filas = [['Fecha', 'Precio']];

        for ($dia = $desde; $dia <= $hasta; $dia++) {
            $filas[] = [sprintf('%02d/07/2090', $dia), 78.4521 + ($dia * 0.0137)];
        }

        return $filas;
    }

    // ==================================================================
    // La pantalla y el alta
    // ==================================================================

    public function test_la_pantalla_se_ve(): void
    {
        $this->get('/configuracion/precios-oro')
            ->assertOk()
            ->assertSee('Precios del oro', false);
    }

    public function test_se_guarda_un_precio(): void
    {
        $this->post('/configuracion/precios-oro', [
            'fecha' => '2090-07-01',
            'precio' => 78.4521,
            'unidad' => 'gramo',
            'moneda_id' => $this->dolar()->id,
            'fuente' => 'Banco Central de Nicaragua',
        ], self::CABECERAS)->assertOk();

        $precio = PreciosOro::where('fecha', '2090-07-01')->first();

        $this->assertNotNull($precio, 'El precio deberia haberse guardado');
        $this->assertSame(78.4521, (float) $precio->precio, 'El precio deberia guardar sus cuatro decimales');
        $this->assertSame('gramo', $precio->unidad);
    }

    public function test_un_dia_no_puede_tener_dos_precios_de_la_misma_unidad_y_moneda(): void
    {
        $this->crear('2090-07-01', 78.4521);

        $this->post('/configuracion/precios-oro', [
            'fecha' => '2090-07-01',
            'precio' => 79.0000,
            'unidad' => 'gramo',
            'moneda_id' => $this->dolar()->id,
        ], self::CABECERAS)
            ->assertStatus(422)
            ->assertJsonValidationErrors('fecha');

        $this->assertSame(
            1,
            PreciosOro::where('fecha', '2090-07-01')->count(),
            'No deberia haber dos precios del mismo dia, unidad y moneda'
        );
    }

    public function test_el_gramo_y_la_onza_conviven_el_mismo_dia(): void
    {
        /*
         * El indice unico lleva la unidad precisamente para esto. El gramo y
         * la onza son numeros que se parecen —uno por debajo de cien, el otro
         * por encima de dos mil— y con el mismo dia de fondo, asi que se
         * separan por la unidad en el indice y conviven sin pisarse. Si
         * alguien carga la cotizacion del mercado en onzas un dia y la del
         * banco en gramos otro, la serie tiene las dos y cada una con su
         * unidad, y quien valore sabe cual esta mirando.
         */
        $gramo = $this->crear('2090-07-01', 78.4521, 'gramo');
        $onza = $this->crear('2090-07-01', 2441.3300, 'onza troy');

        $this->assertNotSame($gramo->id, $onza->id);
        $this->assertSame(2, PreciosOro::where('fecha', '2090-07-01')->count());
    }

    public function test_un_precio_negativo_no_se_admite(): void
    {
        $this->post('/configuracion/precios-oro', [
            'fecha' => '2090-07-01',
            'precio' => -5,
            'unidad' => 'gramo',
            'moneda_id' => $this->dolar()->id,
        ], self::CABECERAS)
            ->assertStatus(422)
            ->assertJsonValidationErrors('precio');
    }

    public function test_una_unidad_que_no_esta_en_la_lista_no_se_admite(): void
    {
        foreach (['libra', 'gram', 'onza', 'kilo'] as $unidad) {
            $this->post('/configuracion/precios-oro', [
                'fecha' => '2090-07-01',
                'precio' => 78.4521,
                'unidad' => $unidad,
                'moneda_id' => $this->dolar()->id,
            ], self::CABECERAS)
                ->assertStatus(422)
                ->assertJsonValidationErrors('unidad');
        }
    }

    public function test_la_unidad_se_normaliza_a_minusculas(): void
    {
        $this->post('/configuracion/precios-oro', [
            'fecha' => '2090-07-01',
            'precio' => 78.4521,
            'unidad' => 'Onza Troy',
            'moneda_id' => $this->dolar()->id,
        ], self::CABECERAS)->assertOk();

        /*
         * Sin esto, "Onza Troy" y "onza troy" serian dos unidades distintas
         * para el indice unico y el mismo dia tendria dos precios, sin que nada
         * lo notara: cada uno buscaria el anterior en su propia unidad y
         * ninguna encontraria el precio del otro.
         */
        $this->assertSame(
            'onza troy',
            PreciosOro::where('fecha', '2090-07-01')->value('unidad')
        );
    }

    public function test_se_corrige_el_precio_de_un_dia_que_ya_esta(): void
    {
        $precio = $this->crear('2090-07-01', 78.4521);

        $this->put('/configuracion/precios-oro/' . $precio->id, [
            'fecha' => '2090-07-01',
            'precio' => 79.1234,
            'unidad' => 'gramo',
            'moneda_id' => $this->dolar()->id,
        ], self::CABECERAS)->assertOk();

        $this->assertSame(79.1234, (float) $precio->fresh()->precio);
    }

    public function test_mover_un_precio_sobre_otro_dia_no_pisa(): void
    {
        $this->crear('2090-07-01', 78.4521);
        $otro = $this->crear('2090-07-02', 78.5000);

        $this->put('/configuracion/precios-oro/' . $otro->id, [
            'fecha' => '2090-07-01',
            'precio' => 78.9999,
            'unidad' => 'gramo',
            'moneda_id' => $this->dolar()->id,
        ], self::CABECERAS)
            ->assertStatus(422)
            ->assertJsonValidationErrors('fecha');

        $this->assertSame(
            '2090-07-02',
            $otro->fresh()->fecha->toDateString(),
            'El dia que se editaba deberia quedarse donde estaba'
        );
    }

    // ==================================================================
    // La regla del cero: el que busca el precio de un dia
    // ==================================================================

    public function test_un_precio_de_cero_se_admite(): void
    {
        $this->post('/configuracion/precios-oro', [
            'fecha' => '2090-07-01',
            'precio' => 0,
            'unidad' => 'gramo',
            'moneda_id' => $this->dolar()->id,
        ], self::CABECERAS)->assertOk();

        $this->assertSame(
            1,
            PreciosOro::where('fecha', '2090-07-01')->count(),
            'Un dia con el precio en cero deberia guardarse: significa que no se sabe'
        );
    }

    public function test_al_valorar_no_se_multiplica_por_cero(): void
    {
        $dolarId = $this->dolar()->id;

        $this->crear('2090-07-10', 78.4521, 'gramo', $dolarId);
        $this->crear('2090-07-11', 0, 'gramo', $dolarId);

        /*
         * El dia 11 tiene precio cero, que quiere decir que de ese dia no se
         * sabe. Si se devolviera como si fuera un precio, una valoracion de
         * cualquier gramo de ese dia daria cero, y el documento saldria con un
         * valor de cero sin avisar de nada.
         *
         * Lo que tiene que pasar es lo que se ha hecho siempre en el taller:
         * el dia 11 se tasa con el ultimo precio que si se sabe, el del 10.
         */
        $vigente = PreciosOro::vigentePara('2090-07-11', 'gramo', $dolarId);

        $this->assertNotNull($vigente, 'Deberia encontrar un precio, aunque el del dia sea cero');
        $this->assertSame(
            '2090-07-10',
            $vigente->fecha->toDateString(),
            'Deberia usar el ultimo precio que se sepa, no el cero del dia 11'
        );
        $this->assertSame(78.4521, (float) $vigente->precio);
    }

    public function test_una_serie_entera_de_ceros_no_da_un_precio(): void
    {
        $dolarId = $this->dolar()->id;

        $this->crear('2090-07-10', 0, 'gramo', $dolarId);
        $this->crear('2090-07-11', 0, 'gramo', $dolarId);

        /*
         * Si todos los anteriores son ceros, no hay precio. Y lo que se
         * devuelve es null, no un cero: el que llama tiene que poder decir
         * "no hay con que valorar", y para eso necesita que le llegue un
         * null y no un numero.
         */
        $this->assertNull(
            PreciosOro::vigentePara('2090-07-11', 'gramo', $dolarId),
            'Una serie de ceros no es un precio, es la falta de un precio'
        );

        $this->assertNull(
            PreciosOro::precioDelGramo('2090-07-11', $dolarId),
            'Tampoco deberia dar un precio del gramo'
        );
    }

    public function test_sin_precios_no_hay_con_que_valorar(): void
    {
        $this->assertNull(
            PreciosOro::precioDelGramo(self::FECHA_SIN_NADA, $this->dolar()->id),
            'Sin ningun precio anterior no se inventa ninguno'
        );
    }

    public function test_el_precio_de_la_onza_se_pasa_a_gramo(): void
    {
        $dolarId = $this->dolar()->id;

        $this->crear('2090-07-10', 2441.33, 'onza troy', $dolarId);

        $elGramo = PreciosOro::precioDelGramo('2090-07-10', $dolarId);

        $this->assertNotNull($elGramo);
        $this->assertSame('onza troy', $elGramo['unidad'], 'Deberia decir de que unidad venia');
        $this->assertSame('2090-07-10', $elGramo['de_que_dia']);

        /*
         * 2441,33 entre 31,1034768. La onza troy no son 31 gramos: son
         * 31,1034768, que es una unidad troy —31,1034768 gramos— y no la onza
         * comun de 28,35. Con 31 se equivocaria el valor de todo el oro del
         * taller en un tres por ciento, y el error no se veria en ningun sitio.
         */
        $this->assertEqualsWithDelta(
            78.4906,
            $elGramo['gramo'],
            0.001,
            'El precio de la onza deberia pasada a gramo dividiendo entre 31,1034768'
        );
    }

    public function test_si_hay_precio_de_gramo_no_se_mira_la_onza(): void
    {
        $dolarId = $this->dolar()->id;

        $this->crear('2090-07-01', 2441.33, 'onza troy', $dolarId);
        $this->crear('2090-07-10', 78.4521, 'gramo', $dolarId);

        $elGramo = PreciosOro::precioDelGramo('2090-07-15', $dolarId);

        $this->assertNotNull($elGramo);
        $this->assertSame(
            'gramo',
            $elGramo['unidad'],
            'Si hay precio del gramo, gana el del gramo: es la unidad con la que se calcula'
        );
        $this->assertSame(78.4521, (float) $elGramo['gramo']);
    }

    public function test_un_cero_en_la_serie_no_tapa_el_precio_anterior_de_otra_unidad(): void
    {
        $dolarId = $this->dolar()->id;

        $this->crear('2090-07-01', 78.4521, 'gramo', $dolarId);
        $this->crear('2090-07-05', 2441.33, 'onza troy', $dolarId);
        $this->crear('2090-07-10', 0, 'gramo', $dolarId);

        /*
         * El cero del gramo no puede tapar el precio de la onza. Cada unidad se
         * busca por separado, y un dia sin precio del gramo dice nada del
         * precio de la onza de ese dia: son dos series en la misma tabla, y
         * que una tenga un hueco no significa que la otra lo tenga.
         */
        $laOnza = PreciosOro::vigentePara('2090-07-10', 'onza troy', $dolarId);

        $this->assertNotNull($laOnza, 'El cero del gramo no puede tapar el precio de la onza');
        $this->assertSame('2090-07-05', $laOnza->fecha->toDateString());
        $this->assertSame(2441.33, (float) $laOnza->precio);
    }

    public function test_el_precio_de_otra_moneda_no_sirve(): void
    {
        $dolarId = $this->dolar()->id;

        $otra = Moneda::create([
            'codigo' => 'EUR',
            'nombre' => 'Euros de la prueba',
            'es_moneda_base' => false,
            'estado' => true,
        ]);

        $this->crear('2090-07-10', 78.4521, 'gramo', $dolarId);

        $this->assertNull(
            PreciosOro::vigentePara('2090-07-10', 'gramo', $otra->id),
            'El precio en dolares no puede servir para el euro'
        );
    }

    public function test_la_ficha_dice_el_precio_que_se_usaria_para_valorar(): void
    {
        $dolarId = $this->dolar()->id;

        $this->crear('2090-07-10', 78.4521, 'gramo', $dolarId);
        $conCero = $this->crear('2090-07-11', 0, 'gramo', $dolarId);

        $datos = $this->get('/configuracion/precios-oro/' . $conCero->id, self::CABECERAS)
            ->assertOk()
            ->json();

        $this->assertSame('0', $datos['precio'], 'La ficha deberia enseñar el cero tal como se guardo');

        $this->assertNotNull($datos['vigente'], 'Deberia decir de que dia se sacaria el precio de verdad');
        $this->assertSame('2090-07-10', $datos['vigente']['de_que_dia']);
    }

    // ==================================================================
    // La importacion del mes
    // ==================================================================

    public function test_importar_un_mes_lo_guarda_todo(): void
    {
        $this->post('/configuracion/precios-oro/importar', [
            'archivo' => $this->excel($this->mesDePrueba()),
            'moneda_id' => $this->dolar()->id,
            'unidad' => 'gramo',
            'confirmar' => 1,
        ], self::CABECERAS)->assertOk();

        $this->assertSame(
            5,
            PreciosOro::whereBetween('fecha', ['2090-07-01', '2090-07-31'])
                ->where('unidad', 'gramo')
                ->count(),
            'Deberian estar los cinco dias del mes de prueba'
        );
    }

    public function test_importar_no_escribe_nada_hasta_que_se_confirme(): void
    {
        $this->post('/configuracion/precios-oro/importar', [
            'archivo' => $this->excel($this->mesDePrueba()),
            'moneda_id' => $this->dolar()->id,
            'unidad' => 'gramo',
        ], self::CABECERAS)->assertOk()->assertJson(['vista_previa' => true]);

        $this->assertSame(
            0,
            PreciosOro::whereBetween('fecha', ['2090-07-01', '2090-07-31'])->count(),
            'Mirar el archivo no deberia escribir nada'
        );
    }

    public function test_la_unidad_del_importador_no_se_mezcla_con_el_gramo(): void
    {
        $this->crear('2090-07-01', 78.4521, 'gramo');

        $this->post('/configuracion/precios-oro/importar', [
            'archivo' => $this->excel($this->mesDePrueba()),
            'moneda_id' => $this->dolar()->id,
            'unidad' => 'onza troy',
            'confirmar' => 1,
        ], self::CABECERAS)->assertOk();

        $this->assertSame(
            78.4521,
            (float) PreciosOro::where('fecha', '2090-07-01')->where('unidad', 'gramo')->value('precio'),
            'Importar en onzas no deberia tocar el precio del gramo de ese dia'
        );

        $this->assertSame(
            1,
            PreciosOro::where('fecha', '2090-07-01')->where('unidad', 'onza troy')->count(),
            'Los onzas deberian estar guardados aparte'
        );
    }

    public function test_un_cero_del_archivo_no_pisa_un_precio_que_ya_se_sabe(): void
    {
        $this->crear('2090-07-01', 78.4521, 'gramo');

        // El archivo trae un cero para el dia 1, que es el unico que ya habia
        $filas = $this->mesDePrueba(1, 3);
        $filas[1][1] = 0;

        $respuesta = $this->post('/configuracion/precios-oro/importar', [
            'archivo' => $this->excel($filas),
            'moneda_id' => $this->dolar()->id,
            'unidad' => 'gramo',
            'confirmar' => 1,
        ], self::CABECERAS)->assertOk();

        /*
         * El dia 1 se queda con 78,4521. El cero del archivo no lo toca, porque
         * un cero en esta tabla no es un precio de cero sino "no se sabe", y
         * pisar un precio que si se sabe con un "no se sabe" deja el mes
         * entero sin precios sin que nada lo avise.
         */
        $this->assertSame(
            78.4521,
            (float) PreciosOro::where('fecha', '2090-07-01')->value('precio'),
            'El cero del archivo deberia haber dejado el precio que ya habia'
        );

        $this->assertSame(
            1,
            $respuesta->json('zeros_ignorados'),
            'Deberia decir cuantos ceros no han pisado nada'
        );
    }

    public function test_un_dia_sin_precio_si_admite_el_cero_del_archivo(): void
    {
        /*
         * El contrapeso del anterior: si el dia no tenia nada, el cero si entra.
         * Un dia en el que no se sabe el precio es un dato que vale la pena
         * guardar, y es lo que hace que la lista enseñe que se esta cargando
         * la serie a medias.
         *
         * El cero va en el dia 1, que es el indice 1 del array porque el
         * indice 0 es la fila de encabezados.
         */
        $filas = $this->mesDePrueba(1, 3);
        $filas[1][1] = 0;

        $respuesta = $this->post('/configuracion/precios-oro/importar', [
            'archivo' => $this->excel($filas),
            'moneda_id' => $this->dolar()->id,
            'unidad' => 'gramo',
            'confirmar' => 1,
        ], self::CABECERAS)->assertOk();

        $this->assertSame(3, $respuesta->json('guardados'));
        $this->assertEqualsWithDelta(
            0.0,
            (float) PreciosOro::where('fecha', '2090-07-01')->value('precio'),
            0.00001,
            'Un dia que no tenia precio deberia quedar guardado con el cero del archivo'
        );
    }

    public function test_la_vista_previa_avisa_de_los_ceros_que_no_van_a_entrar(): void
    {
        $this->crear('2090-07-01', 78.4521, 'gramo');

        $filas = $this->mesDePrueba(1, 3);
        $filas[1][1] = 0;

        $respuesta = $this->post('/configuracion/precios-oro/importar', [
            'archivo' => $this->excel($filas),
            'moneda_id' => $this->dolar()->id,
            'unidad' => 'gramo',
        ], self::CABECERAS)->assertOk();

        $sinCero = $respuesta->json('sin_cero_que_pisando');

        $this->assertCount(1, $sinCero, 'Deberia listar el dia cuyo cero no va a entrar');
        $this->assertSame('2090-07-01', $sinCero[0]['fecha']);
        $this->assertSame(78.4521, (float) $sinCero[0]['antes'], 'Deberia decir que precio se queda');
    }

    public function test_importar_actualiza_el_precio_del_dia_que_ya_esta(): void
    {
        $this->crear('2090-07-01', 99.0000, 'gramo');

        $this->post('/configuracion/precios-oro/importar', [
            'archivo' => $this->excel($this->mesDePrueba()),
            'moneda_id' => $this->dolar()->id,
            'unidad' => 'gramo',
            'confirmar' => 1,
        ], self::CABECERAS)->assertOk();

        $this->assertSame(
            78.4658,
            round((float) PreciosOro::where('fecha', '2090-07-01')->value('precio'), 4),
            'El precio del archivo deberia haber sustituido al que habia'
        );
    }

    public function test_importar_no_pisa_la_fuente_de_un_dia_corregido_a_mano(): void
    {
        $corregido = $this->crear('2090-07-01', 99.0000, 'gramo');

        $corregido->update([PreciosOro::FUENTE => 'Precio del mercado negro']);

        $this->post('/configuracion/precios-oro/importar', [
            'archivo' => $this->excel($this->mesDePrueba()),
            'moneda_id' => $this->dolar()->id,
            'unidad' => 'gramo',
            'fuente' => 'Banco Central de Nicaragua',
            'confirmar' => 1,
        ], self::CABECERAS)->assertOk();

        $this->assertSame(
            'Precio del mercado negro',
            $corregido->fresh()->fuente,
            'La fuente que puso el usuario no se puede llevar el archivo por delante'
        );
    }

    public function test_reimportar_un_mes_borrado_lo_recupera(): void
    {
        foreach (range(1, 5) as $dia) {
            $this->crear(sprintf('2090-07-%02d', $dia), 99.0)->delete();
        }

        $respuesta = $this->post('/configuracion/precios-oro/importar', [
            'archivo' => $this->excel($this->mesDePrueba()),
            'moneda_id' => $this->dolar()->id,
            'unidad' => 'gramo',
            'confirmar' => 1,
        ], self::CABECERAS)->assertOk();

        $this->assertSame(5, $respuesta->json('guardados'));
        $this->assertSame(
            5,
            DB::table('precios_oro')->whereBetween('fecha', ['2090-07-01', '2090-07-31'])->count(),
            'Deberian haber vuelto los cinco dias, y ninguno duplicado'
        );
    }

    public function test_importar_sin_moneda_avisa(): void
    {
        $this->post('/configuracion/precios-oro/importar', [
            'archivo' => $this->excel($this->mesDePrueba()),
            'unidad' => 'gramo',
            'confirmar' => 1,
        ], self::CABECERAS)
            ->assertStatus(422)
            ->assertJsonValidationErrors('moneda_id');
    }

    public function test_un_archivo_que_no_es_un_excel_avisa_y_no_guarda_nada(): void
    {
        $ruta = sys_get_temp_dir() . '/esto-no-es-un-excel-' . uniqid() . '.txt';

        file_put_contents($ruta, "esto es un texto, no una hoja de calculos\n");

        $archivo = new UploadedFile($ruta, 'precios.txt', null, null, true);

        $this->post('/configuracion/precios-oro/importar', [
            'archivo' => $archivo,
            'moneda_id' => $this->dolar()->id,
            'unidad' => 'gramo',
            'confirmar' => 1,
        ], self::CABECERAS)
            ->assertStatus(422)
            ->assertJsonValidationErrors('archivo');

        $this->assertSame(
            0,
            PreciosOro::whereBetween('fecha', ['2090-07-01', '2090-07-31'])->count(),
            'Un archivo que no se puede leer no deberia escribir nada'
        );
    }

    public function test_la_plantilla_se_descarga_y_es_un_ejecutable_que_se_puede_subir(): void
    {
        $respuesta = $this->get('/configuracion/precios-oro/plantilla');

        $respuesta->assertOk();

        $rutaTemporal = $respuesta->baseResponse->getFile();

        $this->assertNotNull($rutaTemporal, 'La plantilla deberia venir como descarga, no como cuerpo en memoria');

        $contenido = file_get_contents($rutaTemporal);

        $this->assertNotEmpty(
            $contenido,
            'La plantilla no puede venir con cero bytes: la respuesta dice 200 y el archivo que se '
            . 'descarga no existe, que es el peor de los dos mundos porque nada en la pagina avisa'
        );

        $archivo = new UploadedFile($rutaTemporal, 'ejemplo.xlsx', null, null, true);

        $leidas = (new \App\Services\PrecioOroImportador())->leer($rutaTemporal);

        $this->assertGreaterThan(
            0,
            count($leidas),
            'La plantilla que genera el codigo debería ser legible por el servicio que lee los archivos'
        );
    }

    // ==================================================================
    // Los permisos
    // ==================================================================

    public function test_quien_no_tiene_permiso_no_entra(): void
    {
        $this->actingAs($this->usuarioCon(['tipos_cambio.view']))
            ->get('/configuracion/precios-oro')
            ->assertForbidden();
    }

    public function test_quien_solo_mira_no_puede_importar_el_mes(): void
    {
        $this->actingAs($this->usuarioCon(['configuracion.precios_oro.view']));

        $this->get('/configuracion/precios-oro')->assertOk();

        $this->post('/configuracion/precios-oro/importar', [
            'archivo' => $this->excel($this->mesDePrueba(1, 3)),
            'moneda_id' => $this->dolar()->id,
            'unidad' => 'gramo',
        ], self::CABECERAS)->assertForbidden();
    }

    public function test_quien_puede_crear_tambien_puede_editar_y_borrar(): void
    {
        $usuario = $this->usuarioCon([
            'configuracion.precios_oro.view',
            'configuracion.precios_oro.create',
            'configuracion.precios_oro.edit',
            'configuracion.precios_oro.delete',
        ]);

        $this->actingAs($usuario)
            ->post('/configuracion/precios-oro', [
                'fecha' => '2090-07-01',
                'precio' => 78.4521,
                'unidad' => 'gramo',
                'moneda_id' => $this->dolar()->id,
            ], self::CABECERAS)
            ->assertOk();

        $precio = PreciosOro::where('fecha', '2090-07-01')->first();

        $this->assertNotNull($precio);

        $this->actingAs($usuario)
            ->put('/configuracion/precios-oro/' . $precio->id, [
                'fecha' => '2090-07-01',
                'precio' => 79.0000,
                'unidad' => 'gramo',
                'moneda_id' => $this->dolar()->id,
            ], self::CABECERAS)
            ->assertOk();

        $this->assertSame(79.0, (float) $precio->fresh()->precio);

        $this->actingAs($usuario)
            ->delete('/configuracion/precios-oro/' . $precio->id, [], self::CABECERAS)
            ->assertOk();

        $this->assertNotNull($precio->fresh()->deleted_at);
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
            'email' => 'oro-sin-roles-' . uniqid() . '@test.local',
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
        $precio = $this->crear('2090-07-01', 78.4521, 'gramo');

        $json = $this->get('/configuracion/precios-oro', self::CABECERAS)
            ->assertOk()
            ->json();

        $fila = $this->filaDe($json['data'], $precio->id);

        $this->assertNotNull($fila, 'Deberia salir el precio del mes de prueba en la tabla');

        $this->assertSame(
            78.4521,
            (float) strip_tags($fila['precio']),
            'El precio deberia salir tal como se guardo, con sus cuatro decimales'
        );

        $this->assertStringContainsString('gramo', $fila['unidad']);
        $this->assertStringContainsString('bi-pencil', $fila['action']);
    }

    public function test_un_precio_de_cero_sale_tachado_y_dice_que_no_se_sabe(): void
    {
        $precio = $this->crear('2090-07-01', 0, 'gramo');

        $json = $this->get('/configuracion/precios-oro', self::CABECERAS)
            ->assertOk()
            ->json();

        $fila = $this->filaDe($json['data'], $precio->id);

        $this->assertNotNull($fila);

        /*
         * Un cero a secas en una columna de precios parece una cotizacion de
         * cero, y alguien acabaria valorando con el. Tachado y con la
         * coletilla, no hay duda de lo que es.
         */
        $this->assertStringContainsString('text-decoration-line-through', $fila['precio']);
        $this->assertStringContainsString('no se sabe', $fila['precio']);
    }

    public function test_la_tabla_se_ordena_por_fecha_y_no_por_fecha_de_entrada(): void
    {
        $primero = $this->crear('2090-07-01', 78.4521);
        $segundo = $this->crear('2090-07-02', 78.5000);

        $json = $this->get('/configuracion/precios-oro', self::CABECERAS)
            ->assertOk()
            ->json();

        $posicionDelPrimero = $this->posicionDe($json['data'], (int) $primero->id);
        $posicionDelSegundo = $this->posicionDe($json['data'], (int) $segundo->id);

        $this->assertLessThan(
            $posicionDelPrimero,
            $posicionDelSegundo,
            'Deberia salir el dia mas reciente —el 2— antes que el mas antiguo —el 1—'
        );
    }

    public function test_los_botones_de_la_fila_llevan_su_url(): void
    {
        $precio = $this->crear('2090-07-01', 78.4521);

        $json = $this->get('/configuracion/precios-oro', self::CABECERAS)->json();

        $fila = $this->filaDe($json['data'], $precio->id);

        $this->assertNotNull($fila);

        $this->assertStringContainsString(route('configuracion.precios_oro.edit', $precio), $fila['action']);
        $this->assertStringContainsString(route('configuracion.precios_oro.destroy', $precio), $fila['action']);
    }

    public function test_la_pantalla_trae_los_modales_y_el_javascript(): void
    {
        $html = $this->get('/configuracion/precios-oro')->assertOk()->getContent();

        $this->assertStringContainsString('id="modalPrecioOro"', $html);
        $this->assertStringContainsString('id="formPrecioOro"', $html);
        $this->assertStringContainsString('id="modalImportarPrecioOro"', $html, 'Falta el modal de importación');
        $this->assertStringContainsString('id="btnNuevoPrecioOro"', $html);
        $this->assertStringContainsString('js/configuracion/precios_oro.js', $html);
        $this->assertStringContainsString(route('configuracion.precios_oro.store'), $html);
    }

    public function test_el_token_de_la_importacion_va_en_el_cuerpo_del_formulario(): void
    {
        $html = $this->get('/configuracion/precios-oro')->assertOk()->getContent();

        /*
         * El layout de este proyecto no trae <meta name="csrf-token">, asi que
         * el jquery lo leeria como undefined en cualquier sitio, y un FormData
         * armado a mano —que es lo unico que sirve para subir un archivo— no
         * lleva el token arrastrado por serialize(). Sin esto, la peticion
         * llega sin token y el servidor contesta un 419 sin decir nada.
         */
        $this->assertStringContainsString(
            'name="_token"',
            $html,
            'El formulario de importación necesita su propio token: el layout no trae el meta'
        );

        $this->assertStringNotContainsString(
            'name="csrf-token"',
            $html,
            'Si el layout trajera el meta, el javascript podria leerlo de ahi'
        );
    }

    /**
     * La fila de la tabla que corresponde a un precio.
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

    private function posicionDe(array $filas, int $id): int
    {
        foreach ($filas as $posicion => $fila) {
            if ((int) ($fila['DT_RowId'] ?? 0) === $id) {
                return $posicion;
            }
        }

        return -1;
    }
}
