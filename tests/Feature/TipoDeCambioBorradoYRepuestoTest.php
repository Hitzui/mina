<?php
/**
 * El indice unico y el borrado logico, juntos.
 *
 * Estas pruebas son sobre una cosa que no se ve en pantalla y que por eso
 * mismo se ha roto sin que nadie lo notara: la tabla de tipos de cambio tiene
 * un indice unico por (dia, moneda) —que es la regla, un dia no puede tener
 * dos valores— y ademas borra de forma logica, de modo que la fila borrada
 * sigue en la base con su deleted_at puesto.
 *
 * De ahi sale el fallo. Eloquent no ve las filas borradas, asi que la
 * comprobacion de pantalla decia que el dia estaba libre y dejaba insertar,
 * pero el indice unico si las cuenta: MySQL respondia con "Duplicate entry" y
 * en pantalla aparecia un fallo entero de la pagina.
 *
 * Y lo que mas dolia era la importacion del mes, que es donde esto se nota de
 * verdad. El archivo del banco venia mal, se importaba el mes, se veia que
 * estaba mal y se borraba entero. Al volver a subir el mismo archivo, las
 * treinta filas chocaban con las treinta borradas, la transaccion se deshacia
 * entera y no entraba ninguna. Y el mensaje decia que se habian guardado
 * treinta dias.
 *
 * Cada prueba de aqui mira el mismoangled: lo que hace la aplicacion
 * cuando se le pide algo que la fila borrada ya ocupaba.
 */

namespace Tests\Feature;

use App\Models\Moneda;
use App\Models\TiposCambio;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Volver a poner un dia que se habia borrado.
 */
class TipoDeCambioBorradoYRepuestoTest extends TestCase
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
            'email' => 'borrado-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        $this->actingAs($admin);
    }

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

    private function crear(string $fecha, float $valor): TiposCambio
    {
        return TiposCambio::create([
            TiposCambio::FECHA => $fecha,
            TiposCambio::MONEDA_ID => $this->dolar()->id,
            TiposCambio::VALOR => $valor,
            TiposCambio::FUENTE => 'Banco Central de Nicaragua',
        ]);
    }

    private function excelDeJunio(int $desde = 1, int $hasta = 5): UploadedFile
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();

        $hoja->setCellValue('A1', 'Fecha');
        $hoja->setCellValue('B1', 'Valor');

        for ($dia = $desde; $dia <= $hasta; $dia++) {
            $hoja->setCellValue('A' . ($dia + 1), sprintf('%02d/06/2090', $dia));
            $hoja->setCellValue('B' . ($dia + 1), 36.5824 + ($dia * 0.01));
        }

        $ruta = sys_get_temp_dir() . '/tipo-cambio-borrado-' . uniqid() . '.xlsx';

        (new Xlsx($libro))->save($ruta);

        $libro->disconnectWorksheets();

        return new UploadedFile($ruta, 'tipo-cambio.xlsx', null, null, true);
    }

    // ==================================================================
    // La regla, primero: el indice unico sigue prohibiting
    // ==================================================================

    public function test_la_base_sigue_prohibiendo_dos_filas_del_mismo_dia(): void
    {
        $this->crear('2090-06-01', 36.5824);

        /*
         * Se comprueba saltandose Eloquent a proposito, con una insercion
         * directa.
         *
         * El arreglo de la pantalla no quita el indice unico: lo que hace es
         * dejar de pedirle que se lo lleve por delante. Si este test pasara
         * sin dar error, la regla de "un dia no puede tener dos tipos de
         * cambio" habria desaparecido, y es la regla de la que depende que el
         * total de una compra salga siempre igual.
         */
        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('tipos_cambio')->insert([
            'fecha' => '2090-06-01',
            'moneda_id' => $this->dolar()->id,
            'valor' => 36.9,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_una_fila_borrada_todavia_ocupa_el_indice_unico(): void
    {
        $tipoCambio = $this->crear('2090-06-01', 36.5824);

        $tipoCambio->delete();

        /*
         * Se deja escrito porque es el hecho que hace que el resto de estas
         * pruebas tengan sentido: la fila borrada no se ve desde Eloquent,
         * pero para el indice sigue ahi. Si algun dia esto dejara de ser
         * cierto —porque se quita el borrado logico, por ejemplo— el arreglo
         * de abajo sobra, pero no rompe nada.
         */
        $this->assertSame(
            1,
            DB::table('tipos_cambio')->where('fecha', '2090-06-01')->count(),
            'La fila borrada deberia seguir en la tabla'
        );

        $this->assertSame(
            0,
            TiposCambio::where('fecha', '2090-06-01')->count(),
            'Eloquent no deberia ver la fila borrada'
        );
    }

    // ==================================================================
    // El alta desde la pantalla
    // ==================================================================

    public function test_se_vuelve_a_poner_un_dia_que_se_habia_borrado(): void
    {
        $borrado = $this->crear('2090-06-01', 36.5824);
        $borrado->delete();

        $this->post('/configuracion/tipos-cambio', [
            'fecha' => '2090-06-01',
            'moneda_id' => $this->dolar()->id,
            'valor' => 36.99,
        ], self::CABECERAS)->assertOk();

        $this->assertSame(
            1,
            TiposCambio::where('fecha', '2090-06-01')->count(),
            'El dia deberia haber vuelto, y una sola vez'
        );

        $this->assertSame(
            1,
            DB::table('tipos_cambio')->where('fecha', '2090-06-01')->count(),
            'No deberia quedar la fila vieja junto a la nueva'
        );

        $this->assertSame(
            36.99,
            (float) TiposCambio::where('fecha', '2090-06-01')->value('valor'),
            'El dia deberia haber vuelto con el valor nuevo, no con el que tenia antes de borrarse'
        );
    }

    public function test_al_volver_a_poner_el_dia_se_reutiliza_la_misma_fila(): void
    {
        $borrado = $this->crear('2090-06-01', 36.5824);
        $idOriginal = $borrado->id;

        $borrado->delete();

        $this->post('/configuracion/tipos-cambio', [
            'fecha' => '2090-06-01',
            'moneda_id' => $this->dolar()->id,
            'valor' => 36.99,
        ], self::CABECERAS)->assertOk();

        /*
         * Que sea la misma fila y no una nueva no es un detalle: si el dia
         * vuelve con id nuevo, las cosas que lobeesnan mirando por id —un
         * documento que se taso con ese valor, una nota que dice de donde
         * salio— se quedan apuntando a una fila que ya no existe.
         */
        $this->assertSame(
            $idOriginal,
            (int) TiposCambio::where('fecha', '2090-06-01')->value('id'),
            'El dia deberia revive con el mismo id que tenia'
        );
    }

    public function test_se_avisa_cuando_el_dia_ya_esta_y_no_esta_borrado(): void
    {
        $this->crear('2090-06-01', 36.5824);

        $this->post('/configuracion/tipos-cambio', [
            'fecha' => '2090-06-01',
            'moneda_id' => $this->dolar()->id,
            'valor' => 36.99,
        ], self::CABECERAS)
            ->assertStatus(422)
            ->assertJsonValidationErrors('fecha');

        $this->assertSame(
            36.5824,
            (float) TiposCambio::where('fecha', '2090-06-01')->value('valor'),
            'Un dia que ya esta no deberia cambiarse de valor al intentar anadir otro'
        );
    }

    // ==================================================================
    // La edicion
    // ==================================================================

    public function test_mover_un_dia_sobre_otro_borrado_avisa_y_no_pisa(): void
    {
        $borrado = $this->crear('2090-06-01', 36.5824);
        $borrado->delete();

        $otro = $this->crear('2090-06-02', 36.6000);

        $this->put('/configuracion/tipos-cambio/' . $otro->id, [
            'fecha' => '2090-06-01',
            'moneda_id' => $this->dolar()->id,
            'valor' => 36.7000,
        ], self::CABECERAS)
            ->assertStatus(422)
            ->assertJsonValidationErrors('fecha');

        $this->assertSame(
            '2090-06-02',
            $otro->fresh()->fecha->toDateString(),
            'El dia que se estaba editando deberia quedarse donde estaba'
        );

        $this->assertSame(
            1,
            DB::table('tipos_cambio')->where('fecha', '2090-06-01')->count(),
            'La fila borrada deberia seguir borrada, sin revivir por el camino'
        );
    }

    public function test_el_aviso_dice_que_ese_dia_esta_borrado(): void
    {
        $borrado = $this->crear('2090-06-01', 36.5824);
        $borrado->delete();

        $otro = $this->crear('2090-06-02', 36.6000);

        /*
         * El mensaje importa mas de lo que parece. Si el aviso dijera
         * "ese dia ya tiene un tipo de cambio", el usuario buscase en la lista
         * y no lo encontrara, porque esta borrado y la lista no lo enseña:
         * le estaria diciendo una cosa que puede comprobar y sabe que es
         * falsa. Por eso el mensaje tiene que decir que esta borrado.
         */
        $respuesta = $this->put('/configuracion/tipos-cambio/' . $otro->id, [
            'fecha' => '2090-06-01',
            'moneda_id' => $this->dolar()->id,
            'valor' => 36.7000,
        ], self::CABECERAS)->assertStatus(422);

        $this->assertStringContainsString(
            'borrado',
            $respuesta->json('errors.fecha.0'),
            'El aviso deberia decir que ese dia esta borrado'
        );
    }

    // ==================================================================
    // La importacion del mes, que es donde esto se nota
    // ==================================================================

    public function test_reimportar_un_mes_que_se_habia_borrado_entra_entero(): void
    {
        foreach (range(1, 5) as $dia) {
            $this->crear(sprintf('2090-06-%02d', $dia), 99.0)->delete();
        }

        $this->assertSame(
            0,
            TiposCambio::whereBetween('fecha', ['2090-06-01', '2090-06-30'])->count(),
            'El mes deberia estar borrado antes de importar'
        );

        $respuesta = $this->post('/configuracion/tipos-cambio/importar', [
            'archivo' => $this->excelDeJunio(),
            'moneda_id' => $this->dolar()->id,
            'confirmar' => 1,
        ], self::CABECERAS)->assertOk();

        $this->assertSame(
            5,
            $respuesta->json('guardados'),
            'Deberian haberse guardado los cinco dias'
        );

        $this->assertSame(
            5,
            TiposCambio::whereBetween('fecha', ['2090-06-01', '2090-06-30'])->count(),
            'Los cinco dias deberian haber vuelto'
        );
    }

    public function test_reimportar_un_mes_borrado_no_deja_cero_dias_o_todos(): void
    {
        /*
         * El fallo que se daba era de todo o nada al reves: la transaccion
         * entera se deshacia por el primer dia que chocaba, y el mensaje
         * decia que se habian guardado cinco. Este test mira las dos cosas
         * que tienen que ser verdad a la vez, porque basta con que falle una
         * para que el usuario se quede sin mes y sin aviso.
         */
        foreach (range(1, 5) as $dia) {
            $this->crear(sprintf('2090-06-%02d', $dia), 99.0)->delete();
        }

        $this->post('/configuracion/tipos-cambio/importar', [
            'archivo' => $this->excelDeJunio(),
            'moneda_id' => $this->dolar()->id,
            'confirmar' => 1,
        ], self::CABECERAS)->assertOk();

        $vivos = TiposCambio::whereBetween('fecha', ['2090-06-01', '2090-06-30'])->count();

        $this->assertSame(5, $vivos, 'Deberian estar los cinco dias, ni cero ni quince');

        $this->assertSame(
            5,
            DB::table('tipos_cambio')->whereBetween('fecha', ['2090-06-01', '2090-06-30'])->count(),
            'No deberia quedar ninguna fila borrada sin revivir'
        );
    }

    public function test_reimportar_un_mes_borrado_trae_el_valor_del_archivo(): void
    {
        foreach (range(1, 5) as $dia) {
            $this->crear(sprintf('2090-06-%02d', $dia), 99.0)->delete();
        }

        $this->post('/configuracion/tipos-cambio/importar', [
            'archivo' => $this->excelDeJunio(),
            'moneda_id' => $this->dolar()->id,
            'confirmar' => 1,
        ], self::CABECERAS)->assertOk();

        /*
         * El 99.0 era el valor con el que se borraron. Si al revivir la fila
         * se quedara con el, el usuario tendria un mes entero de 99 y no lo
         * sabria hasta que una compra saliera disparada.
         */
        $this->assertSame(
            36.5924,
            round((float) TiposCambio::where('fecha', '2090-06-01')->value('valor'), 4),
            'El dia revivido deberia traer el valor del archivo, no el que tenia al borrarse'
        );
    }

    public function test_la_vista_previa_dice_cuantos_dias_se_recuperan(): void
    {
        /*
         * La vista previa antes de confirmar. Un dia que estaba borrado no es
         * un dia nuevo: la fila ya estaba escrita y lo que hace la importacion
         * es revivirla. Si la pantalla lo contara como nuevo, el usuario
         * veria "5 nuevos" y lo que va a pasar es que vuelven cinco filas.
         */
        $this->crear('2090-06-01', 99.0)->delete();

        $respuesta = $this->post('/configuracion/tipos-cambio/importar', [
            'archivo' => $this->excelDeJunio(),
            'moneda_id' => $this->dolar()->id,
        ], self::CABECERAS)->assertOk();

        $this->assertSame(
            1,
            $respuesta->json('recuperados'),
            'La vista previa deberia decir que se recupera un dia'
        );

        $this->assertSame(
            4,
            $respuesta->json('nuevos'),
            'Los otros cuatro si son nuevos de verdad'
        );
    }

    public function test_la_vista_previa_marca_el_dia_borrado_en_la_tabla(): void
    {
        $this->crear('2090-06-01', 99.0)->delete();

        $respuesta = $this->post('/configuracion/tipos-cambio/importar', [
            'archivo' => $this->excelDeJunio(),
            'moneda_id' => $this->dolar()->id,
        ], self::CABECERAS)->assertOk();

        $cambian = $respuesta->json('cambian');
        $delDiaUno = null;

        foreach ($cambian as $cambio) {
            if ($cambio['fecha'] === '2090-06-01') {
                $delDiaUno = $cambio;
            }
        }

        $this->assertNotNull($delDiaUno, 'El dia borrado deberia salir entre los que cambian');
        $this->assertTrue($delDiaUno['borrado'], 'Deberia venir marcado como borrado');
    }

    public function test_reimportar_un_mes_borrado_no_pisa_la_fuente_del_dia(): void
    {
        /*
         * La fuente de un dia corregido a mano es informacion que el archivo
         * no puede tener. Aqui se comprueba tambien para el dia que estaba
         * borrado, y no solo para el vivo, porque los dos caminos son
         * distintos dentro del codigo: uno actualiza la fila que existe y el
         * otro la revive, y en el camino equivocado se pierde lo que el
         * usuario escribio.
         */
        $borrado = $this->crear('2090-06-01', 99.0);

        $borrado->update([TiposCambio::FUENTE => 'Corrección a mano del 1 de junio']);

        $borrado->delete();

        $this->post('/configuracion/tipos-cambio/importar', [
            'archivo' => $this->excelDeJunio(),
            'moneda_id' => $this->dolar()->id,
            'fuente' => 'Banco Central de Nicaragua',
            'confirmar' => 1,
        ], self::CABECERAS)->assertOk();

        $this->assertSame(
            'Corrección a mano del 1 de junio',
            $borrado->fresh()->fuente,
            'Al revivir el dia, su fuente deberia seguir siendo la que puso el usuario'
        );

        $this->assertSame(
            36.5924,
            round((float) $borrado->fresh()->valor, 4),
            'El valor si debería venir del archivo'
        );
    }

    public function test_un_dia_nuevo_si_toma_la_fuente_del_formulario(): void
    {
        /*
         * El contrapeso del test anterior: la fuente del formulario tiene que
         * ponerse en los dias que no existian. Si se protegiera tambien ahi,
         * los dias nuevos se quedarian sin decir de donde salio el numero, y
         * eso es tan poco utile como que se pise la correccion de al usuario.
         */
        $this->post('/configuracion/tipos-cambio/importar', [
            'archivo' => $this->excelDeJunio(),
            'moneda_id' => $this->dolar()->id,
            'fuente' => 'Banco Central de Nicaragua',
            'confirmar' => 1,
        ], self::CABECERAS)->assertOk();

        $this->assertSame(
            'Banco Central de Nicaragua',
            TiposCambio::where('fecha', '2090-06-01')->value('fuente'),
            'Un dia nuevo deberia llevar la fuente del formulario'
        );
    }

    public function test_la_vista_previa_no_escribe_nada(): void
    {
        $this->crear('2090-06-01', 99.0)->delete();

        $this->post('/configuracion/tipos-cambio/importar', [
            'archivo' => $this->excelDeJunio(),
            'moneda_id' => $this->dolar()->id,
        ], self::CABECERAS)->assertOk();

        $this->assertSame(
            0,
            TiposCambio::whereBetween('fecha', ['2090-06-01', '2090-06-30'])->count(),
            'Mirar el archivo no deberia escribir nada, ni siquiera reviving el dia borrado'
        );

        $this->assertSame(
            1,
            DB::table('tipos_cambio')->where('fecha', '2090-06-01')->count(),
            'La fila borrada deberia seguir borrada despues de mirar el archivo'
        );
    }
}
