<?php

namespace App\Http\Controllers\Configuracion;

use App\DataTables\MonedasDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\Moneda;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * El catalogo de monedas.
 *
 * Esta pantalla no existia, y sin ella el catalogo era una trampa: el cordoba
 * y el dolar estaban puestos a mano en la base y no habia forma de anadir una
 * tercera. Todo lo demas de la aplicacion ya sabia leer de esta tabla —el
 * tipo de cambio, las compras, los pagos, los costos, el inventario— pero
 * anadir una moneda era cosa de entrar en la base de datos a mano.
 *
 * Alta, edicion y consulta van en modal sobre la lista, y no en pantallas
 * separadas, porque son cinco campos y la mayoria son de una palabra. Una
 * pantalla entera para escribir "EUR" y "Euros" es mas ruido que ayuda: la
 * lista cabe entera en la vista de una vez y es lo que se mira.
 *
 * Tres reglas, y las tres estan en el metodo que las aplica porque se
 * comprueban en el servidor y no en el formulario:
 *
 *  1. Solo puede haber una moneda base. El taller contabiliza en una moneda y
 *     todo lo demas se convierte a esa; con dos marcadas, el valor con el que
 *     se convertiria dependeria del orden de las filas.
 *
 *  2. La moneda base no se puede desactivar ni borrar. Es la que recibe todas
 *     las conversiones: sin ella, el almacen no sabe con que valor tasar el
 *     material que entra.
 *
 *  3. Una moneda que se esta usando no se borra: se desactiva. Desactivarla
 *     la saca de los desplegables y la deja de ofrecer para lo nuevo, sin
 *     tocar lo que ya se registro con ella. Borrarla dejaria documentos
 *     apuntando a una moneda que ya no esta en ninguna parte.
 */
class MonedaController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        $this->authorizeModule('configuracion.monedas', ['index', 'show']);
    }

    public function index(MonedasDataTable $dataTable)
    {
        return $dataTable->render(
            'configuracion.monedas.index',
            [
                'title' => 'Monedas',
                'breadcrumbs' => [
                    ['label' => 'Inicio', 'url' => route('home')],
                    ['label' => 'Configuración'],
                    ['label' => 'Monedas'],
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
         * La fila se revive si habia una borrada con ese codigo, y no se
         * comprueba con la regla unique de Laravel a proposito.
         *
         * La regla unique mira la tabla entera, borrados logicos incluidos, y
         * por eso su aviso seria "ese codigo ya esta en uso" para un codigo
         * que no se ve en ninguna parte y que el usuario podria poner sin
         * problema. Con la busqueda aqui se distingue: si la que hay esta
         * viva es un choque de verdad, y si esta borrada se revive.
         *
         * Y revive con seguridad porque solo se puede borrar una moneda que
         * no se usa: una moneda borrada y con documentos no se puede llegar a
         * tener, asi que la que se revive esta vacia y no hay a quien volver
         * a colgarle nada.
         */
        [$existe, $estaBorrada] = Moneda::existeClave([
            Moneda::CODIGO => $datos[Moneda::CODIGO],
        ]);

        if ($existe && ! $estaBorrada) {
            throw ValidationException::withMessages([
                Moneda::CODIGO => 'Ese código ya lo usa otra moneda.',
            ]);
        }

        $moneda = Moneda::crearORestaurar([
            Moneda::CODIGO => $datos[Moneda::CODIGO],
        ], $datos);

        $this->dejarUnaSolaBase($moneda);

        Alert::success($estaBorrada
            ? 'Moneda recuperada. Estaba dada de baja y se ha vuelto a activar.'
            : 'Moneda guardada');

        return response()->json(['ok' => true, 'id' => $moneda->id]);
    }

    /**
     * La ficha, para el modal de ver.
     *
     * Es el mismo caso que el tipo de cambio, y va en la misma pantalla: una
     * ficha de moneda es la fila con mas sitio en blanco. Lo que si se anade
     * es la lista de donde se usa, que es la pregunta que se hace al mirar
     * una moneda y que la fila no puede contestar: si de verdad la puedo
     * tocar o solo desactivarla.
     */
    public function show(Moneda $moneda)
    {
        return response()->json($this->datosParaModal($moneda) + [
            'usos' => $moneda->usos(),
            'frase_usos' => $moneda->fraseDeUsos(),
        ]);
    }

    public function edit(Moneda $moneda)
    {
        return response()->json($this->datosParaModal($moneda));
    }

    public function update(Request $request, Moneda $moneda)
    {
        $datos = $this->validar($request, $moneda);

        [$existe, $estaBorrada] = Moneda::existeClave([
            Moneda::CODIGO => $datos[Moneda::CODIGO],
        ], $moneda->id);

        if ($existe && ! $estaBorrada) {
            throw ValidationException::withMessages([
                Moneda::CODIGO => 'Ese código ya lo usa otra moneda.',
            ]);
        }

        /*
         * Quitarle la marca de base a la moneda base no se puede, y no
         * porque sea un capricho del formulario.
         *
         * Si se queda sin moneda base, el almacen deja de tener con que
         * convertir a cordoba el material que entra, y las compras en dolares
         * salen sin equivalente. Es peor que tener dos bases, porque con dos
         * bases el sistema avisa almirar el dato y esto no avisa de nada: el
         * material entra con un valor y nadie se entero hasta que un cuadre
         * no cuadra.
         *
         * Asi que para cambiarla hay que poner otra primero, que es lo que
         * dice el aviso.
         */
        if ($moneda->es_moneda_base && ! $datos[Moneda::ES_MONEDA_BASE]) {
            throw ValidationException::withMessages([
                Moneda::ES_MONEDA_BASE => 'Esta es la moneda base del taller. Para cambiarla, '
                    . 'marque primero otra como base: si se queda el sistema sin moneda base, '
                    . 'el material que entra al almacén y las compras en dólares saldrán sin equivalente.',
            ]);
        }

        /*
         * La moneda base tampoco se puede desactivar, y por lo mismo.
         */
        if ($moneda->es_moneda_base && ! $datos[Moneda::ESTADO]) {
            throw ValidationException::withMessages([
                Moneda::ESTADO => 'La moneda base no se puede desactivar: es la que recibe '
                    . 'todas las conversiones. Para dejar de usarla, marque otra como base primero.',
            ]);
        }

        $moneda->update($datos);

        $this->dejarUnaSolaBase($moneda->fresh());

        Alert::success('Moneda guardada');

        return response()->json(['ok' => true, 'id' => $moneda->id]);
    }

    public function destroy(Moneda $moneda)
    {
        /*
         * Las dos razones para no poder borrar estan en el modelo y no aqui,
         * porque la misma pregunta la hace el boton de eliminar, el boton de
         * desactivar y la ficha, y en cuanto estuviera en el controlador
         * habia que repetirla en los tres sitios.
         */
        if ($moneda->es_moneda_base) {
            throw ValidationException::withMessages([
                Moneda::ID => 'No se puede borrar la moneda base del taller. '
                    . 'Marque otra como base primero, y esta ya se podrá desactivar.',
            ]);
        }

        if (! $moneda->sePuedeBorrar()) {
            throw ValidationException::withMessages([
                Moneda::ID => 'No se puede borrar: esta moneda está en uso, en '
                    . $moneda->fraseDeUsos() . '. Se puede desactivar, que la saca de los '
                    . 'desplegables sin tocar lo que ya se registró con ella.',
            ]);
        }

        $moneda->delete();

        Alert::success('Moneda eliminada');

        return response()->json(['ok' => true]);
    }

    // ==================================================================
    // Lo de siempre
    // ==================================================================

    /**
     * Deja una sola moneda marcada como base.
     *
     * Se quita la marca a las demas en lugar de avisar, y no por evitar el
     * aviso: es que no se puede dejar en manos del usuario. Si el formulario
     * permitiera marcar una segunda base sin quitar la primera, el estado
     * dependeria de que el usuario se acuerde de desmarcar la otra, y con dos
     * bases el valor con el que se convertiría lo decide el orden de las
     * filas de la tabla. No es un error que se vea: es un numero que sale
     * distinto segun desde donde se mire.
     *
     * Va en su propia transaccion porque son dos escrituras y no queremos
     * dejarla a medias: la moneda nueva guardada como base y las demas sin
     * desmarcar.
     */
    private function dejarUnaSolaBase(Moneda $moneda): void
    {
        if (! $moneda->es_moneda_base) {
            return;
        }

        DB::transaction(function () use ($moneda) {
            Moneda::where(Moneda::ES_MONEDA_BASE, true)
                ->where(Moneda::ID, '!=', $moneda->id)
                ->update([Moneda::ES_MONEDA_BASE => false]);
        });
    }

    private function datosParaModal(Moneda $moneda): array
    {
        return [
            'id' => $moneda->id,
            'codigo' => $moneda->codigo,
            'nombre' => $moneda->nombre,
            'simbolo' => $moneda->simbolo,
            'es_moneda_base' => (bool) $moneda->es_moneda_base,
            'estado' => (bool) $moneda->estado,
        ];
    }

    private function validar(Request $request, ?Moneda $moneda = null): array
    {
        $datos = $request->validate([
            Moneda::CODIGO => ['required', 'string', 'size:3', 'alpha:ascii'],
            Moneda::NOMBRE => ['required', 'string', 'max:50'],
            Moneda::SIMBOLO => ['nullable', 'string', 'max:10'],
            Moneda::ES_MONEDA_BASE => ['sometimes', 'boolean'],
            Moneda::ESTADO => ['sometimes', 'boolean'],
        ], [
            Moneda::CODIGO . '.required' => 'Elija el código de la moneda.',
            Moneda::CODIGO . '.size' => 'El código son tres letras, como NIO, USD o EUR.',
            Moneda::CODIGO . '.alpha' => 'El código son tres letras, sin números ni signos.',
            Moneda::NOMBRE . '.required' => 'Escriba el nombre de la moneda.',
            Moneda::NOMBRE . '.max' => 'El nombre no puede pasar de 50 caracteres.',
            Moneda::SIMBOLO . '.max' => 'El símbolo no puede pasar de 10 caracteres.',
        ]);

        /*
         * El codigo se pasa a mayusculas antes de guardarlo, y no al
         * validarlo.
         *
         * La columna es de tres caracteres y el indice unico no distingue
         * mayusculas de minusculas en MySQL, asi que "eur" y "EUR" son la
         * misma moneda para la base: el segundo alta pasaria por buena y
         * reventaria con un error de indice unico. Pasandolo a mayusculas al
         * guardar, "eur" y "EUR" se guardan igual y el indice unico hace su
         * trabajo, que es decir que avise.
         */
        $datos[Moneda::CODIGO] = strtoupper(trim($datos[Moneda::CODIGO]));

        /*
         * Las casillas sin marcar no llegan en la peticion. Se ponen a false
         * para que desmarcar "es la base" o "activa" se guarde de verdad, y
         * no que el modelo se quede con lo que tenia antes.
         */
        $datos[Moneda::ES_MONEDA_BASE] = $request->boolean(Moneda::ES_MONEDA_BASE);
        $datos[Moneda::ESTADO] = $request->boolean(Moneda::ESTADO);

        return $datos;
    }
}
