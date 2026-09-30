<?php

namespace App\Http\Controllers\Procesos;

use App\DataTables\IngresosDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Concerns\OrdenCerrada;
use App\Http\Controllers\Controller;
use App\Models\Ingreso;
use App\Models\Moneda;
use App\Models\OrdenesTrabajo;
use App\Models\TiposCambio;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * Los ingresos de una orden: el dinero que entra.
 *
 * Van en la ficha de la orden y no en una pantalla global, y al reves que las
 * recuperaciones y las valoraciones. Aqui la razon es la contraria: un ingreso no
 * significa nada fuera de su orden. Los recuperadores de oro se pueden mirar todos
 * juntos porque el oro del taller es el mismo para todas las ordenes; lo que
 * entro por una orden es de esa orden.
 *
 * CUATRO REGLAS, TODAS EN EL SERVIDOR PORQUE SE PUEDEN SALTAR DESDE CUALQUIER
 * LADO.
 *
 *  1. EL TOTAL NO SE ESCRIBE. Sale de multiplicar la cantidad por el precio
 *     unitario, y lo multiplica el servidor. Si se recibiera ya calculado, un
 *     valor equivocado en el formulario se guardaria como si fuera verdad y no
 *     habria forma de saber que no cuadra con la multiplicacion. Y esto vale
 *     mas aqui que en los costos, porque el total de los ingresos es dinero que
 *     el taller le esta cobrando al cliente.
 *
 *  2. EL EQUIVALENTE EN CORDOBA SE GUARDA Y NO SE RECALCULA AL LEER. Sale del
 *     tipo de cambio del dia del ingreso, y se queda escrito. Si manana se
 *     corrige el tipo de cambio de esa fecha, los ingresos de una orden ya
 *     cerrada no deben moverse: un documento que cambia de valor solo porque se
 *     corrigio una tabla de hace tres meses no es un documento, es una consulta.
 *
 *  3. UNA ORDEN CERRADA NO ADMITE INGRESOS NUEVOS, y sale del trait
 *     OrdenCerrada, igual que en las otras pantallas que cuelgan de la orden.
 *     Lo que ya esta escrito se corrige igual: un total mal tecleado hay que
 *     poder arreglarlo aunque la orden se cerrara en su dia.
 *
 *     Y aqui entra la liquidacion. Un ingreso puede apuntar a una liquidacion —
 *     el cierre economico de la orden— y esa liquidacion no se puede tocar si la
 *     orden esta cerrada. Por eso un ingreso que apunta a una liquidacion es de
 *     una orden que se liquido, y liquidar es el ultimo paso: si la liquidacion
 *     existe, la orden esta cerrada. O sea que la regla de "cerrada no admite
 *     ingresos nuevos" ya viene implícita en la de la liquidacion, y no hace
 *     falta una tercera.
 *
 *  4. SI NO HAY TIPO DE CAMBIO PARA ESA FECHA, EL EQUIVALENTE SE QUEDA VACIO Y
 *     SE DICE POR QUE. El total en la moneda del ingreso se guarda igualmente,
 *     porque ese si se sabe: lo que no se encuentra es la conversion. Un ingreso
 *     guardado sin el equivalente y sin avisar parece un ingreso de cero.
 *
 * Y NO HAY UN TOTAL DE LA ORDEN QUE REVE LO QUE SALE. Lo que entra y lo que sale
 * son numeros de sistemas distintos —lo que el taller cobra y lo que el taller
 * gasta— y restarlos daria un beneficio que en este taller no significa nada: una
 * orden puede no haber dado dinero, y eso no es una perdida sino como funciona.
 * Cada uno va en su tabla con su total, que es lo que hay que mirar.
 */
class IngresoController extends Controller
{
    use AuthorizesModule;
    use OrdenCerrada;

    public function __construct()
    {
        $this->authorizeModule('ingresos');
    }

    /**
     * La tabla de ingresos de la orden.
     *
     * Devuelve solo el html de la tabla, no la pagina: la ficha de la orden ya
     * esta montada y los ingresos son una seccion mas de ella, como los costos
     * generales. Con render() se traeria la pagina entera y habria que meter la
     * ficha dentro de la tabla, que es al reves de como esta hecho.
     */
    public function index(OrdenesTrabajo $ordenTrabajo, IngresosDataTable $dataTable)
    {
        $dataTable->setOrdenTrabajoId($ordenTrabajo->id);

        return $dataTable->ajax();
    }

    /**
     * El alta va en el modal de la ficha, asi que esta ruta solo existe para que
     * la url del recurso no de error.
     */
    public function create(OrdenesTrabajo $ordenTrabajo)
    {
        return redirect()->route('procesos.ordenes_trabajo.show', $ordenTrabajo);
    }

    public function store(Request $request, OrdenesTrabajo $ordenTrabajo)
    {
        if ($bloqueo = $this->bloquearOrdenCerrada($ordenTrabajo, 'un ingreso')) {
            return $bloqueo;
        }

        $datos = $this->validar($request);

        $ingreso = $this->armar($datos, $ordenTrabajo);

        $ingreso->save();

        Alert::toast($this->mensaje($ingreso, $datos))->success()->flash();

        return redirect()->route('procesos.ordenes_trabajo.show', $ordenTrabajo);
    }

    /**
     * La ficha de un ingreso, en JSON.
     *
     * Devuelve tambien el tipo de cambio que se aplico, y no solo los totales,
     * porque un total en cordoba sin el cambio con el que salio no se puede
     * comprobar: con el, se multiplica y se sabe si la cifra era esa. Y es la
     * pregunta que uno se hace al ver un ingreso cuyo dia ya paso.
     */
    public function show(OrdenesTrabajo $ordenTrabajo, Ingreso $ingreso)
    {
        $this->comprobarPertenencia($ordenTrabajo, $ingreso);

        $ingreso->load(['tipo_ingreso', 'moneda']);

        return response()->json([
            'id' => $ingreso->id,
            'tipo' => $ingreso->tipo_ingreso?->nombre ?? '—',
            'fecha' => $ingreso->fecha?->format('d/m/Y'),
            'descripcion' => $ingreso->descripcion,
            'cantidad' => $ingreso->cantidad,
            'unidad_medida' => $ingreso->unidad_medida,
            'precio_unitario' => $ingreso->precio_unitario,
            'total' => $ingreso->total,
            'moneda' => $ingreso->moneda?->codigo ?? '—',
            'total_nio' => $ingreso->total_nio,
            'tipo_cambio' => $this->tipoCambioAplicado($ingreso),
            'observaciones' => $ingreso->observaciones ?? '',
        ]);
    }

    public function edit(OrdenesTrabajo $ordenTrabajo, Ingreso $ingreso)
    {
        $this->comprobarPertenencia($ordenTrabajo, $ingreso);

        $ingreso->load(['tipo_ingreso', 'moneda']);

        return response()->json([
            'id' => $ingreso->id,
            'tipo_ingreso_id' => $ingreso->tipo_ingreso_id,
            'fecha' => $ingreso->fecha?->format('Y-m-d'),
            'descripcion' => $ingreso->descripcion,
            'cantidad' => $ingreso->cantidad,
            'unidad_medida' => $ingreso->unidad_medida,
            'precio_unitario' => $ingreso->precio_unitario,
            'moneda_id' => $ingreso->moneda_id,
            'total_nio' => $ingreso->total_nio,
            'observaciones' => $ingreso->observaciones ?? '',
        ]);
    }

    public function update(Request $request, OrdenesTrabajo $ordenTrabajo, Ingreso $ingreso)
    {
        $this->comprobarPertenencia($ordenTrabajo, $ingreso);

        $datos = $this->validar($request, $ingreso->id);

        $ingreso = $this->armar($datos, $ordenTrabajo, $ingreso);

        $ingreso->save();

        Alert::toast($this->mensaje($ingreso, $datos))->success()->flash();

        return redirect()->route('procesos.ordenes_trabajo.show', $ordenTrabajo);
    }

    /**
     * Se borra sin preguntar por el estado de la orden, como los costos.
     *
     * Un ingreso mal puesto se borra aunque la orden este cerrada: si no, un
     * error de tecleo en el mes en que se cerro la orden seria imposible de
     * arreglar, que es justo cuando mas urge.
     */
    public function destroy(OrdenesTrabajo $ordenTrabajo, Ingreso $ingreso)
    {
        $this->comprobarPertenencia($ordenTrabajo, $ingreso);

        $ingreso->delete();

        Alert::toast('Ingreso eliminado correctamente.')->success()->flash();

        return redirect()->route('procesos.ordenes_trabajo.show', $ordenTrabajo);
    }

    // ==================================================================
    // Lo de siempre
    // ==================================================================

    /**
     * El ingreso tiene que ser de la orden de la url.
     *
     * Sin esto se podria editar o borrar el ingreso de otra orden escribiendo
     * su id en la url, y el ingreso desapareceria de una orden sin aparecer en
     * ninguna. Es la misma comprobacion que hacen los costos con el proceso, y
     * va en un 404 y no en un aviso porque no es un error de quien esta en la
     * pantalla: es una url que no existe.
     */
    private function comprobarPertenencia(OrdenesTrabajo $ordenTrabajo, Ingreso $ingreso): void
    {
        abort_unless((int) $ingreso->orden_trabajo_id === $ordenTrabajo->id, 404);
    }

    /**
     * Monta el ingreso con los datos del formulario y calcula los importes.
     *
     * El total y su equivalente en cordoba no vienen del formulario: se
     * calculan aqui. Y la liquidacion no se pide en el formulario todavia, porque
     * no hay ninguna registrada y un desplegable vacio en un formulario es peor
     * que no tenerlo: se pondria ahi con la intencion de rellenarlo cuando exista
     * alguna, y en cuanto existiera habria que tocar el formulario entero.
     */
    private function armar(array $datos, OrdenesTrabajo $ordenTrabajo, ?Ingreso $ingreso = null): Ingreso
    {
        $ingreso = $ingreso ?? new Ingreso();

        $ingreso->orden_trabajo_id = $ordenTrabajo->id;
        $ingreso->liquidacion_id = $ingreso->liquidacion_id;
        $ingreso->tipo_ingreso_id = $datos[Ingreso::TIPO_INGRESO_ID];
        $ingreso->fecha = $datos[Ingreso::FECHA];
        $ingreso->descripcion = $datos[Ingreso::DESCRIPCION] ?? null;
        $ingreso->cantidad = $datos[Ingreso::CANTIDAD];
        $ingreso->unidad_medida = $datos[Ingreso::UNIDAD_MEDIDA] ?? null;
        $ingreso->precio_unitario = $datos[Ingreso::PRECIO_UNITARIO];
        $ingreso->moneda_id = $datos[Ingreso::MONEDA_ID];
        $ingreso->estado = $ingreso->estado ?? 1;
        $ingreso->observaciones = $datos[Ingreso::OBSERVACIONES] ?? null;

        $ingreso->calcularTotales($this->tipoDeCambio($datos));

        return $ingreso;
    }

    /**
     * Cuántos córdobas vale una unidad de la moneda del ingreso ese día.
     *
     * Y con la MONEDA BASE ES UNO, no ausencia de tipo de cambio. Es la
     * diferencia entre esta pantalla y el resto del sistema, y esta escrita
     * porque si no el total en cordoba de una orden facturada en cordoba saldria
     * vacio y la suma de abajo no cuadraria con las filas que se ven.
     *
     * @return float|null  Uno para la base, el valor del cambio, o null si no
     *                     se encontro ninguno.
     */
    private function tipoDeCambio(array $datos): ?float
    {
        $monedaId = (int) $datos[Ingreso::MONEDA_ID];

        $base = $this->monedaBase();

        if ($base !== null && $monedaId === (int) $base->id) {
            return 1.0;
        }

        return TiposCambio::vigentePara($monedaId, $datos[Ingreso::FECHA]);
    }

    private function monedaBase(): ?Moneda
    {
        return Moneda::where('es_moneda_base', true)->first();
    }

    /**
     * Que tipo de cambio se aplico a un ingreso ya guardado.
     *
     * Se saca de los dos numeros que guardo la fila, y no se vuelve a buscar el
     * tipo de cambio: el de ahora puede ser distinto del de entonces, y aqui lo
     * que se quiere es el que se uso. Con la moneda base es 1 y el calculo sale
     * exacto; con las demas, la division devuelve el cambio con una precision
     * de cuatro decimales, que es lo que tiene la columna.
     */
    private function tipoCambioAplicado(Ingreso $ingreso): ?float
    {
        if ($ingreso->total_nio === null || (float) $ingreso->total <= 0) {
            return null;
        }

        return round((float) $ingreso->total_nio / (float) $ingreso->total, 4);
    }

    /**
     * Reglas de un ingreso.
     *
     * Los importes calculados —el total y sus equivalentes en cordoba— no se
     * validan porque no se reciben: los pone el servidor. Si estuvieran en las
     * reglas, se podrian mandar y el servidor los ignoraria, que es peor que
     * que no los haya: parece que controlan algo y no controlan nada.
     *
     * @param  int|null  $ignorarId  El ingreso que se esta editando, para que no
     *                                se compare consigo mismo.
     * @return array<string, array<int, mixed>>
     */
    private function validar(Request $request, ?int $ignorarId = null): array
    {
        return $request->validate(
            [
                /*
                 * El tipo sale del catalogo con el mismo filtro que usan los
                 * demas: solo los que estan activos. Un tipo desactivado no se
                 * ofrece, y la regla sale del modelo y no de una lista de
                 * nombres escrita aqui, que es lo que se desincroniza.
                 */
                Ingreso::TIPO_INGRESO_ID => [
                    'required',
                    'integer',
                    Rule::exists('tipos_ingreso', 'id')
                        ->whereNull('deleted_at')
                        ->where('estado', true),
                ],

                Ingreso::FECHA => [
                    'required',
                    'date',
                ],

                /*
                 * La descripcion es opcional a diferencia de la del costo. En
                 * un costo la descripcion es lo que dice de que era el gasto,
                 * porque el nombre de la categoria es "energia" o "agua prima" y
                 * no alcanza. En un ingreso el tipo ya lo dice —"servicio de
                 * procesamiento"— y la descripcion es el detalle de que partida
                 * es, y a veces no hay ninguna.
                 */
                Ingreso::DESCRIPCION => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                /*
                 * La cantidad no puede ser cero. Un ingreso de cero es un
                 * documento que dice que el taller no le cobro nada al cliente,
                 * y si se admite sin querer es un error que no se ve hasta
                 * que la cuenta no cuadra. Un precio unitario en cero si se
                 * admite, y por el mismo motivo que el del costo: puede ser una
                 * cosa que se regala o que entra por otra parte.
                 */
                Ingreso::CANTIDAD => [
                    'required',
                    'numeric',
                    'gt:0',
                ],

                Ingreso::UNIDAD_MEDIDA => [
                    'nullable',
                    'string',
                    'max:20',
                ],

                Ingreso::PRECIO_UNITARIO => [
                    'required',
                    'numeric',
                    'gte:0',
                ],

                Ingreso::MONEDA_ID => [
                    'required',
                    'integer',
                    Rule::exists('monedas', 'id')
                        ->whereNull('deleted_at')
                        ->where('estado', true),
                ],

                Ingreso::OBSERVACIONES => [
                    'nullable',
                    'string',
                ],
            ],
            [
                Ingreso::TIPO_INGRESO_ID . '.required' => 'Elija de qué tipo es el ingreso.',
                Ingreso::TIPO_INGRESO_ID . '.exists' => 'Ese tipo de ingreso no existe o está inactivo.',
                Ingreso::FECHA . '.required' => 'Indique la fecha del ingreso.',
                Ingreso::FECHA . '.date' => 'La fecha no es válida.',
                Ingreso::DESCRIPCION . '.max' => 'La descripción no puede superar los 255 caracteres.',
                Ingreso::CANTIDAD . '.required' => 'Indique la cantidad.',
                Ingreso::CANTIDAD . '.gt' => 'La cantidad debe ser mayor que cero. Si no se cobró nada, no se registra un ingreso: eso se anota en la orden.',
                Ingreso::UNIDAD_MEDIDA . '.max' => 'La unidad no puede pasar de 20 caracteres.',
                Ingreso::PRECIO_UNITARIO . '.required' => 'Indique el precio unitario.',
                Ingreso::PRECIO_UNITARIO . '.gte' => 'El precio unitario no puede ser negativo.',
                Ingreso::MONEDA_ID . '.required' => 'Seleccione la moneda del ingreso.',
            ]
        );
    }

    /**
     * El aviso de exito, que dice la cifra y si el equivalente se pudo calcular.
     *
     * Y cuando no se pudo, lo dice en el mismo aviso y no en otro aparte: es
     * una consecuencia de lo que acaba de pasar, y quien lo lee quiere saber
     * las dos cosas de una vez.
     */
    private function mensaje(Ingreso $ingreso, array $datos): string
    {
        $moneda = Moneda::find($datos[Ingreso::MONEDA_ID]);

        $mensaje = 'Ingreso registrado: ' . number_format((float) $ingreso->total, 2)
            . ' ' . ($moneda?->codigo ?? '') . '.';

        if ($ingreso->total_nio === null) {
            $mensaje .= ' No se encontró tipo de cambio de esa moneda para la '
                . 'fecha indicada; el equivalente en córdobas quedó vacío y no '
                . 'cuenta en el total de la orden.';

            return $mensaje;
        }

        return $mensaje;
    }
}
