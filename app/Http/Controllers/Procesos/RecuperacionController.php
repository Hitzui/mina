<?php

namespace App\Http\Controllers\Procesos;

use App\DataTables\RecuperacionesDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Concerns\OrdenCerrada;
use App\Http\Controllers\Controller;
use App\Models\OrdenesTrabajo;
use App\Models\Recuperaciones;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * El oro que sale del taller.
 *
 * Esta tabla es la que hace que sirva la del precio del oro. Recuperaciones
 * registra cuantos gramos salieron de una orden y con que pureza;
 * valoraciones_oro multiplica esos gramos por el precio de un gramo de ese
 * dia. Sin esta pantalla, la del precio del oro es una serie que no alimenta
 * a nadie, y el valor de lo recuperado habria que calcularlo a mano.
 *
 * Va en Produccion y no dentro de la ficha de la orden, y la razon es que no
 * es un dato del taller sino un hecho económico: los gramos de una orden se
 * pueden mirar todos juntos, que es como se mira una producción, y una
 * pantalla dentro de la ficha obligaria a entrar en cada orden para ver lo que
 * salio de ella. El vinculo con la orden esta, y la orden se elige con su
 * buscador. Ademas, una orden puede tener varias recuperaciones —una por
 * partida, una por cada vez que se lavó el mineral— y en una pantalla global
 * se ven todas, que es justo lo que hace falta para comprobar que los gramos
 * de una orden cuadran con los de sus procesos.
 *
 * Tres reglas, y las tres estan escritas aqui y no en el formulario, porque
 * se pueden saltar desde cualquier lado:
 *
 *  1. Una orden finalizada o cancelada no admite recuperaciones nuevas. Es la
 *     misma regla de las otras seis pantallas que cuelgan de una orden, y sale
 *     del trait OrdenCerrada; lo unico que aporta esta pantalla es el nombre
 *     de lo que se intentaba registrar, para que el aviso pueda decir "no se
 *     puede registrar una recuperación" en vez de "no se puede hacer esto".
 *
 *  2. La pureza es una fracción: 0,915 es el 91,5 %. Es lo que dice la
 *     documentacion y es lo que evita guardar un 91 creyendo que es el 91 %,
 *     que seria el 9100 %. La regla parece rara hasta que alguien teclea 91, y
 *     entonces se ve: el campo va con un texto al lado que lo dice, y el
 *     servidor no admite un numero mayor que uno.
 *
 *  3. Y los gramos en cero SI se admiten, a diferencia del precio del oro. La
 *     razon es que aqui el cero es un hecho y no una falta: una partida de
 *     mineral de la que no salio oro es un dato real, y registrarla es
 *     precisamente lo que deja constancia. En el precio del oro el cero
 *     significaba "no se sabe", y ahi si era una falta. No se puede aplicar el
 *     mismo criterio a las dos cosas solo porque las dos se llamen "el valor".
 */
class RecuperacionController extends Controller
{
    use AuthorizesModule;
    use OrdenCerrada;

    public function __construct()
    {
        $this->authorizeModule('recuperaciones', ['index', 'show']);
    }

    public function index(RecuperacionesDataTable $dataTable)
    {
        return $dataTable->render(
            'procesos.recuperaciones.index',
            [
                /*
                 * Las ordenes van todas al desplegable, y no se piden por ajax
                 * como el resto de los selectores. Son tres hoy, y con tres
                 * llevar un buscador que las busca en el servidor es ruido: una
                 * peticion por abrir el modal para devolver tres filas.
                 *
                 * El buscador de select2 esta puesto igual, porque el taller
                 * llega a tener cientos de ordenes y cuando llegue ya no
                 * hara falta tocar el codigo: el desplegable se leera
                 * entero mientras que quepa, y se buscara cuando ya no quepa.
                 *
                 * Se traen todas, abiertas y cerradas: las cerradas hacen
                 * falta para corregir una recuperacion que ya esta escrita
                 * en una orden cerrada, que se puede, y para que el usuario
                 * vea por que no puede anadir mas.
                 */
                'ordenes' => OrdenesTrabajo::with('cliente')
                    ->orderByDesc('fecha')
                    ->orderByDesc('id')
                    ->get(),
                'title' => 'Recuperaciones de oro',
                'breadcrumbs' => [
                    ['label' => 'Inicio', 'url' => route('home')],
                    ['label' => 'Producción'],
                    ['label' => 'Recuperaciones de oro'],
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

        $orden = OrdenesTrabajo::findOrFail($datos[Recuperaciones::ORDEN_TRABAJO_ID]);

        $this->comprobarOrdenAbierta($orden, 'una recuperación de oro');

        $recuperacion = Recuperaciones::create($datos);

        Alert::success('Recuperación guardada');

        return response()->json(['ok' => true, 'id' => $recuperacion->id]);
    }

    public function show(Recuperaciones $recuperacion)
    {
        return response()->json($this->datosParaModal($recuperacion->load('orden_trabajo')));
    }

    public function edit(Recuperaciones $recuperacion)
    {
        return response()->json($this->datosParaModal($recuperacion->load('orden_trabajo')));
    }

    public function update(Request $request, Recuperaciones $recuperacion)
    {
        $datos = $this->validar($request, $recuperacion);

        $orden = OrdenesTrabajo::findOrFail($datos[Recuperaciones::ORDEN_TRABAJO_ID]);

        /*
         * Una orden cerrada bloquea el alta, pero no la correccion.
         *
         * Es la misma regla de las otras pantallas que cuelgan de una orden, y
         * ahi esta explicada: lo que ya esta escrito se corrige, porque un
         * numero de gramos mal tecleado se corrige aunque la orden se haya
         * cerrado. Lo que no se puede es anadir una recuperacion nueva a una
         * orden que ya no admite datos.
         */
        if ((int) $orden->id !== (int) $recuperacion->orden_trabajo_id) {
            $this->comprobarOrdenAbierta($orden, 'una recuperación de oro');
        }

        $recuperacion->update($datos);

        Alert::success('Recuperación guardada');

        return response()->json(['ok' => true, 'id' => $recuperacion->id]);
    }

    public function destroy(Recuperaciones $recuperacion)
    {
        /*
         * Se borra sin preguntar por el estado de la orden, y a proposito. Un
         * registro mal puesto se borra aunque la orden este cerrada: si no,
         * un error de tecleo en el mes en que se cerro la orden seria
         * imposible de arreglar, que es justo cuando mas urge.
         */
        $recuperacion->delete();

        Alert::success('Recuperación eliminada');

        return response()->json(['ok' => true]);
    }

    // ==================================================================
    // Lo de siempre
    // ==================================================================

    /**
     * No deja guardar si la orden esta cerrada.
     *
     * El trait OrdenCerrada devuelve una redireccion a la ficha de la orden,
     * que es lo que necesitan las seis pantallas que son una ficha entera. En
     * esta no: el formulario va en un modal sobre la lista, y una redireccion
     * ahi llega al javascript como un 200 con html donde se espera un json.
     * El boton queda en silencio y el modal sigue abierto, que es peor que no
     * avisar.
     *
     * Asi que aqui se usa el texto del trait —que es el mismo y ya esta
     * escrito una vez para todas las pantallas— y se lanza como error de
     * validacion, que es lo que el modal sabe pintar.
     */
    private function comprobarOrdenAbierta(OrdenesTrabajo $orden, string $que): void
    {
        if ($this->ordenAbierta($orden)) {
            return;
        }

        throw ValidationException::withMessages([
            Recuperaciones::ORDEN_TRABAJO_ID => $this->mensajeDeOrdenCerrada($orden, $que),
        ]);
    }

    private function datosParaModal(Recuperaciones $recuperacion): array
    {
        return [
            'id' => $recuperacion->id,
            'orden_trabajo_id' => (int) $recuperacion->orden_trabajo_id,
            'orden_codigo' => $recuperacion->orden_trabajo?->codigo,
            'fecha' => $recuperacion->fecha->format('Y-m-d'),
            'gramos' => rtrim(rtrim(number_format((float) $recuperacion->gramos, 4, '.', ''), '0'), '.') ?: '0',
            'pureza' => $recuperacion->pureza,
            'observaciones' => $recuperacion->observaciones,
        ];
    }

    private function validar(Request $request, ?Recuperaciones $recuperacion = null): array
    {
        $datos = $request->validate([
            /*
             * La orden se busca entre las que no estan borradas. Con la regla
             * de "exists" a secas, una orden dada de baja seguira contando y
             * se podria colgar una recuperacion de algo que ya no esta: el
             * desplegable no la enseña, asi que solo se podria hacer a mano,
             * pero "a mano" incluye un formulario antiguo que se dejo abierto.
             */
            Recuperaciones::ORDEN_TRABAJO_ID => [
                'required',
                'integer',
                Rule::exists('ordenes_trabajo', 'id')->whereNull('deleted_at'),
            ],

            Recuperaciones::FECHA => ['required', 'date'],

            /*
             * Los gramos en cero se admiten, y no por descuido. Aqui el cero
             * es un hecho —una partida de la que no salio oro— y no una falta
             * de dato, asi que se registra igual. Lo que no tiene sentido es
             * un numero negativo, que no es una partida mala: es la columna al
             * reves.
             */
            Recuperaciones::GRAMOS => ['required', 'numeric', 'min:0', 'max:999999.9999'],

            /*
             * La pureza va de 0 a 1 porque es una fracción: 0,915 es el
             * 91,5 %. El maximo en uno es la regla que hace que la fracción no
             * se pueda pasar por alto, y hay un texto al lado del campo que lo
             * dice, porque un 91 tecleado aqui no da error de validacion si no
             * se acota: se guardaria como el 9100 % y nadie se enteraria hasta
             * que una valoracion saliese absurda.
             */
            Recuperaciones::PUREZA => ['nullable', 'numeric', 'min:0', 'max:1'],

            Recuperaciones::OBSERVACIONES => ['nullable', 'string', 'max:1000'],
        ], [
            Recuperaciones::ORDEN_TRABAJO_ID . '.required' => 'Elija la orden de trabajo.',
            Recuperaciones::ORDEN_TRABAJO_ID . '.exists' => 'Esa orden de trabajo no está en el catálogo.',
            Recuperaciones::FECHA . '.required' => 'Elija el día de la recuperación.',
            Recuperaciones::FECHA . '.date' => 'El día no es una fecha válida.',
            Recuperaciones::GRAMOS . '.required' => 'Indique los gramos recuperados.',
            Recuperaciones::GRAMOS . '.numeric' => 'Los gramos tienen que ser un número. Con coma o con punto, pero solo uno de los dos como decimal.',
            Recuperaciones::GRAMOS . '.min' => 'Los gramos no pueden ser negativos. Si de esa partida no salió oro, deje el cero: eso es un dato, no un error.',
            Recuperaciones::GRAMOS . '.max' => 'La columna admite hasta 999.999,9999 gramos. Si el numero es ese, hay un error de tecleo.',
            /*
             * El mensaje de la pureza es el mas importante de esta pantalla,
             * y va escrito entero. Es la regla que mas cara sale si no se
             * entiende: un 91 guardado como 9100 % no se ve hasta que una
             * valoracion sale absurda, y para entonces ya hay documentos
             * escritos.
             */
            Recuperaciones::PUREZA . '.numeric' => 'La pureza tiene que ser un número. Se escribe como fracción: 0,915 para el 91,5 %.',
            Recuperaciones::PUREZA . '.max' => 'La pureza va de 0 a 1, porque es una fracción. Para el 91,5 % se escribe 0,915. Si quiere decir el 91 %, se escribe 0,91.',
            Recuperaciones::PUREZA . '.min' => 'La pureza no puede ser negativa.',
        ]);

        /*
         * La pureza a null cuando no viene en nada.
         *
         * Sin esto, un campo vacio llega como cadena vacia y se guardaria
         * como "" en una columna decimal, que es un cero con pinta de
         * numero. Y un cero de pureza es el 0 %, que es una afirmacion: dice
         * que el oro era puro cero. Lo que se quiere decir cuando no se
         * midio la pureza es que no se sabe, y eso es la falta de dato.
         *
         * Y por eso la lista enseña un guion en vez de un 0 %: que no se sepa
         * y que valga cero son cosas distintas, y en un numero pequeño la
         * diferencia no se ve.
         *
         * El "que no vine en nada" no es lo mismo que "vino vacio", y por eso
         * se miran las dos cosas. Un campo vacio llega como cadena vacia, que
         * en una columna decimal se guardaria como un cero con pinta de
         * numero. Y un campo que el usuario ni ha tocado no llega en la
         * peticion, de modo que la clave ni siquiera existe en lo que
         * devuelve validate(): pedirla a pelo sale con un "Undefined array
         * key" y la pantalla entera se cae con un error de codigo, que es lo
         * que le pasaria a cualquiera que guardase una recuperacion sin
         * mirar el campo de la pureza.
         */
        $pureza = $datos[Recuperaciones::PUREZA] ?? null;

        if ($pureza === '' || $pureza === null) {
            $datos[Recuperaciones::PUREZA] = null;
        }

        return $datos;
    }
}
