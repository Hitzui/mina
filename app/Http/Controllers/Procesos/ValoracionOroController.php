<?php

namespace App\Http\Controllers\Procesos;

use App\DataTables\ValoracionesOroDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\Moneda;
use App\Models\OrdenesTrabajo;
use App\Models\PreciosOro;
use App\Models\Recuperaciones;
use App\Models\ValoracionesOro;
use App\Services\ValoracionOroService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * Cuanto vale lo que salio del taller.
 *
 * Es la ultima pieza del camino del oro. Sin ella, las otras dos son una
 * serie de gramos y una serie de precios que no se juntan nunca: el taller
 * tendria que hacer la cuenta a mano en una calculadora y acordarse de mirar
 * el precio del dia. Con esta pantalla, lo que sale del taller vale algo
 * concreto, con la cifra de donde sale al lado.
 *
 * Va en Produccion y al lado de las recuperaciones, no dentro de la ficha de la
 * orden, por lo mismo que aquellas: el valor es un hecho economico y se mira
 * junto a otras, no orden por orden. Y una valoracion cuelga de una
 * recuperacion, no de una orden, que es lo que hace que la suma de lo
 * valorado de una partida cuadre con lo que salio de ella.
 *
 * CUATRO REGLAS, Y LAS CUATRO ESTAN AQUI Y NO EN EL FORMULARIO, PORQUE SE
 * PUEDEN SALTAR DESDE CUALQUIER LADO.
 *
 *  1. El valor NO SE ESCRIBE. No hay un campo de valor en el formulario, y no
 *     es una casualidad: lo calcula el servicio y no hay forma de teclear una
 *     cifra que contradiga la cuenta. Una pantalla de valoracion con el valor
 *     a mano es la forma mas rapida de tener dos cifras distintas para los
 *     mismos gramos —la que dice el documento y la que sale de la cuenta— sin
 *     que ninguna avise. En su lugar, el modal enseña el calculo entero antes
 *     de guardar: los gramos valorados, el precio del gramo, de que dia salio
 *     y el tipo de cambio si lo hubo. Lo que se ve antes de pulsar es
 *     exactamente lo que se guarda despues.
 *
 *  2. Se valoran los gramos FINOS cuando la recuperacion tiene pureza, y los
 *     que salieron cuando no la tiene. Es la regla que evita pagar de mas por
 *     material que no era oro: una partida de 2 gramos al 75 % tiene 1,5
 *     gramos de oro fino, y valorar los 2 seria un 33 % de mas. Y lo de "si no
 *     hay pureza, los gramos" no es un afterthought: en el taller hay
 *     recuperaciones en las que nadie midio la pureza, y en esas el unico
 *     numero sobre el que multiplicar es el que salio. Deliberadamente NO es la
 *     misma regla que la del precio del oro, donde el cero significa "no se
 *     sabe": aqui el cero es un hecho, y ahi era una falta.
 *
 *  3. Una orden CANCELADA no admite valoraciones nuevas; una FINALIZADA si.
 *     Y esto se sale a proposito del trait OrdenCerrada, que en las otras seis
 *     pantallas bloquea las finalizadas tambien. Aqui no puede ser igual: el
 *     taller se cierra y el oro se valora despues, al facturar. Si se bloqueara
 *     en las finalizadas, cerrar la orden dejaria sus gramos sin poder
 *     facturar nunca, que es un callejon sin salida: el unico camino seria
 *     reabrir la orden para meter un numero. Una orden cancelada es otra cosa
 *     del todo —no se hizo el trabajo, y lo que no se hizo no se valora— y por
 *     eso si se bloquea. Lo que ya esta escrito se corrige igual en los dos
 *     casos: un numero mal tecleado se arregla aunque la orden este cerrada.
 *
 *  4. Una recuperacion no puede tener dos valoraciones, y no solo por pantalla:
 *     hay un indice unico en la base. Si el banco corrige el precio de un dia,
 *     lo que se corrige es la valoracion, no se anade otra. El rastro de lo que
 *     valia cada dia esta en la serie de precios, que es una fila por dia y no
 *     se toca. Ademas el indice va solo sobre la recuperacion, sin la fecha: si
 *     llevara la fecha, dejaria pasar dos valoraciones de la misma partida
 *     hechas en dias distintos, que es justo lo que se quiere impedir.
 */
class ValoracionOroController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        $this->authorizeModule('valoraciones_oro', ['index', 'show']);
    }

    public function index(ValoracionesOroDataTable $dataTable)
    {
        return $dataTable->render(
            'procesos.valoraciones_oro.index',
            [
                /*
                 * Las recuperaciones van todas al desplegable, con buscador, y
                 * no se piden por ajax. Son pocas hoy, y una peticion por abrir
                 * el modal para devolver unas pocas filas es ruido. Cuando haya
                 * cientos, el desplegable se leera entero mientras que quepa y
                 * se buscara cuando ya no quepa, que es justo lo que hace el
                 * buscador: por eso se pone ya, aunque hoy sobre.
                 *
                 * Y van las canceladas tambien, marcadas como tales. Podria
                 * filtrarlas y no se hace a proposito: si una partida sale de
                 * una orden cancelada, el usuario tiene que poder verla y ver
                 * por que no deja valorar, y no un desplegable al que le falta
                 * una fila sin explicación. Es la diferencia entre un aviso y
                 * un fallo.
                 */
                'recuperaciones' => Recuperaciones::with('orden_trabajo')
                    ->orderByDesc('fecha')
                    ->orderByDesc('id')
                    ->get(),

                'monedas' => Moneda::orderBy('codigo')->get(),
                'precioMonedaPorDefecto' => $this->precioMonedaPorDefecto(),
                'title' => 'Valoraciones de oro',
                'breadcrumbs' => [
                    ['label' => 'Inicio', 'url' => route('home')],
                    ['label' => 'Producción'],
                    ['label' => 'Valoraciones de oro'],
                ],
            ]
        );
    }

    public function create()
    {
        return response()->json(['ok' => true]);
    }

    public function store(Request $request, ValoracionOroService $servicio)
    {
        $datos = $this->validar($request);

        $recuperacion = Recuperaciones::with('orden_trabajo')
            ->findOrFail($datos[ValoracionesOro::RECUPERACION_ID]);

        $this->comprobarValorable($recuperacion, null);

        $estabaBorrada = $this->comprobarNoValorada(
            $datos[ValoracionesOro::RECUPERACION_ID],
            null
        );

        $calculo = $servicio->calcular(
            $recuperacion,
            $datos[ValoracionesOro::FECHA],
            (int) $datos[ValoracionesOro::PRECIO_MONEDA],
            (int) $datos[ValoracionesOro::MONEDA_ID]
        );

        if (! $calculo['ok']) {
            throw ValidationException::withMessages([
                ValoracionesOro::FECHA => $calculo['motivo'],
            ]);
        }

        $valoracion = ValoracionesOro::crearORestaurar(
            $this->claveDe($datos),
            [
                ValoracionesOro::RECUPERACION_ID => $datos[ValoracionesOro::RECUPERACION_ID],
                ValoracionesOro::PRECIO_ORO_ID => $calculo['precio_oro_id'],
                ValoracionesOro::VALOR => $calculo['valor'],
                ValoracionesOro::MONEDA_ID => $datos[ValoracionesOro::MONEDA_ID],
                ValoracionesOro::FECHA => $datos[ValoracionesOro::FECHA],
                ValoracionesOro::OBSERVACIONES => $datos[ValoracionesOro::OBSERVACIONES] ?? null,
            ]
        );

        /*
         * Cuando la fila estaba dada de baja y se ha revivido, el aviso lo
         * dice. Un "guardada" a secas haria pensar que es una valoracion nueva,
         * y la diferencia importa: la id es la misma de antes, y si habria algo
         * que depende de ella —un informe, un cobro— no se ha creado otra fila.
         */
        Alert::success(
            ($estabaBorrada ? 'Valoración recuperada, estaba dada de baja. ' : '')
            . number_format((float) $calculo['valor'], 2)
        );

        return response()->json(['ok' => true, 'id' => $valoracion->id]);
    }

    public function show(ValoracionesOro $valoracion)
    {
        return response()->json($this->datosParaModal($valoracion));
    }

    public function edit(ValoracionesOro $valoracion)
    {
        return response()->json($this->datosParaModal($valoracion));
    }

    public function update(Request $request, ValoracionesOro $valoracion, ValoracionOroService $servicio)
    {
        $datos = $this->validar($request, $valoracion);

        $recuperacion = Recuperaciones::with('orden_trabajo')
            ->findOrFail($datos[ValoracionesOro::RECUPERACION_ID]);

        $this->comprobarValorable($recuperacion, $valoracion);

        $this->comprobarNoValorada($datos[ValoracionesOro::RECUPERACION_ID], $valoracion->id);

        $calculo = $servicio->calcular(
            $recuperacion,
            $datos[ValoracionesOro::FECHA],
            (int) $datos[ValoracionesOro::PRECIO_MONEDA],
            (int) $datos[ValoracionesOro::MONEDA_ID]
        );

        if (! $calculo['ok']) {
            throw ValidationException::withMessages([
                ValoracionesOro::FECHA => $calculo['motivo'],
            ]);
        }

        $valores = [
            ValoracionesOro::RECUPERACION_ID => $datos[ValoracionesOro::RECUPERACION_ID],
            ValoracionesOro::PRECIO_ORO_ID => $calculo['precio_oro_id'],
            ValoracionesOro::VALOR => $calculo['valor'],
            ValoracionesOro::MONEDA_ID => $datos[ValoracionesOro::MONEDA_ID],
            ValoracionesOro::FECHA => $datos[ValoracionesOro::FECHA],
            ValoracionesOro::OBSERVACIONES => $datos[ValoracionesOro::OBSERVACIONES] ?? null,
        ];

        /*
         * Si la clave NO ha cambiado, se actualiza la fila y ya esta.
         *
         * Y si ha cambiado —la valoracion se pasa a otra recuperacion, que es
         * el caso de una partida valorada por error en la fila de al lado— no
         * se puede hacer un update normal: la recuperacion_id es indice unico,
         * de modo que la fila destino puede tener una valoracion dada de baja
         * cuya id sigue ocupando el indice, y el update reventaria con un
         * "Duplicate entry" de MySQL.
         *
         * Por eso se borra la vieja y se escribe la nueva con crearORestaurar,
         * que revive la que hubiera en lugar de crear otra. Y se borra antes y
         * no despues por el mismo motivo: si se escribiera antes, la propia fila
         * que se esta moviendo estorbaria.
         *
         * El aviso sale igual en los dos casos. Un "movida" aqui seria ruido:
         * lo que le importa a quien esta corrigiendo es el numero, y ya esta
         * en el mensaje.
         */
        $cambiaRecuperacion = (int) $valoracion->recuperacion_id !== (int) $datos[ValoracionesOro::RECUPERACION_ID];

        if ($cambiaRecuperacion) {
            $valoracion->delete();

            $valoracion = ValoracionesOro::crearORestaurar(
                $this->claveDe($datos),
                $valores
            );
        } else {
            $valoracion->update($valores);
        }

        Alert::success('Valoración guardada: ' . number_format((float) $calculo['valor'], 2));

        return response()->json(['ok' => true, 'id' => $valoracion->id]);
    }

    public function destroy(ValoracionesOro $valoracion)
    {
        /*
         * Se borra sin preguntar por el estado de la orden, igual que las
         * recuperaciones. Una valoracion mal puesta se borra aunque la orden
         * este cerrada: si no, un error de tecleo en el mes en que se cerro
         * seria imposible de arreglar, que es justo cuando mas urge.
         */
        $valoracion->delete();

        Alert::success('Valoración eliminada');

        return response()->json(['ok' => true]);
    }

    /**
     * El calculo de una valoracion, para enseñarlo en el modal antes de
     * guardar.
     *
     * Va como peticion aparte y no como calculo en el navegador a proposito. La
     * cuenta usa el precio vigente de la serie, que es una tabla por dia, y
     * tambien el tipo de cambio del dia: si se calculara en el javascript,
     * habria que mandar al navegador la serie entera para que multiplicara, o
     * estimar, y entre las dos cosas se acabaria viendo un numero en el modal
     * que no es el que se guardaria. Preguntando al servidor, lo que se ve
     * antes de guardar es lo mismo —el mismo metodo, los mismos datos— que lo
     * que se guarda despues.
     *
     * Y devuelve tambien el motivo cuando no se puede, que es lo que permite
     * que el modal diga "no hay ningun precio anterior al 12/09" en vez de
     * quedarse mudo con la cuenta a medias.
     */
    public function calcular(ValoracionOroService $servicio)
    {
        $datos = $this->validarCalculo();

        /*
         * La recuperacion se busca con findOrFail y no con find.
         *
         * La regla de exists la pone validarCalculo, y entonces find no puede
         * devolver null. Pero si devolviera, el servicio reventaria al leer
         * $recuperacion->pureza, y el error le llegaria al modal como un 500
         * en vez de como un "esa recuperacion ya no esta". Con findOrFail el
         * mismo caso sale como un 404 con su texto, que es lo que el modal sabe
         * enseñar.
         */
        $recuperacion = Recuperaciones::findOrFail($datos[ValoracionesOro::RECUPERACION_ID]);

        $calculo = $servicio->calcular(
            $recuperacion,
            $datos[ValoracionesOro::FECHA],
            (int) $datos[ValoracionesOro::PRECIO_MONEDA],
            (int) $datos[ValoracionesOro::MONEDA_ID]
        );

        return response()->json($calculo);
    }

    // ==================================================================
    // Lo de siempre
    // ==================================================================

    /**
     * La clave de la valoracion, que es la recuperacion.
     *
     * Va en el controlador y no en el modelo, como en el precio del oro y el
     * tipo de cambio, y por el mismo motivo: es el unico sitio donde se
     * escribe la combinacion, y si estuviera en dos habria que acordarse de
     * cambiar los dos. El indice unico de la base es la red de seguridad: si
     * esto se descolgara, el alta reventaria con el error de MySQL en vez de
     * guardar dos filas.
     */
    private function claveDe(array $datos): array
    {
        return [
            ValoracionesOro::RECUPERACION_ID => $datos[ValoracionesOro::RECUPERACION_ID],
        ];
    }

    /**
     * No deja guardar si la orden de la recuperacion esta cancelada.
     *
     * El texto no sale del trait OrdenCerrada como en las otras pantallas, y
     * en parte es el mismo y en parte no. Lo que se rehusa a hacer aqui es
     * dejar sin valoracion una orden que se cancelo: no se hizo el trabajo, y
     * lo que no se hizo no se valora. Y hay un motivo para no reutilizar el
     * metodo tal cual, aunque el texto se parece: el de las otras pantallas
     * sugiere volver a poner la orden en Pendiente, y aqui eso no seria la
     * salida. Cancelar una orden no fue un error de tecleo que se quiera
     * deshacer escribiendo un numero de gramos, es una decision del taller.
     *
     * Y en la correccion no se mira: si la valoracion ya estaba escrita, se
     * puede corregir, porque un numero mal puesto se arregla aunque la orden
     * este cancelada. Es la misma regla de las otras pantallas, y la unica
     * diferencia es que alli la orden cerrada es siempre de las dos clases.
     */
    private function comprobarValorable(Recuperaciones $recuperacion, ?ValoracionesOro $valoracion): void
    {
        $orden = $recuperacion->orden_trabajo;

        if (! $orden || (int) $orden->estado !== OrdenesTrabajo::ESTADO_CANCELADA) {
            return;
        }

        if ($valoracion !== null) {
            return;
        }

        throw ValidationException::withMessages([
            ValoracionesOro::RECUPERACION_ID => sprintf(
                'No se puede valorar la recuperación de la orden %s: la orden está %s. '
                . 'Una orden cancelada no produjo trabajo, así que lo que salió de ella no '
                . 'se valora. Si lo que hace falta es seguir trabajando en esta orden, '
                . 'vuelve a ponerla en Pendiente desde su ficha.',
                $orden->codigo,
                mb_strtolower($orden->estadoTexto())
            ),
        ]);
    }

    /**
     * No deja guardar si esa recuperacion ya esta valorada.
     *
     * Se comprueba antes de escribir, y no se deja al indice unico, para que
     * el mensaje sea del taller y no de MySQL. El indice unico esta igual, y
     * sigue siendo la red de seguridad, pero la explicacion de por que —"esta
     * partida ya tiene una valoracion del 15/09; si el precio estaba mal, corrige
     * esa, no añadas otra"— la puede dar solo el servidor mirando la fila que
     * hay.
     *
     * Y SI ESTA BORRADA NO SE AVISA: se deja pasar, y la revive el trait
     * ClaveUnica al escribir. Es el choque entre el indice unico y el borrado
     * logico, que es lo mismo que rompia al tipo de cambio y al precio del
     * oro: la fila borrada sigue contando para el indice pero Eloquent no la
     * ve. Si aqui se avisara, borrar una valoracion y volver a meterla —que es
     * exactamente lo que se hace cuando el banco corrige un precio y se quiere
     * empezar de cero— seria imposible desde la pantalla, y el unico camino
     * seria un "Duplicate entry" de MySQL.
     *
     * Por eso el metodo devuelve si estaba borrada, para que quien llama lo diga
     * en el aviso de exito: "valoracion recuperada, estaba dada de baja" en vez
     * de un "guardada" a secas, que haria pensar que es una nueva.
     */
    private function comprobarNoValorada(int $recuperacionId, ?int $ignorarId): bool
    {
        [$existe, $estaBorrada] = ValoracionesOro::existeClave(
            [ValoracionesOro::RECUPERACION_ID => $recuperacionId],
            $ignorarId
        );

        if (! $existe || $estaBorrada) {
            return $estaBorrada;
        }

        $otra = ValoracionesOro::withTrashed()
            ->where(ValoracionesOro::RECUPERACION_ID, $recuperacionId)
            ->when($ignorarId !== null, fn($q) => $q->where(ValoracionesOro::ID, '!=', $ignorarId))
            ->first();

        throw ValidationException::withMessages([
            ValoracionesOro::RECUPERACION_ID => sprintf(
                'Esta recuperación ya está valorada el %s. Una partida tiene un solo valor: '
                . 'si el precio del oro estaba mal, corrige esa valoración, que la cuenta se vuelve '
                . 'a hacer con el precio de ahora. No añadas una segunda.',
                $otra?->fecha->format('d/m/Y') ?? '—'
            ),
        ]);
    }

    /**
     * En que moneda se buscan los precios por defecto.
     *
     * Es la del ultimo precio cargado, y no la moneda base, porque el precio
     * del oro es lo que decide si la cuenta se puede hacer: si los precios que
     * hay en el taller estan en dolares y se busca en cordoba, no habria nada
     * que encontrar y el modal saldria con un error en el primer clic.
     *
     * Si no hay ningun precio cargado todavia, cae a la moneda base, que es la
     * unica con la que el taller podria tenerlos si todavia no ha cargado
     * ninguno. Y si tampoco hubiera moneda base —que solo pasaria con un
     * catalogo vacio— devuelve cero, y el selector se queda sin nada marcado
     * para que lo elija a mano en vez de fallar con una excepcion.
     */
    private function precioMonedaPorDefecto(): int
    {
        $delUltimoPrecio = (int) PreciosOro::orderByDesc('fecha')
            ->orderByDesc('id')
            ->value('moneda_id');

        if ($delUltimoPrecio > 0) {
            return $delUltimoPrecio;
        }

        return (int) (Moneda::where('es_moneda_base', true)->value('id') ?? 0);
    }

    private function datosParaModal(ValoracionesOro $valoracion): array
    {
        $valoracion->loadMissing([
            'moneda',
            'precio_oro',
            'recuperacion.orden_trabajo',
        ]);

        $gramos = $valoracion->gramosValorados();

        $servicio = new ValoracionOroService();

        $calculo = $servicio->calcular(
            $valoracion->recuperacion,
            $valoracion->fecha->toDateString(),
            (int) ($valoracion->precio_oro?->moneda_id ?? $this->precioMonedaPorDefecto()),
            (int) $valoracion->moneda_id
        );

        return [
            'id' => $valoracion->id,
            'recuperacion_id' => (int) $valoracion->recuperacion_id,
            'recuperacion_texto' => $valoracion->recuperacion
                ? sprintf(
                    '%s · %s · %s g%s',
                    $valoracion->recuperacion->orden_trabajo?->codigo ?? 'sin orden',
                    $valoracion->recuperacion->fecha->format('d/m/Y'),
                    number_format((float) $valoracion->recuperacion->gramos, 4),
                    $valoracion->recuperacion->purezaEnPorcentaje() === null
                        ? ' (sin medir la pureza)'
                        : ' · ' . number_format($valoracion->recuperacion->purezaEnPorcentaje(), 2) . ' %'
                )
                : '—',
            'fecha' => $valoracion->fecha->format('Y-m-d'),
            'precio_moneda' => (int) ($valoracion->precio_oro?->moneda_id ?? $this->precioMonedaPorDefecto()),
            'moneda_id' => (int) $valoracion->moneda_id,
            'valor' => (float) $valoracion->valor,
            'observaciones' => $valoracion->observaciones,

            /*
             * El calculo de la ficha es el de ahora, no el que se guardo.
             *
             * La fila conserva el precio que uso, y por eso se puede volver a
             * el. Pero el que se enseña en el modal es el que sale hoy de la
             * serie, y van a coincidir salvo que el banco haya corregido un
             * dia. Cuando no coincidan, el modal lo dice: es la forma de que
             * "el valor guardado" y "el valor que sale hoy" no se confundan
             * en la cabeza de quien esta leyendo.
             */
            'gramos_valorados' => $gramos['valorados'],
            'usa_pureza' => $gramos['usa_pureza'],
            'calculo' => $calculo,
        ];
    }

    private function validar(Request $request, ?ValoracionesOro $valoracion = null): array
    {
        $datos = $request->validate([
            /*
             * La recuperacion tiene que existir y no estar dada de baja. Con
             * "exists" a secas, una recuperacion borrada seguira contando y se
             * podria valorar algo que ya no esta: el desplegable no la enseia,
             * asi que solo se podria hacer a mano, pero "a mano" incluye un
             * formulario antiguo que se dejo abierto.
             */
            ValoracionesOro::RECUPERACION_ID => [
                'required',
                'integer',
                Rule::exists('recuperaciones', 'id')->whereNull('deleted_at'),
            ],

            ValoracionesOro::FECHA => ['required', 'date'],

            /*
             * Estas dos monedas no son la misma cosa y por eso son dos campos.
             * La del precio es donde se busca el precio del gramo, y la del
             * valor es en la que se guarda el resultado. Lo normal es que
             * coincidan —el taller carga el precio del banco en la moneda en
             * la que cotiza, y valora en la misma— y en ese caso no hace
             * falta tipo de cambio para nada. Y cuando no coinciden, hace
             * falta, y es la unica forma de que quede dicho cual se uso.
             *
             * No se anade PRECIO_ORO_ID a la validacion porque el precio no lo
             * elige el usuario: lo elige la serie, buscando el ultimo
             * conocido que no sea cero. Escribirlo en el formulario abriria la
             * puerta a valorar con un precio de un dia cualquiera.
             */
            ValoracionesOro::PRECIO_MONEDA => ['required', 'integer', Rule::exists('monedas', 'id')->whereNull('deleted_at')],

            ValoracionesOro::MONEDA_ID => [
                'required',
                'integer',
                Rule::exists('monedas', 'id')->whereNull('deleted_at'),
            ],

            ValoracionesOro::OBSERVACIONES => ['nullable', 'string', 'max:1000'],
        ], [
            ValoracionesOro::RECUPERACION_ID . '.required' => 'Elija la recuperación que se va a valorar.',
            ValoracionesOro::RECUPERACION_ID . '.exists' => 'Esa recuperación no está en el catálogo. Puede que se haya eliminado.',
            ValoracionesOro::FECHA . '.required' => 'Elija el día de la valoración.',
            ValoracionesOro::FECHA . '.date' => 'El día no es una fecha válida.',
            ValoracionesOro::PRECIO_MONEDA . '.required' => 'Elija en qué moneda está el precio del oro.',
            ValoracionesOro::PRECIO_MONEDA . '.exists' => 'Esa moneda no está en el catálogo.',
            ValoracionesOro::MONEDA_ID . '.required' => 'Elija en qué moneda se guarda el valor.',
            ValoracionesOro::MONEDA_ID . '.exists' => 'Esa moneda no está en el catálogo.',
        ]);

        /*
         * Las observaciones a null cuando no viene nada.
         *
         * Sin esto, un campo vacio llega como cadena vacia y se guardaria
         * como "" en vez de no guardar nada. Y el "que no vine en nada" no es
         * lo mismo que "vino vacio": un campo que el usuario ni ha tocado no
         * llega en la peticion, de modo que la clave ni siquiera existe en lo
         * que devuelve validate(), y pedirla a pelo sale con un "Undefined
         * array key" que tumba la pantalla entera.
         */
        $observaciones = $datos[ValoracionesOro::OBSERVACIONES] ?? null;

        $datos[ValoracionesOro::OBSERVACIONES] = ($observaciones === '' || $observaciones === null)
            ? null
            : $observaciones;

        return $datos;
    }

    /**
     * Lo que necesita la peticion del calculo.
     *
     * Las mismas cuatro cosas que el formulario —recuperacion, dia y las dos
     * monedas— y con las MISMAS reglas, copiadas de las de arriba en vez de
     * llamarlas a un metodo comun.
     *
     * Y con las MISMAS reglas, copiadas de las de arriba en vez de llamarlas
     * a un metodo comun.
     *
     * Se copian a proposito. Si las dos peticiones leyeran las reglas de un
     * sitio comun, ese sitio tendria que ser alcanzable desde validate(), que
     * es estatico, y la unica forma de llegar ahi es un array de reglas en una
     * constante: y el dia que ese array exista habra dos listas de reglas y
     * alguien tendra que acordarse de las dos. Duplicadas, el fallo se ve al
     * leer: si el formulario admite algo que el calculo no, la cuenta no sale
     * nunca con un dato que el formulario si deja pasar.
     *
     * Lo que cambia es la consecuencia, no las reglas. Aqui "la recuperacion no
     * existe" es un 404 con su texto, porque es una peticion de lectura y no un
     * formulario que alguien esta rellenando: el modal solo llega aqui con algo
     * que ha elegido de un desplegable.
     */
    private function validarCalculo(): array
    {
        return request()->validate([
            ValoracionesOro::RECUPERACION_ID => [
                'required',
                'integer',
                Rule::exists('recuperaciones', 'id')->whereNull('deleted_at'),
            ],
            ValoracionesOro::FECHA => ['required', 'date'],
            ValoracionesOro::PRECIO_MONEDA => ['required', 'integer'],
            ValoracionesOro::MONEDA_ID => ['required', 'integer'],
        ], [
            ValoracionesOro::RECUPERACION_ID . '.required' => 'Elija la recuperación que se va a valorar.',
            ValoracionesOro::FECHA . '.required' => 'Elija el día de la valoración.',
            ValoracionesOro::FECHA . '.date' => 'El día no es una fecha válida.',
            ValoracionesOro::PRECIO_MONEDA . '.required' => 'Falta la moneda del precio.',
            ValoracionesOro::MONEDA_ID . '.required' => 'Falta la moneda del valor.',
        ]);
    }
}
