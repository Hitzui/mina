<?php

namespace App\Http\Controllers\Configuracion;

use App\DataTables\PreciosOroDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\Moneda;
use App\Models\PreciosOro;
use App\Services\PrecioOroImportador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * El precio del oro de cada dia.
 *
 * Esta tabla no estaba de adorno: recuperaciones dice cuantos gramos salieron
 * del taller, y valoraciones_oro multiplica esos gramos por un precio para
 * decir cuanto valen. Ese precio es esta serie. Sin ella, la valoracion de una
 * recuperacion no tendria con que multiplicar y habria que teclear el valor a
 * mano, perdiendo el rastro de donde salio.
 *
 * Es por eso que la serie no puede estar en la tabla del tipo de cambio, que
 * ya esta hecha y va en su propia pantalla: el precio del oro y el tipo de
 * cambio son dos series distintas, con unidades distintas —el gramo y la
 * moneda— y monedas distintas, y meterlas en la misma tabla daria dos sitios
 * donde mirar el precio del oro. Los dos acabarían discrepando.
 *
 * Y por eso el alta y la edicion van en modal sobre la lista, como las del
 * tipo de cambio: son cinco campos y una pantalla entera para escribir un
 * numero de un dia seria mas ruido que ayuda.
 *
 * Aqui la diferencia con el tipo de cambio, y hay una sola, pero es la que
 * define como se maneja esta pantalla: el cero si se admite.
 *
 * Un cero en el precio del oro no significa que el oro valia cero —eso no ha
 * pasado nunca— sino que de ese dia no se sabe el precio. Pasa: se carga la
 * serie del mes a mitad, o el banco no publica un dia, o el taller no consulto
 * ese dia. Es un dato que se sabe que falta, y es distinto de no tener nada.
 *
 * Lo que no se puede es que ese cero salga multiplicado. Por eso, al leer el
 * precio de un dia para valorar, los ceros se saltan y se cae en el ultimo
 * precio que si se sabe, que es lo que se ha hecho siempre en el taller: el
 * metal no cambia de valor de un dia a otro porque el taller no consultara el
 * banco. Y si no hay ninguno anterior, la valoracion dice que no hay con que y
 * no inventa un numero. Un documento con valor cero es lo peor de los dos
 * mundos, porque sale un numero y no avisa de nada.
 */
class PrecioOroController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        $this->authorizeModule('configuracion.precios_oro', ['index', 'show']);

        /*
         * La importacion se cuelga del permiso de crear, y por el mismo
         * motivo que en el tipo de cambio: cargar treinta dias de golpe no es
         * lo mismo que mirar la serie, y quien puede corregir un dia a mano ya
         * tiene el mismo poder sobre la serie. Lo que cambia es que se ve la
         * vista previa antes de confirmar.
         */
        $this->middleware('permission:configuracion.precios_oro.create')
            ->only(['importar', 'plantilla']);
    }

    public function index(PreciosOroDataTable $dataTable)
    {
        return $dataTable->render(
            'configuracion.precios_oro.index',
            [
                'monedas' => Moneda::where('estado', true)->orderBy('nombre')->get(),
                'unidades' => PreciosOro::UNIDADES,
                'title' => 'Precios del oro',
                'breadcrumbs' => [
                    ['label' => 'Inicio', 'url' => route('home')],
                    ['label' => 'Configuración'],
                    ['label' => 'Precios del oro'],
                ],
            ]
        );
    }

    public function create()
    {
        return response()->json(['ok' => true]);
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);

        /*
         * Un dia, una unidad y una moneda no pueden tener dos precios, y eso
         * esta puesto como indice unico en la base. Aqui no se comprueba con
         * la regla unique de Laravel a proposito: esa mira la tabla entera,
         * borrados logicos incluidos, y su aviso seria "ese dia ya esta" para
         * un dia que no se ve en ninguna parte. Con la busqueda se distingue,
         * y el borrado se revive.
         */
        [$existe, $estaBorrada] = PreciosOro::existeClave($this->claveDe($datos));

        if ($existe && ! $estaBorrada) {
            throw ValidationException::withMessages([
                PreciosOro::FECHA => 'Ese día ya tiene precio para esa unidad y esa moneda. '
                    . 'Edítalo en vez de añadir otro.',
            ]);
        }

        $precio = PreciosOro::crearORestaurar($this->claveDe($datos), $datos);

        Alert::success($estaBorrada
            ? 'Precio recuperado. Estaba dado de baja y se ha vuelto a poner.'
            : ($datos[PreciosOro::PRECIO] > 0
                ? 'Precio del oro guardado'
                : 'Día guardado con el precio en cero, que quiere decir que de ese día no se sabe'));

        return response()->json(['ok' => true, 'id' => $precio->id]);
    }

    public function show(PreciosOro $precioOro)
    {
        return response()->json($this->datosParaModal($precioOro) + [
            'vigente' => $this->precioEnVigencia($precioOro),
        ]);
    }

    public function edit(PreciosOro $precioOro)
    {
        return response()->json($this->datosParaModal($precioOro));
    }

    public function update(Request $request, PreciosOro $precioOro)
    {
        $datos = $this->validar($request);

        $cambiaClave = $datos[PreciosOro::FECHA] !== $precioOro->fecha->toDateString()
            || $datos[PreciosOro::UNIDAD] !== $precioOro->unidad
            || (int) $datos[PreciosOro::MONEDA_ID] !== (int) $precioOro->moneda_id;

        /*
         * Mover un precio sobre otro dia que ya lo tiene es tapar uno y dejar
         * el viejo sin nada, que es justo lo que hace que la serie tenga huecos
         * por los que el oro se valoraria con un precio de hace un mes sin que
         * nada lo diga. Se comprueba antes de guardar, y no solo con el indice
         * unico de la base: ese llega tarde, con un error entero de MySQL que
         * no le dice a nadie de que se trata.
         */
        if ($cambiaClave) {
            [$ocupado, $estaBorrado] = PreciosOro::existeClave($this->claveDe($datos), $precioOro->id);

            if ($ocupado) {
                throw ValidationException::withMessages([
                    PreciosOro::FECHA => $estaBorrado
                        ? 'Ese día tuvo precio, pero está borrado. Un día no puede tener dos precios: vuelve a poner el borrado desde la lista, o guarda este en otro día.'
                        : 'Ese día ya tiene precio para esa unidad y esa moneda, y no '
                            . 'se pueden tener dos. Corrige el que ya hay, o cambia de día este.',
                ]);
            }
        }

        $antes = (float) $precioOro->precio;

        $precioOro->update($datos);

        if ($cambiaClave) {
            Alert::success('Precio guardado. Ojo: el día, la unidad o la moneda han cambiado, '
                . 'así que este precio ya no cuenta para los documentos del día anterior.');
        } elseif ($antes > 0 && (float) $datos[PreciosOro::PRECIO] <= 0) {
            Alert::warning('El precio de ese día ahora es cero. Con cero, una valoración '
                . 'no multiplica por cero: usa el último precio que se sepa. Si lo que querías '
                . 'era quitar el precio de ese día, bórralo.');
        } else {
            Alert::success('Precio del oro guardado');
        }

        return response()->json(['ok' => true, 'id' => $precioOro->id]);
    }

    public function destroy(PreciosOro $precioOro)
    {
        $precioOro->delete();

        Alert::success('Precio eliminado');

        return response()->json(['ok' => true]);
    }

    // ==================================================================
    // La importacion del mes
    // ==================================================================

    /**
     * Muestra lo que hay en el archivo, sin guardar nada.
     *
     * Dos pasos por el mismo motivo que el tipo de cambio: leer treinta dias y
     * escribirlos son dos cosas distintas que pueden pasar en dias distintos.
     * Por eso el boton de confirmar dice cuantos dias va a escribir y cuales
     * ya estan, y solo con esa informacion delante se pulsa.
     */
    public function importar(Request $request)
    {
        $archivo = $request->file('archivo');

        if (! $archivo) {
            throw ValidationException::withMessages([
                'archivo' => 'Elija el archivo de Excel.',
            ]);
        }

        $monedaId = $request->integer('moneda_id');
        $unidad = $this->unidadDe($request->input('unidad'));

        if (! Moneda::where('id', $monedaId)->exists()) {
            throw ValidationException::withMessages([
                'moneda_id' => 'Elija de qué moneda es el precio.',
            ]);
        }

        $ruta = $archivo->getRealPath();

        if (! $ruta || ! is_readable($ruta)) {
            throw ValidationException::withMessages([
                'archivo' => 'No se pudo leer el archivo que se subió.',
            ]);
        }

        $importador = new PrecioOroImportador();

        try {
            $filas = $importador->leer($ruta);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'archivo' => $e->getMessage(),
            ]);
        }

        $descartadas = $importador->descartadas();

        /*
         * Que hay ya de esos dias, mirando tambien entre los borrados.
         *
         * El keyBy() va con una funcion y no con el nombre de la columna: al
         * traer la fila como modelo, la fecha viene ya convertida en un
         * objeto Carbon y la clave se guardaria como "2090-06-01 00:00:00",
         * que no es como viene la fecha en el archivo y no encontraria nada.
         */
        $existentes = PreciosOro::withTrashed()
            ->whereIn(PreciosOro::FECHA, array_column($filas, 'fecha'))
            ->where(PreciosOro::UNIDAD, $unidad)
            ->where(PreciosOro::MONEDA_ID, $monedaId)
            ->get([PreciosOro::FECHA, PreciosOro::PRECIO, 'deleted_at'])
            ->keyBy(fn (PreciosOro $una) => $una->fecha->toDateString());

        $recuperados = 0;
        $cambian = [];
        $sinCeroQuePisando = [];

        foreach ($existentes as $fila) {
            if ($fila->deleted_at !== null) {
                $recuperados++;
            }
        }

        foreach ($filas as $fila) {
            $anterior = $existentes[$fila['fecha']] ?? null;

            if ($anterior === null) {
                continue;
            }

            $antes = (float) $anterior->precio;
            $ahora = (float) $fila['valor'];

            if (abs($antes - $ahora) < 0.00005) {
                continue;
            }

            $cambian[] = [
                'fecha' => $fila['fecha'],
                'antes' => $antes,
                'despues' => $ahora,
                'borrado' => $anterior->deleted_at !== null,
            ];

            /*
             * Un cero en el archivo no pisa un precio que ya se sabe.
             *
             * En esta tabla el cero tiene un significado —"de ese dia no se
             * sabe"— y el archivo del banco no lo pone nunca. Si lo pusiera,
             * seria porque el archivo traia la celda vacia y el lector la leyo
             * como cero, que es justo el fallo que hay que evitar: dejaria un
             * mes entero sin precios y con la pantalla llena de ceros que
             * parecen cotizaciones. Se cuenta y se dice, y el dia se queda con
             * el precio que tenia.
             */
            if ($ahora <= 0 && $antes > 0) {
                $sinCeroQuePisando[] = [
                    'fecha' => $fila['fecha'],
                    'antes' => $antes,
                ];
            }
        }

        $nuevos = count($filas) - count($existentes);

        if (! $request->boolean('confirmar')) {
            return response()->json([
                'ok' => true,
                'vista_previa' => true,
                'moneda_id' => $monedaId,
                'unidad' => $unidad,
                'total' => count($filas),
                'nuevos' => max(0, $nuevos),
                'ya_existentes' => count($existentes),
                'recuperados' => $recuperados,
                'sin_cambiar' => count($filas) - count($existentes) - count($cambian),
                'cambian' => $cambian,
                'sin_cero_que_pisando' => $sinCeroQuePisando,
                'descartadas' => $descartadas,
                'desde' => $filas[0]['fecha'],
                'hasta' => $filas[count($filas) - 1]['fecha'],
            ]);
        }

        $guardados = $this->escribir($filas, $monedaId, $unidad, $request->input('fuente'), $sinCeroQuePisando);

        return response()->json([
            'ok' => true,
            'guardados' => $guardados,
            'nuevos' => max(0, $nuevos),
            'cambiados' => count($cambian),
            'recuperados' => $recuperados,
            'zeros_ignorados' => count($sinCeroQuePisando),
            'descartadas' => $descartadas,
        ]);
    }

    /**
     * Escribe las filas leidas, actualizando las que ya habia.
     *
     * Van todas en una transaccion. Sin ella, un archivo de treinta dias que
     * falla en el dia dieciocho deja los diecisiete primeros guardados y el
     * usuario no tiene forma de saber cuales son.
     */
    private function escribir(
        array $filas,
        int $monedaId,
        string $unidad,
        ?string $fuente,
        array $sinCeroQuePisando
    ): int {
        /*
         * Los dias de los que el archivo trae un cero y ya habia un precio
         * real se sacan antes de escribir. Lo decide el servidor y no el
         * cliente a proposito: la vista previa que se le enseño al usuario ya
         * lo decia, y volver a calcularlo aqui con los datos que mande el
         * navegador abriria la puerta a que se escribiera un cero que la
         * pantalla no enseño.
         */
        $ignorarCeros = array_column($sinCeroQuePisando, 'fecha');

        return DB::transaction(function () use ($filas, $monedaId, $unidad, $fuente, $ignorarCeros) {
            $guardados = 0;

            foreach ($filas as $fila) {
                if (in_array($fila['fecha'], $ignorarCeros, true)) {
                    continue;
                }

                $clave = [
                    PreciosOro::FECHA => $fila['fecha'],
                    PreciosOro::UNIDAD => $unidad,
                    PreciosOro::MONEDA_ID => $monedaId,
                ];

                /*
                 * El precio SI se cambia: el archivo es la fuente del mes y
                 * manda sobre lo que hubiera. La fuente NO se toca nunca, y
                 * tampoco la unidad ni la moneda, que son de la fila y no del
                 * archivo.
                 */
                PreciosOro::crearORestaurar($clave, [
                    PreciosOro::FECHA => $fila['fecha'],
                    PreciosOro::UNIDAD => $unidad,
                    PreciosOro::MONEDA_ID => $monedaId,
                    PreciosOro::PRECIO => $fila['valor'],
                ], [
                    PreciosOro::FUENTE => $fuente,
                ]);

                $guardados++;
            }

            return $guardados;
        });
    }

    /**
     * Un Excel de ejemplo, con los dias del mes que hay que cargar.
     */
    public function plantilla()
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Precio del oro');

        $hoja->fromArray(['Fecha', 'Precio'], null, 'A1');

        $hoy = today();
        $mes = (int) $hoy->format('n');
        $anio = (int) $hoy->format('Y');

        // Valores inventados, pero con la forma de los de verdad: cuatro
        // decimales, que es como se cotiza el gramo.
        for ($dia = 1; $dia <= $hoy->daysInMonth; $dia++) {
            $fecha = sprintf('%02d/%02d/%04d', $dia, $mes, $anio);

            $hoja->fromArray([$fecha, number_format(78.4521 + ($dia * 0.0137), 4, '.', '')], null, 'A' . ($dia + 1));
        }

        $hoja->getStyle('A1:B1')->getFont()->setBold(true);

        foreach (['A', 'B'] as $columna) {
            $hoja->getColumnDimension($columna)->setAutoSize(true);
        }

        /*
         * Se escribe en un temporal y se devuelve con response()->download(),
         * no con un cuerpo en memoria. PhpSpreadsheet escribe a una ruta, no a
         * una cadena, y si se lee el temporal para devolverlo con file_get_
         * contents() el archivo se borra antes de que la respuesta llegue al
         * navegador: la respuesta dice 200 y lo que se descarga son cero bytes.
         */
        $ruta = tempnam(sys_get_temp_dir(), 'plantilla-oro') . '.xlsx';

        (new Xlsx($libro))->save($ruta);

        $libro->disconnectWorksheets();

        return response()->download($ruta, 'precio-del-oro-ejemplo.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ==================================================================
    // Lo de siempre
    // ==================================================================

    /**
     * La clave que identifica un precio: el dia, la unidad y la moneda.
     *
     * @return array<string, mixed>
     */
    private function claveDe(array $datos): array
    {
        return [
            PreciosOro::FECHA => $datos[PreciosOro::FECHA],
            PreciosOro::UNIDAD => $datos[PreciosOro::UNIDAD],
            PreciosOro::MONEDA_ID => $datos[PreciosOro::MONEDA_ID],
        ];
    }

    /**
     * El precio que se usaria para valorar en la fecha de esta fila.
     *
     * Va en la ficha y no en la lista porque es la pregunta que se hace al
     * mirar una fila: si ese dia vale algo o no. Con la fila en cero, la
     * respuesta es que para ese dia se usa otro, y cual.
     *
     * @return array{de_que_dia: string, gramo: float}|null
     */
    private function precioEnVigencia(PreciosOro $precioOro): ?array
    {
        $vigente = PreciosOro::precioDelGramo(
            $precioOro->fecha->toDateString(),
            (int) $precioOro->moneda_id
        );

        if ($vigente === null) {
            return null;
        }

        return [
            'de_que_dia' => $vigente['de_que_dia'],
            'gramo' => $vigente['gramo'],
        ];
    }

    /**
     * La unidad, ya normalizada y contra la lista cerrada.
     *
     * Se pasa por aqui y no se usa tal cual lo que venga del formulario
     * porque la columna es una cadena de veinte caracteres: "Gramo" y "gramo"
     * serian dos unidades distintas para el indice unico y el mismo gramo
     * existiria dos veces en la serie sin que nada lo notara.
     */
    private function unidadDe(?string $unidad): string
    {
        return strtolower(trim((string) $unidad));
    }

    private function datosParaModal(PreciosOro $precioOro): array
    {
        return [
            'id' => $precioOro->id,
            'fecha' => $precioOro->fecha->format('Y-m-d'),
            'precio' => $precioOro->precioDesconocido()
                ? '0'
                : rtrim(rtrim(number_format((float) $precioOro->precio, 4, '.', ''), '0'), '.'),
            'unidad' => $precioOro->unidad,
            'moneda_id' => (int) $precioOro->moneda_id,
            'fuente' => $precioOro->fuente,
            'observaciones' => $precioOro->observaciones,
        ];
    }

    private function validar(Request $request): array
    {
        /*
         * La unidad se pasa a minusculas ANTES de validar, y no despues.
         *
         * La lista de unidades es cerrada y esta en minusculas, asi que
         * validar primero rechazaria "Onza Troy" aunque sea la misma unidad que
         * "onza troy". Y no es un caso de laboratorio: el desplegable de la
         * pantalla la manda como esta en la lista, pero el archivo importado
         * puede traerla como la escribio quien lo armo, y ahi es normal que
         * venga con mayusculas.
         *
         * Con esto, "Onza Troy" y "onza troy" se guardan igual, y el indice
         * unico hace su trabajo, que es avisar cuando de verdad se repite.
         */
        if ($request->has('unidad')) {
            $request->merge([
                'unidad' => $this->unidadDe($request->input('unidad')),
            ]);
        }

        $datos = $request->validate([
            PreciosOro::FECHA => ['required', 'date'],
            /*
             * El precio tiene min:0 y no gt:0, y la diferencia con el tipo de
             * cambio es la regla de esta tabla, no un descuido: aqui el cero
             * significa "de este dia no se sabe el precio", que es un dato
             * real y distinto de no tener nada. Lo que no se puede es que ese
             * cero salga multiplicado, y de eso se encarga vigentePara(), que
             * se salta los ceros al buscar el precio de un dia.
             */
            PreciosOro::PRECIO => ['required', 'numeric', 'min:0'],
            PreciosOro::UNIDAD => ['required', 'string', Rule::in(array_keys(PreciosOro::UNIDADES))],
            PreciosOro::MONEDA_ID => ['required', 'integer', Rule::exists('monedas', 'id')],
            PreciosOro::FUENTE => ['nullable', 'string', 'max:150'],
            PreciosOro::OBSERVACIONES => ['nullable', 'string', 'max:1000'],
        ], [
            PreciosOro::FECHA . '.required' => 'Elija el día.',
            PreciosOro::FECHA . '.date' => 'El día no es una fecha válida.',
            PreciosOro::PRECIO . '.required' => 'Indique el precio.',
            PreciosOro::PRECIO . '.numeric' => 'El precio tiene que ser un número. Con coma o con punto, pero solo uno de los dos como decimal.',
            /*
             * En negativo no tiene lectura: un precio negativo no significa
             * que no se sepa, significa que la columna esta al reves.
             */
            PreciosOro::PRECIO . '.min' => 'El precio no puede ser negativo. Si de ese día no se sabe, deje el cero: eso quiere decir "no se sabe", y al valorar se usará el último precio que sí se sepa.',
            PreciosOro::UNIDAD . '.required' => 'Elija la unidad.',
            PreciosOro::UNIDAD . '.in' => 'Esa unidad no está en la lista.',
            PreciosOro::MONEDA_ID . '.required' => 'Elija la moneda.',
            PreciosOro::MONEDA_ID . '.exists' => 'Esa moneda no está en el catálogo.',
        ]);

        $datos[PreciosOro::UNIDAD] = $this->unidadDe($datos[PreciosOro::UNIDAD]);

        return $datos;
    }
}
