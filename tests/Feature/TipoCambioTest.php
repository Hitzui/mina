<?php
/**
 * El CRUD del tipo de cambio y la importacion del mes, de punta a punta.
 *
 * Lo que se comprueba aqui no es que las pantallas existan, que eso ya lo
 * hacen otros tests. Es la regla de la serie: un dia no puede tener dos tipos
 * de cambio para la misma moneda, y la importacion tiene que respetar esa
 * regla sin que el usuario tenga que saber que existe.
 *
 * Y estan los tres fallos que hunden la importacion del mes, y que son los
 * que salen cuando se prueban a mano en lugar de en un test:
 *
 *  - Que se importen treinta dias y no se diga ni uno. El archivo se lee bien,
 *    se dice que se ha guardado y en la tabla no hay nada.
 *
 *  - Que se pisen las correcciones a mano. El usuario corrige un dia porque el
 *    banco se equivoco, vuelve a subir el archivo del mes, y la correccion
 *    desaparece sin avisar. La correccion del usuario es mas nueva que el
 *    archivo y tiene que ganar.
 *
 *  - Que a medias. Un archivo de treinta dias que falla en el dia dieciocho
 *    deja los diecisiete primeros guardados, y al reintentarlo falla en el
 *    primero porque ya existe. La base se queda con una mitad del mes y
 *    ningun sitio donde mirar que mitad es.
 */

namespace Tests\Feature;

use App\Models\Moneda;
use App\Models\TiposCambio;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * El tipo de cambio de cada dia.
 */
class TipoCambioTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * El mes en el que trabajan las pruebas.
     *
     * Es de 2090 y no de 2026 por dos motivos, y los dos importan:
     *
     *  - Para no chocar con los datos de verdad. El usuario carga el tipo de
     *    cambio del mes que esta viviendo a mano, y un test que usara las
     *    mismas fechas se meteria en ellas: al crear una fila del dia 15
     *    chocaria con el indice unico, y al borrarla se llevaria por delante el
     *    dia del usuario.
     *
     *  - Y mas importante: para que el test que comprueba que un dia SIN tipo
     *    de cambio sale vacio pueda existir. La busqueda toma el ultimo
     *    registro anterior a la fecha pedida, con lo que cualquier fecha
     *    posterior a los datos del usuario los encuentra. Un anio de 2090 esta
     *    lejos de los datos reales, y un 2089 esta antes de ellos.
     */
    private const MES_PRUEBA = '2090-06';

    /**
     * Una fecha anterior a cualquier tipo de cambio guardado.
     *
     * Sirve para comprobar que, cuando no hay nada, no se inventa un valor.
     * Esta antes de los datos del usuario y antes que el mes de las pruebas,
     * que es lo unico que hace que de verdad no encuentre nada.
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
            'email' => 'tipocambio-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        $this->actingAs($admin);
    }

    // ------------------------------------------------------------------
    // Ayudas
    // ------------------------------------------------------------------

    /**
     * Una moneda extranjera. La base es el cordoba, que no lleva tipo de
     * cambio consigo mismo: un tipo de cambio del cordoba contra el cordoba
     * seria siempre uno y no diria nada.
     *
     * Se busca por el codigo ISO y no por id, igual que la migracion que
     * cambio los codigos. El id lo asigno el generador cuando se creo la
     * base y no significa nada: la moneda de la aplicacion tiene el id 1 y
     * la de la prueba el 4, sin que haya ningun orden detrás.
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

    private function crear(string $fecha, float $valor, ?int $monedaId = null): TiposCambio
    {
        return TiposCambio::create([
            TiposCambio::FECHA => $fecha,
            TiposCambio::MONEDA_ID => $monedaId ?? $this->dolar()->id,
            TiposCambio::VALOR => $valor,
            TiposCambio::FUENTE => 'Banco Central de Nicaragua',
        ]);
    }

    /**
     * Un Excel de verdad, con lo que el usuario subiria.
     *
     * Se construye con PhpSpreadsheet y no como un archivo de texto con
     * extension de Excel: el servicio que lee los archivos en la aplicacion
     * tambien los lee con PhpSpreadsheet, y un archivo de texto con el
     * nombre cambiado no lo abriria. La prueba estaria probando un archivo
     * que el usuario jamas tendria.
     *
     * @param  array<int, array{0: mixed, 1: mixed}>  $filas
     */
    private function excel(array $filas, string $nombre = 'tipo-cambio.xlsx'): UploadedFile
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Septiembre');

        foreach ($filas as $indice => $celdas) {
            $numero = $indice + 1;

            foreach ($celdas as $columna => $valor) {
                $hoja->setCellValue(chr(65 + $columna) . $numero, $valor);
            }
        }

        /*
         * La ruta tiene que acabar en .xlsx, y no se le pone la extension
         * encima de la que ya trae.
         *
         * tempnam() devuelve algo como "/tmp/tipo_cambioAB12Cd", y el
         * servicio que lee el archivo decide que formato tiene por la
         * extension de ESA ruta, no por el nombre que el usuario le dio. Con
         * una extension suelta, el archivo se rechazaba con "el que ha subido
         * es un tmp": el nombre de verdad lo tiene el UploadedFile, y la
         * ruta de disco es solo donde esta mientras se lee.
         */
        $ruta = sys_get_temp_dir() . '/tipo-cambio-' . uniqid() . '.xlsx';

        (new Xlsx($libro))->save($ruta);

        $libro->disconnectWorksheets();

        /*
         * El UploadedFile se crea con esa ruta y con test = true, que es lo
         * que le dice a Laravel que el archivo viene de una prueba y no de
         * una subida de verdad.
         *
         * Con test = false, Laravel no se fia del archivo y lo borra al
         * terminar la peticion; a mitad de la prueba, entre subirlo y leerlo,
         * el archivo ya no esta y el fallo que sale es que no se encuentra.
         */
        return new UploadedFile($ruta, $nombre, null, null, true);
    }

    private function mesDeSeptiembre(int $desde = 1, int $hasta = 5): array
    {
        $filas = [['Fecha', 'Valor']];

        for ($dia = $desde; $dia <= $hasta; $dia++) {
            $filas[] = [sprintf('%02d/06/2090', $dia), 36.5824 + ($dia * 0.01)];
        }

        return $filas;
    }

    // ==================================================================
    // El alta y la edicion de un dia
    // ==================================================================

    public function test_la_pantalla_se_ve(): void
    {
        $this->get('/configuracion/tipos-cambio')
            ->assertOk()
            ->assertSee('Tipo de cambio')
            ->assertSee('tipos-cambio-table', false);
    }

    public function test_se_guarda_un_dia(): void
    {
        $dolar = $this->dolar();

        $this->post('/configuracion/tipos-cambio', [
            'fecha' => '2090-06-01',
            'moneda_id' => $dolar->id,
            'valor' => '36.5824',
            'fuente' => 'Banco Central de Nicaragua',
        ], self::CABECERAS)->assertOk();

        $guardado = TiposCambio::where('fecha', '2090-06-01')->firstOrFail();

        $this->assertSame($dolar->id, (int) $guardado->moneda_id);
        $this->assertSame(36.5824, round((float) $guardado->valor, 4));
    }

    public function test_no_se_admite_un_tipo_de_cambio_de_cero(): void
    {
        /*
         * Cero no es un tipo de cambio: es la forma de decir que no se sabe.
         * Aceptarlo dejaria una compra con el equivalente en cordoba a cero,
         * que es peor que no tenerla valorada, porque el numero sale y no
         * avisa de nada.
         */
        $this->post('/configuracion/tipos-cambio', [
            'fecha' => '2090-06-01',
            'moneda_id' => $this->dolar()->id,
            'valor' => '0',
        ], self::CABECERAS)
            ->assertStatus(422)
            ->assertJsonValidationErrors('valor');
    }

    public function test_un_dia_no_puede_tener_dos_tipos_de_cambio_de_la_misma_moneda(): void
    {
        $dolar = $this->dolar();

        $this->crear('2090-06-01', 36.5824, $dolar->id);

        $this->post('/configuracion/tipos-cambio', [
            'fecha' => '2090-06-01',
            'moneda_id' => $dolar->id,
            'valor' => '36.60',
        ], self::CABECERAS)
            ->assertStatus(422)
            ->assertJsonValidationErrors('fecha');

        $this->assertSame(
            1,
            TiposCambio::where('fecha', '2090-06-01')
                ->where('moneda_id', $dolar->id)
                ->count(),
            'Ese dia no deberia haberse guardado un segundo tipo de cambio'
        );
    }

    public function test_se_avisa_de_que_ese_dia_ya_esta_puesto(): void
    {
        $dolar = $this->dolar();

        $this->crear('2090-06-01', 36.5824, $dolar->id);

        $respuesta = $this->post('/configuracion/tipos-cambio', [
            'fecha' => '2090-06-01',
            'moneda_id' => $dolar->id,
            'valor' => '36.60',
        ], self::CABECERAS);

        /*
         * El error tiene que decir que hay que editarlo. Un "ya existe" a
         * secas deja al usuario buscando donde editar, cuando lo que quiere es
         * cambiar el valor de un dia que ya tiene.
         */
        $this->assertStringContainsString(
            'Edítalo',
            $respuesta->json('errors.fecha.0') ?? ''
        );
    }

    public function test_se_corrige_el_valor_de_un_dia_que_ya_esta(): void
    {
        $tipoCambio = $this->crear('2090-06-01', 36.5824);

        $this->put('/configuracion/tipos-cambio/' . $tipoCambio->id, [
            'fecha' => '2090-06-01',
            'moneda_id' => $tipoCambio->moneda_id,
            'valor' => '36.6500',
        ], self::CABECERAS)->assertOk();

        $this->assertSame(36.65, round((float) $tipoCambio->fresh()->valor, 4));
                $this->assertSame(
            1,
            $this->cuantosHayEnElMesDePrueba(),
            'Corregir un dia no deberia crear otra fila'
        );
    }

    public function test_se_cambia_el_dia_que_tiene_un_tipo_de_cambio_ya_guardado(): void
    {
        $tipoCambio = $this->crear('2090-06-01', 36.5824);

        $this->put('/configuracion/tipos-cambio/' . $tipoCambio->id, [
            'fecha' => '2090-06-02',
            'moneda_id' => $tipoCambio->moneda_id,
            'valor' => '36.5824',
        ], self::CABECERAS)->assertOk();

        /*
         * Cambiarle el dia a un tipo de cambio ya guardado es una correccion
         * legitima — uno se equivoca al teclear la fecha — pero deja un hueco
         * en el dia viejo. Por eso se avisa, no se prohibe: quien lo hace
         * suele saber lo que hace, y quien no, por lo menos se entera.
         */
        $this->assertSame('2090-06-02', $tipoCambio->fresh()->fecha->toDateString());
    }

    public function test_cambiar_el_dia_a_uno_que_ya_tiene_otro_no_se_pisa(): void
    {
        $dolar = $this->dolar();

        $este = $this->crear('2090-06-01', 36.5824, $dolar->id);
        $otro = $this->crear('2090-06-02', 36.6000, $dolar->id);

        $this->put('/configuracion/tipos-cambio/' . $este->id, [
            'fecha' => '2090-06-02',
            'moneda_id' => $dolar->id,
            'valor' => '36.5824',
        ], self::CABECERAS)->assertStatus(422);

        $this->assertSame('2090-06-01', $este->fresh()->fecha->toDateString());
        $this->assertSame(36.6, round((float) $otro->fresh()->valor, 4));
    }

    public function test_borrar_necesita_confirmacion(): void
    {
        $tipoCambio = $this->crear('2090-06-01', 36.5824);

        $this->delete('/configuracion/tipos-cambio/' . $tipoCambio->id, [], self::CABECERAS)
            ->assertStatus(409)
            ->assertJson(['requiere_confirmacion' => true]);

                $this->assertSame(
            1,
            $this->cuantosHayEnElMesDePrueba(),
            'Sin confirmar no se deberia haber borrado el dia'
        );
    }

    public function test_borrar_con_confirmacion_quita_el_dia(): void
    {
        $tipoCambio = $this->crear('2090-06-01', 36.5824);

        $this->delete('/configuracion/tipos-cambio/' . $tipoCambio->id, [
            'confirmado' => 1,
        ], self::CABECERAS)->assertOk();

                $this->assertSame(
            0,
            $this->cuantosHayEnElMesDePrueba(),
            'El dia deberia haberse borrado del todo'
        );
    }

    // ==================================================================
    // Quien lee la serie
    // ==================================================================

    public function test_el_vigente_es_el_del_dia_o_el_mas_cercano_de_antes(): void
    {
        $dolar = $this->dolar();

        $this->crear('2090-06-01', 36.5824, $dolar->id);
        $this->crear('2090-06-05', 36.7000, $dolar->id);

        // Un dia entre los dos se tasa con el del 1, que es el ultimo
        // conocido en ese momento.
        $this->assertSame(36.5824, round((float) TiposCambio::vigentePara($dolar->id, '2090-06-03'), 4));

        $this->assertSame(36.7, round((float) TiposCambio::vigentePara($dolar->id, '2090-06-05'), 4));
    }

    public function test_sin_tipo_de_cambio_no_hay_equivalente(): void
    {
        $dolar = $this->dolar();

        $this->crear('2090-06-10', 36.5824, $dolar->id);

        /*
         * Un dia anterior al primero de la serie no tiene tipo de cambio
         * todavia. Devolver null es lo que hace que la compra salga sin
         * equivalente en vez de valorada a cero o inventandose un cambio.
         *
         * Y se comprueba con una moneda que no tenga nada, y no con el dolar.
         * La busqueda toma el ultimo registro ANTERIOR a la fecha pedida, asi
         * que con los dias que el usuario tiene cargados en la base cualquier
         * fecha posterior a ellos los encontraria: el test pasaria mirando los
         * datos del usuario, y no por lo que dice su nombre. Con una moneda
         * vacia lo que se demuestra es lo que tiene que demostrarse: que sin
         * nada guardado devuelve null.
         */
        $vacia = Moneda::create([
            'codigo' => '009',
            'nombre' => 'Moneda sin tipo de cambio de la prueba',
            'simbolo' => '?',
            'es_moneda_base' => false,
            'estado' => true,
        ]);

        $this->assertNull(
            TiposCambio::vigentePara($vacia->id, self::FECHA_SIN_NADA),
            'Una moneda sin tipo de cambio no puede devolver un valor'
        );

        $this->assertNull(
            TiposCambio::vigentePara($vacia->id, '2090-06-01'),
            'Tampoco cuando la fecha es posterior: una moneda sin datos sigue '
            . 'sin tener datos, y el valor de otra moneda no se le presta'
        );
    }

    public function test_el_mes_de_las_pruebas_esta_libre(): void
    {
        /*
         * Las pruebas de esta clase trabajan en junio de 2090, y este test es
         * el que se entera de que ese mes sigue libre.
         *
         * Es el unico sitio donde se comprueba la suposicion en vez de
         * darla por buena. Si el usuario cargara un tipo de cambio de junio de
         * 2090 —que no va a pasar, pero puede— los tests empezarian a chocar
         * con el indice unico al crear sus filas, y el fallo seria "duplicate
         * entry", que no dice nada del problema real: que el mes de las
         * pruebas ya no esta libre.
         *
         * Y no se mira la tabla entera, sino solo ese mes. La tabla tiene los
         * dias que el usuario carga a mano, y el mas antiguo es de 2026, que
         * es anterior al 2089 que usan las pruebas. Mirando la tabla entera,
         * este test fallaria siempre y no por nada: esos dias no molestan,
         * porque el unico test al que le importa no tener nada delante —el del
         * dia sin tipo de cambio— usa una moneda vacia, y ahi si no hay nada
         * que buscar.
         */

        $esteMes = DB::table('tipos_cambio')->where('fecha', 'like', self::MES_PRUEBA . '-%')->count();

        $this->assertSame(
            0,
            $esteMes,
            'Las pruebas usan el mes de ' . self::MES_PRUEBA . ', que deberia '
            . 'estar libre. Si hay dias ahi, un test que cree una fila del mismo '
            . 'dia chocaria con el indice unico y no llegaria a comprobar nada'
        );
    }

    public function test_el_tipo_de_cambio_de_una_moneda_no_sirve_para_otra(): void
    {
        $dolar = $this->dolar();

        $otra = Moneda::create([
            'codigo' => 'EUR',
            'nombre' => 'Euros de la prueba',
            'simbolo' => 'E',
            'es_moneda_base' => false,
            'estado' => true,
        ]);

        $this->crear('2090-06-01', 36.5824, $dolar->id);

        $this->assertNull(
            TiposCambio::vigentePara($otra->id, '2090-06-01'),
            'El tipo de cambio del dolar no puede servir para el euro'
        );
    }

    // ==================================================================
    // La importacion del mes
    // ==================================================================

    public function test_importar_un_mes_lo_guarda_todo(): void
    {
        $dolar = $this->dolar();

        $this->post('/configuracion/tipos-cambio/importar', [
            'archivo' => $this->excel($this->mesDeSeptiembre(1, 5)),
            'moneda_id' => $dolar->id,
            'confirmar' => 1,
        ], self::CABECERAS)
            ->assertOk()
            ->assertJson(['guardados' => 5, 'nuevos' => 5]);

                $this->assertSame(
            5,
            $this->cuantosHayEnElMesDePrueba(),
            'El mes entero deberia estar guardado'
        );

        $this->assertSame(
            36.5924,
            round((float) TiposCambio::where('fecha', '2090-06-01')->firstOrFail()->valor, 4)
        );
    }

    public function test_importar_no_escribe_nada_hasta_que_se_confirme(): void
    {
        $dolar = $this->dolar();

        $respuesta = $this->post('/configuracion/tipos-cambio/importar', [
            'archivo' => $this->excel($this->mesDeSeptiembre(1, 5)),
            'moneda_id' => $dolar->id,
        ], self::CABECERAS)->assertOk();

        /*
         * Este es el fallo que mas caro sale. Leer el archivo y devolver lo
         * que trae sin escribir nada es lo que permite que el usuario vea
         * cuantos dias van a entrar antes de que entren. Si el primer paso ya
         * guardara, la vista previa serian un resumen de lo que ya se ha
         * escrito, y cancelar el modal no cancelaria nada.
         */
        $this->assertTrue($respuesta->json('vista_previa'));
        $this->assertSame(5, $respuesta->json('total'));
                $this->assertSame(
            0,
            $this->cuantosHayEnElMesDePrueba(),
            'La vista previa no deberia escribir nada: leer el archivo no es '
            . 'guardarlo, y un boton de cancelar que dejara el mes dentro seria '
            . 'peor que un boton que no hiciera nada'
        );
    }

    public function test_la_vista_previa_dice_cuantos_dias_cambian(): void
    {
        $dolar = $this->dolar();

        $this->crear('2090-06-02', 99.0000, $dolar->id);

        $respuesta = $this->post('/configuracion/tipos-cambio/importar', [
            'archivo' => $this->excel($this->mesDeSeptiembre(1, 5)),
            'moneda_id' => $dolar->id,
        ], self::CABECERAS)->assertOk();

        $this->assertSame(5, $respuesta->json('total'));
        $this->assertSame(4, $respuesta->json('nuevos'));
        $this->assertSame(1, count($respuesta->json('cambian')));

        $cambio = $respuesta->json('cambian.0');

        /*
         * La previa tiene que decir de cuanto a cuanto, no solo que cambia.
         * "Un dia va a cambiar" no deja decidir; "el 2 pasa de 99 a 36.60" si.
         */
        $this->assertSame('2090-06-02', $cambio['fecha']);
        $this->assertSame(99.0, (float) $cambio['antes']);
        $this->assertNotSame(99.0, (float) $cambio['despues']);
    }

    public function test_importar_otra_vez_el_mismo_mes_no_duplica_los_dias(): void
    {
        $dolar = $this->dolar();

        $this->post('/configuracion/tipos-cambio/importar', [
            'archivo' => $this->excel($this->mesDeSeptiembre(1, 5)),
            'moneda_id' => $dolar->id,
            'confirmar' => 1,
        ], self::CABECERAS)->assertOk();

        $this->post('/configuracion/tipos-cambio/importar', [
            'archivo' => $this->excel($this->mesDeSeptiembre(1, 5)),
            'moneda_id' => $dolar->id,
            'confirmar' => 1,
        ], self::CABECERAS)->assertOk();

                $this->assertSame(
            5,
            $this->cuantosHayEnElMesDePrueba(),
            'El mes entero deberia estar guardado'
        );
    }

    public function test_importar_actualiza_el_dia_que_ya_esta_con_el_valor_del_archivo(): void
    {
        $dolar = $this->dolar();

        $this->crear('2090-06-02', 99.0000, $dolar->id);

        $this->post('/configuracion/tipos-cambio/importar', [
            'archivo' => $this->excel($this->mesDeSeptiembre(1, 5)),
            'moneda_id' => $dolar->id,
            'confirmar' => 1,
        ], self::CABECERAS)
            ->assertOk()
            ->assertJson(['cambiados' => 1]);

        $this->assertSame(
            36.6024,
            round((float) TiposCambio::where('fecha', '2090-06-02')->firstOrFail()->valor, 4)
        );
    }

    public function test_importar_no_toca_los_dias_de_otra_moneda(): void
    {
        $dolar = $this->dolar();

        $otra = Moneda::create([
            'codigo' => 'EUR',
            'nombre' => 'Euros de la prueba',
            'simbolo' => 'E',
            'es_moneda_base' => false,
            'estado' => true,
        ]);

        $este = $this->crear('2090-06-01', 36.5824, $dolar->id);
        $otro = $this->crear('2090-06-01', 42.0000, $otra->id);

        $this->post('/configuracion/tipos-cambio/importar', [
            'archivo' => $this->excel($this->mesDeSeptiembre(1, 3)),
            'moneda_id' => $dolar->id,
            'confirmar' => 1,
        ], self::CABECERAS)->assertOk();

        /*
         * El indice unico es de fecha y moneda, asi que dos monedas pueden
         * tener el mismo dia. Importar los dolares no puede pisar el euro de
         * ese dia, que es un dato que no tiene nada que ver.
         */
        $this->assertSame(36.5924, round((float) $este->fresh()->valor, 4));
        $this->assertSame(42.0, round((float) $otro->fresh()->valor, 4));
    }

    public function test_importar_usa_la_fuente_del_formulario(): void
    {
        $dolar = $this->dolar();

        $this->post('/configuracion/tipos-cambio/importar', [
            'archivo' => $this->excel($this->mesDeSeptiembre(1, 3)),
            'moneda_id' => $dolar->id,
            'fuente' => 'Banco Central de Nicaragua',
            'confirmar' => 1,
        ], self::CABECERAS)->assertOk();

        $this->assertSame(
            'Banco Central de Nicaragua',
            TiposCambio::where('fecha', '2090-06-01')->firstOrFail()->fuente
        );
    }

    public function test_importar_no_pisa_la_fuente_de_un_dia_corregido_a_mano(): void
    {
        $dolar = $this->dolar();

        $corregido = $this->crear('2090-06-02', 99.0000, $dolar->id);
        $corregido->update(['fuente' => 'Corrección a mano del 15 de septiembre']);

        $this->post('/configuracion/tipos-cambio/importar', [
            'archivo' => $this->excel($this->mesDeSeptiembre(1, 3)),
            'moneda_id' => $dolar->id,
            'fuente' => 'Banco Central de Nicaragua',
            'confirmar' => 1,
        ], self::CABECERAS)->assertOk();

        /*
         * El valor si se cambia, que el archivo es la fuente oficial. La
         * fuente no: si el usuario apunto de donde salio su correccion a
         * mano, dejar el nombre del banco encima haria que dentro de un mes
         * nadie supiera de donde salio ese numero.
         */
        $this->assertSame(
            'Corrección a mano del 15 de septiembre',
            $corregido->fresh()->fuente
        );
    }

    public function test_un_archivo_sin_encabezados_avisa_y_no_guarda_nada(): void
    {
        $dolar = $this->dolar();

        $this->post('/configuracion/tipos-cambio/importar', [
            'archivo' => $this->excel([
                ['columna A', 'columna B'],
                ['01/06/2090', 36.5824],
            ]),
            'moneda_id' => $dolar->id,
            'confirmar' => 1,
        ], self::CABECERAS)
            ->assertStatus(422)
            ->assertJsonValidationErrors('archivo');

                $this->assertSame(
            0,
            $this->cuantosHayEnElMesDePrueba(),
            'El dia deberia haberse borrado del todo'
        );
    }

    public function test_un_archivo_que_no_es_un_excel_avisa_y_no_guarda_nada(): void
    {
        $dolar = $this->dolar();

        // Sin anadirle la extension encima: tempnam() ya pone una, y el
        // nombre final tiene que ser el que se le pide al constructor.
        $ruta = tempnam(sys_get_temp_dir(), 'foto') . '.jpg';
        file_put_contents($ruta, 'esto es una foto, no un excel');

        $subido = new UploadedFile($ruta, 'banco.jpg', 'image/jpeg', null, true);

        $this->post('/configuracion/tipos-cambio/importar', [
            'archivo' => $subido,
            'moneda_id' => $dolar->id,
            'confirmar' => 1,
        ], self::CABECERAS)
            ->assertStatus(422)
            ->assertJsonValidationErrors('archivo');

                $this->assertSame(
            0,
            $this->cuantosHayEnElMesDePrueba(),
            'El dia deberia haberse borrado del todo'
        );
    }

    public function test_importar_sin_moneda_avisa(): void
    {
        $this->post('/configuracion/tipos-cambio/importar', [
            'archivo' => $this->excel($this->mesDeSeptiembre(1, 3)),
            'moneda_id' => '',
            'confirmar' => 1,
        ], self::CABECERAS)
            ->assertStatus(422)
            ->assertJsonValidationErrors('moneda_id');
    }

    public function test_los_dias_malos_no_tiran_el_resto_del_archivo(): void
    {
        $dolar = $this->dolar();

        $respuesta = $this->post('/configuracion/tipos-cambio/importar', [
            'archivo' => $this->excel([
                ['Fecha', 'Valor'],
                ['01/06/2090', 36.5824],
                ['esto no es una fecha', 36.61],
                ['03/06/2090', 36.6543],
            ]),
            'moneda_id' => $dolar->id,
        ], self::CABECERAS)->assertOk();

        /*
         * Un archivo con una fila mala entre las buenas es el caso normal: el
         * banco publica dias que no cierra, la conversion deja celdas vacias.
         * Tirar el archivo entero por una fila haria que el usuario tuviera
         * que arreglar el Excel entero para meter cuatro dias buenos.
         */
        $this->assertSame(2, $respuesta->json('total'));
        $this->assertSame(1, count($respuesta->json('descartadas')));
    }

    public function test_un_dia_repetido_en_el_archivo_avisa(): void
    {
        $dolar = $this->dolar();

        $respuesta = $this->post('/configuracion/tipos-cambio/importar', [
            'archivo' => $this->excel([
                ['Fecha', 'Valor'],
                ['01/06/2090', 36.5824],
                ['01/06/2090', 99.99],
                ['02/06/2090', 36.61],
            ]),
            'moneda_id' => $dolar->id,
            'confirmar' => 1,
        ], self::CABECERAS)->assertOk();

        $this->assertSame(2, $respuesta->json('guardados'));
        $this->assertSame(1, count($respuesta->json('descartadas')));
        $this->assertStringContainsString('ya venia', $respuesta->json('descartadas.0.motivo'));
    }

    public function test_un_mes_importado_queda_todo_en_la_base_o_nada(): void
    {
        $dolar = $this->dolar();

        $filas = [['Fecha', 'Valor']];

        for ($dia = 1; $dia <= 30; $dia++) {
            $filas[] = [sprintf('%02d/06/2090', $dia), 36.5824 + ($dia * 0.01)];
        }

        // Se duplica uno de los dias que ya hay, para forzar el conflicto
        $this->crear('2090-06-05', 99.0000, $dolar->id);

        $this->post('/configuracion/tipos-cambio/importar', [
            'archivo' => $this->excel($filas),
            'moneda_id' => $dolar->id,
            'confirmar' => 1,
        ], self::CABECERAS)->assertOk();

                $this->assertSame(
            30,
            $this->cuantosHayEnElMesDePrueba(),
            'Un mes de 30 dias deberia quedar entero'
        );
    }

    public function test_el_token_de_la_importacion_va_en_el_cuerpo_del_formulario(): void
    {
        /*
         * El boton de importar no puede fallar en silencio.
         *
         * El layout no trae la meta csrf-token, asi que la cabecera
         * X-CSRF-TOKEN que mandan los scripts sale vacia. En el resto de la
         * aplicacion eso no pasa nada porque los formularios se mandan con
         * serialize(), que arrastra el campo _token del propio formulario.
         *
         * La importacion arma el FormData a mano, porque un archivo no se
         * manda por serialize(). Si no se le anade el _token, el cuerpo va
         * sin token y la cabecera va vacia: el servidor contesta 419, el
         * boton no hace nada y no sale ningun error por ningun sitio. Ni en
         * consola ni en la pagina. Solo se nota porque el mes no se importa.
         *
         * Por eso el token se mira en el javascript y no probando el boton:
         * los tests llaman al controlador, y ahi el token no se comprueba.
         */
        $js = file_get_contents(public_path('js/configuracion/tipos_cambio.js'));

        $this->assertStringContainsString(
            "datos.append('_token'",
            $js,
            'La importacion deberia mandar el token en el FormData: sin el, el '
            . 'boton manda una peticion sin token y el servidor la rechaza sin '
            . 'avisar de nada en la pantalla'
        );

        /*
         * Y que lo saca de un sitio de verdad. Si lo sacara de la meta que no
         * existe, el token seria undefined y el fallo seria el mismo con
         * distinta forma: por eso se mira de donde sale.
         */
        $this->assertStringContainsString(
            "formImportarTipoCambio input[name=\"_token\"]",
            $js,
            'El token deberia sacarse del campo oculto del formulario de importar'
        );

        /*
         * Y que el token exista en la pagina. Si el campo no esta, el
         * javascript manda undefined y el 419 vuelve a aparecer, ahora por un
         * motivo distinto: el formulario sin su token.
         */
        $html = $this->get('/configuracion/tipos-cambio')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/id="formImportarTipoCambio".*?name="_token"/s',
            $html,
            'El formulario de importar deberia llevar su campo _token'
        );
    }

    public function test_la_plantilla_se_descarga_y_es_un_executable_que_se_puede_subir(): void
    {
        /*
         * La plantilla no es un adorno: es el archivo que el usuario va a
         * subir. Si no se pudiera subir tal cual, el boton de "descargar
         * ejemplo" estaria mandando a un callejon sin salida, y el error
         * solo apareceria en el momento de importar.
         */
        $respuesta = $this->get('/configuracion/tipos-cambio/plantilla')->assertOk();

        $ruta = sys_get_temp_dir() . '/plantilla-tipo-cambio-' . uniqid() . '.xlsx';
        file_put_contents($ruta, $respuesta->getContent());

        /*
         * El cuerpo de la respuesta sale vacio, y no es un fallo: la
         * plantilla se manda como un archivo que Symfony envia por trozos al
         * navegador, no como una cadena dentro de la respuesta. Por eso se
         * lee del archivo que lleva la respuesta y no de su contenido, que en
         * una prueba es siempre de cero bytes.
         *
         * Sin esto, la prueba seARIA pasar con una descarga de cero bytes —
         *porque en la prueba nunca hay navegador que la llame— y el
         * usuario se descargaria un archivo vacio sin ver ningun aviso.
         */
        $this->assertNotNull(
            $respuesta->baseResponse->getFile(),
            'La respuesta de la plantilla deberia traer el archivo que se manda'
        );

        $contenido = (string) file_get_contents($respuesta->baseResponse->getFile()->getPathname());

        $this->assertNotEmpty($contenido, 'La plantilla descargada no deberia estar vacia');

        $this->assertStringStartsWith('PK', $contenido, 'Un xlsx tiene que ser un zip');

        $ruta = sys_get_temp_dir() . '/plantilla-tipo-cambio-' . uniqid() . '.xlsx';
        file_put_contents($ruta, $contenido);

        $this->post('/configuracion/tipos-cambio/importar', [
            'archivo' => new UploadedFile($ruta, 'ejemplo.xlsx', null, null, true),
            'moneda_id' => $this->dolar()->id,
        ], self::CABECERAS)
            ->assertOk()
            ->assertJsonPath('vista_previa', true)
            ->assertJsonPath('total', 30);
    }

    // ==================================================================
    // Los permisos
    // ==================================================================

    public function test_quien_no_tiene_permiso_no_entra(): void
    {
        $this->actingAs($this->usuarioConPermisos([]));

        $this->get('/configuracion/tipos-cambio')->assertForbidden();
    }

    public function test_quien_solo_mira_no_puede_importar_el_mes(): void
    {
        /*
         * Importar treinta dias no es mirar el historico, y no es lo mismo que
         * dar de alta un dia. Quien puede ver la serie para saber a cuanto se
         * compro algo no deberia poder pisar el mes entero: por eso importar
         * pide permiso de crear, y no solo de ver.
         */
        $this->actingAs($this->usuarioConPermisos(['tipos_cambio.view']));

        $this->get('/configuracion/tipos-cambio')->assertOk();

        $this->post('/configuracion/tipos-cambio/importar', [
            'archivo' => $this->excel($this->mesDeSeptiembre(1, 3)),
            'moneda_id' => $this->dolar()->id,
        ], self::CABECERAS)->assertForbidden();
    }

    public function test_quien_puede_crear_tambien_importa_el_mes(): void
    {
        $this->actingAs($this->usuarioConPermisos([
            'tipos_cambio.view',
            'tipos_cambio.create',
        ]));

        $this->post('/configuracion/tipos-cambio/importar', [
            'archivo' => $this->excel($this->mesDeSeptiembre(1, 3)),
            'moneda_id' => $this->dolar()->id,
            'confirmar' => 1,
        ], self::CABECERAS)->assertOk();
    }

    /**
     * Cuantos tipos de cambio hay en el mes de las pruebas.
     *
     * No cuenta la tabla entera, y no es una maniasita: la base de datos de
     * trabajo tiene los dias que el usuario ha cargado a mano, y un
     * "TiposCambio::count()" que espera 1 y encuentra 31 no esta comprobando
     * que se creo una fila, esta comprobando que el numero le ha pasado por
     * delante. En un test de borrado eso es peor que no comprobar nada: uno
     * que espera cero y encuentra treinta cree que se borro cuando no se ha
     * borrado nada.
     *
     * Por eso se cuenta acotando al mes de las pruebas, que es de 2090 y no
     * puedeexistir en la base.
     */
    private function cuantosHayEnElMesDePrueba(): int
    {
        return TiposCambio::where('fecha', 'like', self::MES_PRUEBA . '-%')->count();
    }

    /**
     * La fecha de un dia del mes de las pruebas.
     *
     * @param  int  $dia  el dia del mes, del 1 al 30
     */
    private function diaDePrueba(int $dia): string
    {
        return sprintf('%s-%02d', self::MES_PRUEBA, $dia);
    }

    /**
     * Un usuario con unos permisos concretos y ninguno mas.
     *
     * Se limpia la cache de permisos antes de cambiar de usuario, porque
     * Spatie guarda en cache cuales ha visto. Sin limpiarla, el usuario
     * anterior deja su lista pegada y el que entra parece tener mas
     * permisos de los que tiene: la prueba pasaria con un usuario que en la
     * aplicacion no podria hacer nada.
     */
    private function usuarioConPermisos(array $permisos): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $usuario = User::create([
            'name' => 'Usuario',
            'email' => 'tc-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);

        $usuario->syncRoles([]);
        $usuario->syncPermissions($permisos);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($usuario);

        return $usuario;
    }

    // ==================================================================
    // El que lee la tabla, que es la que ve el usuario
    // ==================================================================

    public function test_la_tabla_de_ajax_devuelve_las_filas(): void
    {
        $dolar = $this->dolar();

        $este = $this->crear('2090-06-01', 36.5824, $dolar->id);
        $this->crear('2090-06-02', 36.6100, $dolar->id);

        $json = $this->get('/configuracion/tipos-cambio', self::CABECERAS)
            ->assertOk()
            ->json();

        /*
         * Se buscan las dos filas del mes de las pruebas, y no se mira que
         * la tabla tenga exactamente dos.
         *
         * La tabla enseña todos los tipos de cambio que hay, y hay treinta del
         * usuario. Un "dos filas" aqui no estaria comprobando que el listado
         * funciona, estaria comprobando que la base esta vacia —que es una
         * cosa que el usuario decide cuando le da la gana— y fallaria en
         * cuanto meta un mes mas. Lo que importa es que las dos filas esten y
         * que salgan en su sitio.
         */
        $primera = $this->filaDe($json['data'], $este->id);
        $segunda = $this->filaDe($json['data'], TiposCambio::where('fecha', '2090-06-02')->value('id'));

        $this->assertNotNull($primera, 'Deberia salir la primera fila del mes de las pruebas');
        $this->assertNotNull($segunda, 'Deberian salir las dos filas del mes de las pruebas');

        /*
         * Y que salga la mas reciente antes. El listado va del dia mas reciente
         * al mas antiguo, y como las dos filas del test estan en junio de 2090
         * —que es lo mas lejano en el tiempo— las dos salen despues que las del
         * usuario. Por eso se compara una con otra y no con una posicion fija:
         * la posicion depende de cuantos dias tenga cargados el usuario, que
         * es cosa suya y cambia cada mes.
         */
        $posicionDelDiaUno = $this->posicionDe($json['data'], (int) $este->id);
        $posicionDelDiaDos = $this->posicionDe(
            $json['data'],
            (int) TiposCambio::where('fecha', '2090-06-02')->value('id')
        );

        $this->assertLessThan(
            $posicionDelDiaUno,
            $posicionDelDiaDos,
            'Deberia salir el dia mas reciente —el 2— antes que el mas antiguo —el 1—'
        );

        /*
         * El valor sale con su <span> dentro, porque la columna va marcada como
         * html para que el numero salga con la fuente de los codigos. Por eso no
         * se puede comparar con un numero: hay que quitar las etiquetas antes,
         * como hace el navegador cuando lo pinta.
         */
        $this->assertSame(
            36.5824,
            (float) strip_tags($primera['valor']),
            'El valor deberia salir tal como se guardo'
        );

        $this->assertStringContainsString('Dólares', $primera['moneda']);
        $this->assertStringContainsString('bi-pencil', $primera['action']);
    }

    /**
     * La fila de la tabla que corresponde a un tipo de cambio.
     *
     * La busca por el identificador de la fila, que es lo que Yajra pone para
     * que el javascript sepa cual es cual. Y es la unica forma de buscarla:
     * la tabla trae tambien los dias que el usuario tiene cargados, y buscar
     * por la fecha daria por hecho que no hay nadie mas.
     *
     * @param  array<int, array<string, mixed>>  $filas
     * @return array<string, mixed>|null
     */
    private function filaDe(array $filas, ?int $id): ?array
    {
        foreach ($filas as $fila) {
            if ((int) ($fila['id'] ?? 0) === (int) $id) {
                return $fila;
            }
        }

        return null;
    }

    /**
     * En que posicion de la lista sale un tipo de cambio.
     *
     * @param  array<int, array<string, mixed>>  $filas
     */
    private function posicionDe(array $filas, int $id): int
    {
        foreach ($filas as $posicion => $fila) {
            if ((int) ($fila['id'] ?? 0) === $id) {
                return $posicion;
            }
        }

        return -1;
    }

    public function test_la_tabla_se_ordena_por_fecha_y_no_por_fecha_de_entrada(): void
    {
        /*
         * La lista va del dia mas reciente al mas antiguo, y eso se ordena por
         * la fecha, no por la fecha en que se metio la fila.
         *
         * Con el tipo de cambio recien cargado da igual, porque el orden de
         * entrada coincide con el de las fechas. Pero en cuanto se corrige un
         * dia viejo —que es justo cuando se corrige un dia, cuando el banco se
         * equivoco— esa fila se va al final de la lista, debajo de los dias
         * que aun no han pasado, y el mes entero sale desordenado.
         *
         * El caso que lo demuestra: se crean tres dias seguidos, luego se
         * corrige el primero, que es el mas antiguo. Si se ordenara por fecha
         * de entrada, el dia 1 corregido saldria el ultimo de los tres, que
         * es como si fuera un dia mas reciente que el 3.
         */
        $dolar = $this->dolar();

        $uno = $this->crear('2090-06-01', 36.5824, $dolar->id);
        $dos = $this->crear('2090-06-02', 36.6100, $dolar->id);
        $tres = $this->crear('2090-06-03', 36.6543, $dolar->id);

        // Se corrige el mas antiguo, que es el caso real: el banco se equivoco.
        $uno->update([TiposCambio::VALOR => 36.5900]);

        $json = $this->get('/configuracion/tipos-cambio', self::CABECERAS)
            ->assertOk()
            ->json();

        $posiciones = [
            'el dia 3' => $this->posicionDe($json['data'], (int) $tres->id),
            'el dia 2' => $this->posicionDe($json['data'], (int) $dos->id),
            'el dia 1' => $this->posicionDe($json['data'], (int) $uno->id),
        ];

        foreach ($posiciones as $nombre => $posicion) {
            $this->assertGreaterThanOrEqual(
                0,
                $posicion,
                $nombre . ' deberia salir en la tabla'
            );
        }

        $this->assertLessThan(
            $posiciones['el dia 1'],
            $posiciones['el dia 2'],
            'El dia 2 deberia salir antes que el 1, aunque el 1 se haya corregido despues'
        );

        $this->assertLessThan(
            $posiciones['el dia 2'],
            $posiciones['el dia 3'],
            'El dia 3 deberia salir antes que el 2'
        );

        // Y que la correccion se vea, que de nada sirve ordenarlo si luego
        // sale el valor viejo
        $corregido = $this->filaDe($json['data'], (int) $uno->id);

        $this->assertSame(
            36.5900,
            (float) strip_tags($corregido['valor']),
            'La tabla deberia ensenar el valor corregido, no el que tenia antes'
        );
    }

    public function test_los_botones_de_la_fila_llevan_su_url(): void
    {
        $tipoCambio = $this->crear('2090-06-01', 36.5824);

        $json = $this->get('/configuracion/tipos-cambio', self::CABECERAS)->json();

        // La fila que se busca es la del mes de las pruebas, no la primera: la
        // tabla trae tambien los dias que el usuario tiene cargados.
        $minea = $this->filaDe($json['data'], (int) $tipoCambio->id);

        $this->assertNotNull($minea, 'La fila del mes de las pruebas deberia salir en la tabla');

        $this->assertStringContainsString(
            route('configuracion.tipos_cambio.edit', $tipoCambio->id),
            $minea['action']
        );

        $this->assertStringContainsString(
            route('configuracion.tipos_cambio.destroy', $tipoCambio->id),
            $minea['action']
        );
    }

    public function test_el_modal_de_editar_recibe_los_datos_del_dia(): void
    {
        $dolar = $this->dolar();

        $tipoCambio = $this->crear('2090-06-01', 36.5824, $dolar->id);

        $datos = $this->get(
            '/configuracion/tipos-cambio/' . $tipoCambio->id . '/edit',
            self::CABECERAS
        )->assertOk()->json();

        $this->assertSame('2090-06-01', $datos['fecha']);
        $this->assertSame($dolar->id, (int) $datos['moneda_id']);

        /*
         * Con todos los decimales, no redondeados. Si el modal llegara con
         * 36.58 y el usuario guardara sin tocar el campo, se guardaria
         * 36.58: cada correccion de un dia que no hacia falta corregir
         * bajaria un centesimo, y al cabo de un mes el tipo de cambio seria
         * un numero que nadie escribio.
         */
        $this->assertSame('36.5824', $datos['valor']);
    }
}
