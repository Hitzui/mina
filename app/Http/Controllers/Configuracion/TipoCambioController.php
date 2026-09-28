<?php

namespace App\Http\Controllers\Configuracion;

use App\DataTables\TiposCambioDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\Moneda;
use App\Models\TiposCambio;
use App\Services\TipoCambioImportador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * El tipo de cambio de cada dia.
 *
 * Es lo que convierte los dolares a cordoba, asi que de la fila de aqui
 * dependen el total en NIO de las compras y el valor con que se tasa el
 * material que entra al almacen. Por eso el alta de un dia y la edicion del
 * mismo se hacen en modal, sobre la lista: son cuatro campos, y una pantalla
 * entera para escribir un numero seria mas ruido que ayuda.
 *
 * La serie tiene una regla que no se negocia: un dia no puede tener dos
 * tipos de cambio para la misma moneda, y esta puesto como indice unico en
 * la base, no como una comprobacion en la pantalla. Es que la fila se usa
 * para convertir, y si un dia tuviera dos valores habria que elegir uno
 * sin que nada dijera cual: el total de una compra saldria distinto segun
 * por donde se mirara.
 *
 * Y va en Configuracion, no en Inventario, aunque lo usen el almacen y las
 * compras. No es ni una compra ni un material: es el dato maestro que define
 * como se mide todo lo demas, asi que va con las demas listas maestras. Lo
 * que decide de verdad es que no importa de donde se lea la serie: el total en
 * NIO de una compra y el valor con que se tasa el material salen de la misma
 * funcion, justamente para que las dos cosas nunca se separen.
 */
class TipoCambioController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        $this->authorizeModule('tipos_cambio', ['index', 'show']);

        /*
         * La importacion se cuelga del permiso de crear.
         *
         * Importar un mes son treinta filas de golpe, y no es lo mismo que
         * mirar la serie ni que dar de alta un dia. Se le pasa el mismo
         * permiso de crear, y no uno nuevo, por una razon que no es de
         * permisos: quien puede corregir un dia a mano ya tiene el mismo
         * poder sobre la serie que el que puede importarla. La diferencia
         * esta en lo que se ve antes de hacerlo —la vista previa dice cuantos
         * dias cambian y de cuanto a cuanto—, no en quien lo puede hacer.
         *
         * Plantilla va con el mismo porque descargar el ejemplo es el primer
         * paso de importar: no es una accion aparte.
         *
         * Y se repite el permiso de ver en el index y en el show, que es lo
         * que hace el trait. Esta linea esta porque el trait solo engancha los
         * metodos del resource por su nombre, y un metodo que no esta en la
         * lista se queda sin comprobar: el permiso simplemente no se mira, sin
         * avisar, y el metodo se escribe y funciona.
         */
        $this->middleware('permission:tipos_cambio.create')
            ->only(['importar', 'plantilla']);
    }

    /**
     * La lista del tipo de cambio.
     *
     * Se pinta con render() y no con view(), y no es una preferencia: el
     * metodo render() es el que monta el <table> y deja en la vista las
     * variables que necesita para los scripts —el id de la tabla, las
     * columnas— que son cosas que solo el sabe.
     *
     * Pasando el DataTable tal cual a view(), la vista recibe el objeto y
     * llama a $dataTable->table(), que no existe: ese metodo no es del
     * DataTable sino del constructor de html, al que se llega pasando por
     * render(). El error que sale es "Method ...::table does not exist",
     * que no dice nada de que lo que falte es usar la otra via.
     */
    public function index(TiposCambioDataTable $dataTable)
    {
        return $dataTable->render(
            'configuracion.tipos_cambio.index',
            [
                'monedas' => Moneda::where('estado', true)->orderBy('nombre')->get(),
                'title' => 'Tipo de cambio',
                'breadcrumbs' => [
                    ['label' => 'Inicio', 'url' => route('home')],
                    ['label' => 'Configuración'],
                    ['label' => 'Tipo de cambio'],
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
         * Se comprueba que el dia no este ya antes de insertar, y no solo
         * confiando en el indice unico.
         *
         * El indice unico es la red de seguridad: si dos personas pulsan
         * guardar a la vez sobre el mismo dia, una entra y la otra recibe un
         * error de base de datos, que en pantalla sale como un fallo entero
         * sin decir de que va. Con esta comprobacion, la que llega segundo ve
         * un mensaje que si dice algo: que ese dia ya esta puesto y que lo
         * que se quiere es cambiarlo.
         */
        [$yaExiste, $estaBorrada] = TiposCambio::existeClave([
            TiposCambio::FECHA => $datos[TiposCambio::FECHA],
            TiposCambio::MONEDA_ID => $datos[TiposCambio::MONEDA_ID],
        ]);

        if ($yaExiste && ! $estaBorrada) {
            throw ValidationException::withMessages([
                TiposCambio::FECHA => 'Ese día ya tiene un tipo de cambio para esa moneda. Edítalo en vez de añadir otro.',
            ]);
        }

        /*
         * Si lo que hay esta borrado, la fila se revive en vez de crear otra.
         *
         * El indice unico prohibe tener dos filas del mismo dia y moneda, y
         * la borrada sigue contando para el, aunque Eloquent no la vea. Asi
         * que un create() a pelo aqui revienta con un "Duplicate entry" de
         * MySQL: un fallo de pagina entera por volver a poner un dia que se
         * habia borrado, que es justo lo que se hace al corregir una
         * equivocacion.
         */
        $tipoCambio = TiposCambio::crearORestaurar([
            TiposCambio::FECHA => $datos[TiposCambio::FECHA],
            TiposCambio::MONEDA_ID => $datos[TiposCambio::MONEDA_ID],
        ], $datos);

        $this->limpiarCache();

        Alert::success('Tipo de cambio guardado');

        return response()->json([
            'ok' => true,
            'id' => $tipoCambio->id,
        ]);
    }

    public function show(TiposCambio $tipoCambio)
    {
        return $this->datosParaModal($tipoCambio);
    }

    public function edit(TiposCambio $tipoCambio)
    {
        return $this->datosParaModal($tipoCambio);
    }

    public function update(Request $request, TiposCambio $tipoCambio)
    {
        $datos = $this->validar($request, $tipoCambio);

        /*
         * Cambiar la moneda o la fecha de un dia que ya esta es dejar un
         * hueco y tapar otro. Se avisa, porque se puede querer a proposito
         * —equivocarse al meter la fecha es normal— pero tiene que ser
         * derlo.
         */
        $cambiaDiaOMoneda = $datos[TiposCambio::FECHA] !== $tipoCambio->fecha->toDateString()
            || (int) $datos[TiposCambio::MONEDA_ID] !== (int) $tipoCambio->moneda_id;

        /*
         * Mover un tipo de cambio a un dia que ya tiene uno no se puede
         * hacer, y se comprueba antes de guardar.
         *
         * El indice unico de la base lo prohibe igual, pero si se llega
         * hasta el guardado lo que sale es un error de MySQL entero, con su
         * numero y su sql, en una pantalla de edicion normal. El usuario ve
         * un fallo que no dice nada de que ha hecho mal, y lo que ha hecho
         * mal es una cosa facil de explicar: ese dia ya tiene su valor.
         *
         * Con la comprobacion, lo que sale es un aviso en el campo del dia y
         * el otro tipo de cambio se queda como estaba, sin pisarse.
         */
        if ($cambiaDiaOMoneda) {
            /*
             * La busqueda mira tambien entre las filas borradas, y el
             * mensaje distingue las dos cosas, porque la respuesta es otra:
             * una fila viva se corrige en su sitio, y una borrada hay que
             * quitarla del medio o poner este dia en otro sitio.
             */
            [$ocupado, $estaBorrada] = TiposCambio::existeClave([
                TiposCambio::FECHA => $datos[TiposCambio::FECHA],
                TiposCambio::MONEDA_ID => $datos[TiposCambio::MONEDA_ID],
            ], $tipoCambio->id);

            if ($ocupado) {
                throw ValidationException::withMessages([
                    TiposCambio::FECHA => $estaBorrada
                        ? 'Ese día tuvo un tipo de cambio, pero está borrado. Un día no puede tener dos: vuelve a poner el borrado desde la lista, o guarda este en otro día.'
                        : 'Ese día ya tiene un tipo de cambio para esa moneda, '
                            . 'y no se pueden tener dos. Corrige el que ya hay, o cambia de día este.',
                ]);
            }
        }

        $tipoCambio->update($datos);

        $this->limpiarCache();

        Alert::success($cambiaDiaOMoneda
            ? 'Tipo de cambio guardado. Ojo: el día y la moneda han cambiado, así que este valor ya no cuenta para los documentos del día anterior.'
            : 'Tipo de cambio guardado');

        return response()->json(['ok' => true, 'id' => $tipoCambio->id]);
    }

    public function destroy(Request $request, TiposCambio $tipoCambio)
    {
        /*
         * Borrar un dia es dejar al almacen sin un valor con el que tasar lo
         * que entre ese dia. Se dice cual es el dia antes de hacerlo, y se
         * dice tambien que es reversible editando el archivo, no volviendo
         * atras a mano.
         */
        $confirmado = $request->boolean('confirmado');

        if (! $confirmado) {
            return response()->json([
                'ok' => false,
                'requiere_confirmacion' => true,
                'mensaje' => 'Se va a borrar el tipo de cambio del '
                    . $tipoCambio->fecha->format('d/m/Y') . '.'
                    . ($request->boolean('es_ultimo')
                        ? ' Es el único que hay para esa moneda: hasta que se ponga otro, las compras y el material de ese día saldrán sin equivalente en córdoba.'
                        : ''),
            ], 409);
        }

        $tipoCambio->delete();

        $this->limpiarCache();

        Alert::success('Tipo de cambio eliminado');

        return response()->json(['ok' => true]);
    }

    // ==================================================================
    // La importacion del mes
    // ==================================================================

    /**
     * Muestra lo que hay en el archivo, sin guardar nada.
     *
     * Es una pantalla aparte y no un paso dentro del alta porque leer treinta
     * dias y escribirlos son dos cosas distintas que pueden pasar en dias
     * distintos: hoy se sube el archivo, mañana se sube el de octubre. Por eso
     * el boton de confirmar dice cuantos dias va a escribir y cuales ya
     * estan, y solo con esa informacion delante se pulsa.
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

        if (! Moneda::where('id', $monedaId)->exists()) {
            throw ValidationException::withMessages([
                'moneda_id' => 'Elija de qué moneda es el tipo de cambio.',
            ]);
        }

        $ruta = $archivo->getRealPath();

        if (! $ruta || ! is_readable($ruta)) {
            throw ValidationException::withMessages([
                'archivo' => 'No se pudo leer el archivo que se subió.',
            ]);
        }

        $importador = new TipoCambioImportador();

        try {
            $filas = $importador->leer($ruta);
        } catch (\RuntimeException $e) {
            /*
             * El archivo no se puede leer, y eso no es culpa de los datos que
             * el usuario metio: es un archivo con otra forma. Se devuelve como
             * error de validacion y no como un fallo de la pagina, para que
             * salga pegado al campo del archivo y no como una pantalla en
             * blanco.
             */
            throw ValidationException::withMessages([
                'archivo' => $e->getMessage(),
            ]);
        }

        $descartadas = $importador->descartadas();

        /*
         * Que hay ya de esos dias, para poder decir cuantos se van a cambiar.
         *
         * Se buscan tambien entre los borrados, y no por curiosidad. Si el
         * mes se importo, se borro y se vuelve a subir el mismo archivo —que
         * es lo que pasa cuando el archivo del banco venia mal— esos dias no
         * son nuevos: se van a recuperar. Contarlos como nuevos haria que la
         * vista previa dijera "30 dias nuevos" y lo que ocurre es que
         * vuelven treinta filas que estaban borradas, que no es lo mismo y
         * cambia lo que el usuario cree que va a pasar.
         *
         * El keyBy() es con una funcion y no con el nombre de la columna a
         * proposito. Al traer la fila como modelo, la fecha viene ya
         * convertida en un objeto Carbon, y keyBy() la guardaria como
         * "2090-06-01 00:00:00": al buscar luego el dia del archivo, que es
         * "2090-06-01", no se encontraria, y todos los dias saldrian como
         * nuevos aunque estuvieran ahi. Con toDateString() la clave es la
         * fecha tal cual la trae el archivo.
         */
        $existentes = TiposCambio::withTrashed()
            ->whereIn(TiposCambio::FECHA, array_column($filas, 'fecha'))
            ->where(TiposCambio::MONEDA_ID, $monedaId)
            ->get([TiposCambio::FECHA, TiposCambio::VALOR, 'deleted_at'])
            ->keyBy(fn (TiposCambio $una) => $una->fecha->toDateString());

        $recuperados = 0;

        foreach ($existentes as $dia => $fila) {
            if ($fila->deleted_at !== null) {
                $recuperados++;
            }
        }

        $cambian = [];

        foreach ($filas as $fila) {
            $anterior = $existentes[$fila['fecha']] ?? null;

            if ($anterior !== null && abs((float) $anterior->valor - (float) $fila['valor']) < 0.000001) {
                continue;
            }

            if ($anterior !== null) {
                $cambian[] = [
                    'fecha' => $fila['fecha'],
                    'antes' => (float) $anterior->valor,
                    'despues' => (float) $fila['valor'],
                    'borrado' => $anterior->deleted_at !== null,
                ];
            }
        }

        $nuevos = count($filas) - count($existentes);

        /*
         * Vista previa y nada mas: se devuelve lo que se ha leido y se espera
         * a que el usuario confirme con un segundo paso. Importar sin mirar
         * es escribir treinta dias de conversiones sin haber visto ni uno, y
         * un archivo mal ledo se lleva por delante todos los totales del mes
         * sin que nada se note hasta que un cuadre no cuadra.
         */
        if (! $request->boolean('confirmar')) {
            return response()->json([
                'ok' => true,
                'vista_previa' => true,
                'moneda_id' => $monedaId,
                'total' => count($filas),
                'nuevos' => max(0, $nuevos),
                'ya_existentes' => count($existentes),
                'recuperados' => $recuperados,
                'sin_cambiar' => count($filas) - count($existentes) - count($cambian),
                'cambian' => $cambian,
                'descartadas' => $descartadas,
                'desde' => $filas[0]['fecha'],
                'hasta' => $filas[count($filas) - 1]['fecha'],
            ]);
        }

        $guardados = $this->escribir($filas, $monedaId, $request->input('fuente'));

        $this->limpiarCache();

        return response()->json([
            'ok' => true,
            'guardados' => $guardados,
            'nuevos' => max(0, $nuevos),
            'cambiados' => count($cambian),
            'recuperados' => $recuperados,
            'descartadas' => $descartadas,
        ]);
    }

    /**
     * Escribe las filas leidas, actualizando las que ya habia.
     *
     * Van todas en una transaccion. Sin ella, un archivo de treinta dias
     * que falla en el dia 18 deja los diecisiete primeros guardados y el
     * usuario no tiene forma de saber cuales son: la pantalla dice que
     * importo treinta y en verdad hay diecisiete. Y como el indice unico
     * esta puesto, el reintento del archivo entero fallaria en el dia 1.
     */
    private function escribir(array $filas, int $monedaId, ?string $fuente): int
    {
        return DB::transaction(function () use ($filas, $monedaId, $fuente) {
            $guardados = 0;

            foreach ($filas as $fila) {
                /*
                 * La clave del dia y la moneda. Se pasa como array y no como
                 * un where() suelto porque crearORestaurar() la necesita
                 * completa para buscar tambien entre las filas borradas.
                 */
                $clave = [
                    TiposCambio::FECHA => $fila['fecha'],
                    TiposCambio::MONEDA_ID => $monedaId,
                ];

                /*
                 * Si el dia ya esta, cambia el valor. Si estaba borrado, se
                 * revive y con el valor del archivo.
                 *
                 * La fuente NO se cambia nunca, y por eso va en
                 * $soloAlCrear. Si el usuario corrigio un dia a mano y apunto
                 * de donde lo saco —"correccion del 15 de septiembre"—,
                 * dejar el nombre del banco encima haria que dentro de un
                 * mes nadie supiera de donde salio ese numero, y el archivo no
                 * puede saber eso. Se cambia el numero y se deja la nota de
                 * quien lo escribio.
                 */
                TiposCambio::crearORestaurar($clave, [
                    TiposCambio::FECHA => $fila['fecha'],
                    TiposCambio::MONEDA_ID => $monedaId,
                    TiposCambio::VALOR => $fila['valor'],
                ], [
                    TiposCambio::FUENTE => $fuente,
                ]);

                $guardados++;
            }

            return $guardados;
        });
    }

    /**
     * Un Excel de ejemplo, para que el usuario vea el formato sin tener que
     * acordarse ni inventarlo.
     *
     * Se genera en memoria en vez de tener un archivo suelto en el proyecto.
     * Tres razones, y las tres son de mantenimiento:
     *
     *  - Un csv con separador de punto y coma, que es como se abre en
     *    espanol, PhpSpreadsheet no lo lee bien: el punto y coma lo toma
     *    como separador de columnas y cada fila le llega partida en tres
     *    trozos. Habria que decirle que separador usar, y entonces el
     *    ejemplo solo serviria si el usuario lo guardaba en csv.
     *
     *  - Un archivo suelto en el repositorio se queda sin actualizar en
     *    cuanto el formato cambia, y nadie se acuerda de el. Si el ejemplo
     *    lo dibuja el mismo codigo que lee el archivo, no pueden separarse.
     *
     *  - Va con los dias de verdad del mes actual, asi que el usuario ve
     *    cuantos dias tiene que tener el archivo antes de armarlo.
     */
    public function plantilla()
    {
        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Tipo de cambio');

        $hoja->fromArray(['Fecha', 'Valor'], null, 'A1');

        $hoy = today();
        $mes = (int) $hoy->format('n');
        $anio = (int) $hoy->format('Y');
        $diasDelMes = (int) $hoy->daysInMonth;

        // Valores inventados, pero con la forma de los de verdad: seis
        // decimales, que es como los publica el banco.
        for ($dia = 1; $dia <= $diasDelMes; $dia++) {
            $fecha = sprintf('%02d/%02d/%04d', $dia, $mes, $anio);

            $hoja->fromArray([$fecha, number_format(36.5824 + ($dia * 0.0137), 4, '.', '')], null, 'A' . ($dia + 1));
        }

        $hoja->getStyle('A1:B1')->getFont()->setBold(true);

        $anchos = ['A', 'B'];

        foreach ($anchos as $columna) {
            $hoja->getColumnDimension($columna)->setAutoSize(true);
        }

        /*
         * El archivo se escribe en un temporal del disco y se devuelve con
         * response()->download(), no con response() y un cuerpo en memoria.
         *
         * PhpSpreadsheet escribe a un archivo, no a una cadena: save() pide
         * una ruta. El truco de abrir un bucle de salida de PHP para que
         * "guarde" en memoria no funciona con esta libreria, porque save()
         * no respeta la salida de PHP sino que abre el archivo que se le
         * pase, y al pasarle una cadena vacia de ruta el error que sale es
         * "no se pudo abrir el archivo para escribir", que no dice nada de
         * que el problema es la ruta.
         */
        $ruta = tempnam(sys_get_temp_dir(), 'plantilla-tipo-cambio') . '.xlsx';

        (new Xlsx($libro))->save($ruta);

        $libro->disconnectWorksheets();

        /*
         * download() y no response()->download() con el archivo pasado a
         * mano.
         *
         * La diferencia es que download() manda el archivo por el gestor de
         * respuestas de Symfony, que lo envia en trozos y lo borra al
         * terminar. Si se lee el archivo con file_get_contents() y se devuelve
         * el contenido, el temporal se borra antes de que la respuesta llegue
         * al navegador y lo que se descarga son cero bytes: la respuesta dice
         * 200 y el archivo que sale no existe, que es el peor de los dos
         * mundos porque nada en la pagina avisa de nada.
         */
        return response()->download($ruta, 'tipo-de-cambio-ejemplo.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ==================================================================
    // Lo de siempre
    // ==================================================================

    /**
     * Lo que necesita el modal para abrirse.
     *
     * La fecha se manda ya formateada para que el campo la pinte tal cual,
     * y el valor con todos sus decimales: si se mandara redondeado, al
     * guardar sin tocar el campo se guardaria el redondeado, que es como
     * un tipo de cambio se va corrido un centesimo cada vez que se corrige
     * un dia que no hacia falta corregir.
     */
    private function datosParaModal(TiposCambio $tipoCambio): array
    {
        return [
            'id' => $tipoCambio->id,
            'fecha' => $tipoCambio->fecha->format('Y-m-d'),
            'moneda_id' => (int) $tipoCambio->moneda_id,
            'valor' => number_format((float) $tipoCambio->valor, 4, '.', ''),
            'fuente' => $tipoCambio->fuente,
            'observaciones' => $tipoCambio->observaciones,
        ];
    }

    private function validar(Request $request, ?TiposCambio $tipoCambio = null): array
    {
        return $request->validate([
            TiposCambio::FECHA => ['required', 'date'],
            TiposCambio::MONEDA_ID => [
                'required',
                'integer',
                Rule::exists('monedas', 'id'),
            ],
            TiposCambio::VALOR => ['required', 'numeric', 'gt:0'],
            TiposCambio::FUENTE => ['nullable', 'string', 'max:100'],
            TiposCambio::OBSERVACIONES => ['nullable', 'string', 'max:255'],
        ], [
            TiposCambio::FECHA . '.required' => 'Elija el día.',
            TiposCambio::FECHA . '.date' => 'El día no es una fecha válida.',
            TiposCambio::MONEDA_ID . '.required' => 'Elija la moneda.',
            TiposCambio::MONEDA_ID . '.exists' => 'Esa moneda no está en el catálogo.',
            TiposCambio::VALOR . '.required' => 'Indique el valor del tipo de cambio.',
            TiposCambio::VALOR . '.numeric' => 'El valor tiene que ser un número. Con coma o con punto, pero solo uno de los dos como decimal.',
            /*
             * Cero no es un tipo de cambio: es la forma de decir que no se
             * sabe. Aceptarlo dejaria una compra con equivalente en cordoba de
             * cero, que es peor que no tener la compra valorada, porque el
             * numero sale y no avisa de nada.
             */
            TiposCambio::VALOR . '.gt' => 'El tipo de cambio tiene que ser mayor que cero.',
        ]);
    }

    /**
     * No hace falta limpiar ninguna cache.
     *
     * Se dejo escrito a proposito, porque es el sitio donde se pondria si
     * algum dia el tipo de cambio se cachea: aqui, al lado de los sitios que
     * lo cambian.
     *
     * Hoy no hay nada que limpiar porque la serie se lee de la base en cada
     * conversion, sin guarda: se escribe, se borra, se importa y al instante
     * siguiente una compra nueva ya ve el valor. Si algun dia se cachea para
     * no pegarle a la base en cada linea de cada compra, los tres sitios que
     * escriben —este controlador y la importacion— tienen que acordarlo, y
     * que este metodo este vacio es la pista de que todavia no hace falta.
     */
    private function limpiarCache(): void
    {
        // Nada que limpiar. Ver el comentario de arriba.
    }

    /**
     * Como se escriben las migas de una pantalla, para quien anada otra.
     *
     * Las claves son "label" y "url", no "nombre" y "ruta". El componente de
     * las migas no sabe el idioma que le hable cada uno: mira si el elemento
     * trae "url" y, si la trae, pinta un enlace; si no, lo marca como la
     * pagina en la que se esta. Con otras claves el enlace no sale y sale un
     * error de "label no definida" que tumba la pantalla entera.
     *
     * El bloque de en medio no lleva url a proposito, y es lo mismo en todas
     * las pantallas: es el nombre del menu al que la pantalla pertenece, y no
     * hay una pagina que reuna lo de ese menu —en Configuracion, por ejemplo,
     * son tres listas sueltas y no hay una pagina de configuracion—. Un
     * enlace a un sitio que no existe seria peor que no ponerlo.
     */
}
