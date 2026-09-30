<?php

namespace App\Http\Controllers\Configuracion;

use App\DataTables\TiposIngresoDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\TiposIngreso;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * Los tipos de ingreso: el catalogo de por que entro dinero en una orden.
 *
 * Son cuatro y los puso el taller a mano en la base de datos, que se puede
 * hacer pero es lo unico del sistema que se hace asi. Esta pantalla es para
 * que se puedan corregir, desactivar y dar de baja sin salir de la aplicacion.
 *
 * NO SE DECIDE AQUI QUE TIPOS HAY.
 *
 * Ni se anaden ni se completan. Los tipos que hay son los del taller —servicio
 * de procesamiento, participacion en oro, venta de oro y otro— y quien decide
 * que falta es el que lleva el taller, no un codigo que pone cuatro lineas por
 * aqui y se decide. Un maestro de tipos de ingreso es una opinion sobre como
 * funciona el negocio, y meter opinions distintas de las del taller es
 * precisamente el error que hace un software inutil.
 *
 * VA EN CONFIGURACION y no en la pantalla de ingresos, y por lo mismo que las
 * demas categorias: es una lista de consulta, y meterla en la ficha de los
 * ingresos seria una pantalla para cambiar cuatro campos.
 *
 * TRES REGLAS, Y LAS TRES ESTAN EN EL SERVIDOR PORQUE SE PUEDEN SALTAR.
 *
 *  1. Un tipo en uso no se borra: se desactiva. Si hay ingresos con ese tipo,
 *     borrarlo dejaria la FK apuntando a una fila que ya no esta. Y desactivar
 *     sirve para lo mismo —sacarlo de los desplegables— sin tocar lo que ya se
 *     registro con el. Es la misma regla que en las monedas, y por el mismo
 *     motivo: un registro mal puesto se corrige, un tipo que ya se uso se deja
 *     de usar, y ninguna de las dos cosas es motivo para romper lo que hay.
 *
 *  2. Y se cuentan como uso los ingresos dados de baja tambien, no solo los
 *     vivos. La FK no distingue: la fila borrada sigue escribiendo el numero.
 *     Si se contaran solo los vivos, el boton dejaria pulsar el borrar y
 *     reventaria con un error de MySQL en vez de con un aviso que explica.
 *
 *  3. Un tipo desactivado sale de los desplegables pero se sigue viendo en la
 *     lista, con su etiqueta de inactivo. Si desapareciera de la lista no habria
 *     forma de volverlo a activar, y desactivado es una decision que se cambia:
 *     un tipo que se dejo de usar en febrero puede volver a usarse en
 *     septiembre.
 *
 * Y el nombre NO es unico en la base, a proposito y en contra de lo que hace
 * el catalogo de monedas. Alli lo unico es el codigo ISO, que por definicion no
 * puede repetirse. Aqui el nombre es libre, y las categorias de costo y los
 * tipos de pago de empleado —los otros dos catalogos del taller— tampoco lo
 * tienen. Meterlo solo en esta tabla seria tener tres catalogos con una regla
 * y dos con otra, que es como se acaba sin saber cual es la buena.
 */
class TipoIngresoController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        $this->authorizeModule('configuracion.tipos_ingreso', ['index', 'show']);
    }

    public function index(TiposIngresoDataTable $dataTable)
    {
        return $dataTable->render(
            'configuracion.tipos_ingreso.index',
            [
                'title' => 'Tipos de ingreso',
                'breadcrumbs' => [
                    ['label' => 'Inicio', 'url' => route('home')],
                    ['label' => 'Configuración'],
                    ['label' => 'Tipos de ingreso'],
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

        $tipo = TiposIngreso::create($datos);

        Alert::success('Tipo de ingreso guardado');

        return response()->json(['ok' => true, 'id' => $tipo->id]);
    }

    public function show(TiposIngreso $tipoIngreso)
    {
        return response()->json($this->datosParaModal($tipoIngreso));
    }

    public function edit(TiposIngreso $tipoIngreso)
    {
        return response()->json($this->datosParaModal($tipoIngreso));
    }

    public function update(Request $request, TiposIngreso $tipoIngreso)
    {
        $datos = $this->validar($request);

        $tipoIngreso->update($datos);

        Alert::success('Tipo de ingreso guardado');

        return response()->json(['ok' => true, 'id' => $tipoIngreso->id]);
    }

    /**
     * Activar o desactivar, sin pasar por el modal.
     *
     * Va como boton propio en la fila y no como un campo mas del formulario, y
     * la razon es que es la accion que mas se va a repetir: desactivar un tipo
     * que ya no se usa y volverlo a activar en temporada alta son dos clics, y
     * si estuviera en el modal habria que abrirlo, cambiar el interruptor y
     * guardar, que es un modo de equivocarse: guardar el nombre con una tilde
     * quitada mientras venia a cambiar el estado.
     *
     * El boton NO se apaga cuando el tipo esta en uso, y tambien eso es
     * decision: desactivar un tipo en uso es justamente lo que se quiere hacer,
     * es el otro camino del aviso de "no se puede borrar".
     */
    public function cambiarEstado(TiposIngreso $tipoIngreso)
    {
        $tipoIngreso->update([
            TiposIngreso::ESTADO => ! $tipoIngreso->estado
        ]);

        Alert::success($tipoIngreso->estado
            ? 'Tipo de ingreso activado'
            : 'Tipo de ingreso desactivado');

        return response()->json(['ok' => true]);
    }

    public function destroy(TiposIngreso $tipoIngreso)
    {
        /*
         * La razon por la que no se puede esta en el modelo, porque la misma
         * pregunta la hacen el boton de la fila y la ficha, y en cuanto estuviera
         * en el controlador habria que repetirla en los dos sitios. Aqui solo se
         * escribe el texto, que si tiene que decir donde esta en uso para que el
         * usuario sepa que hacer en vez de probar.
         */
        if (! $tipoIngreso->sePuedeBorrar()) {
            throw ValidationException::withMessages([
                TiposIngreso::NOMBRE => 'No se puede borrar: este tipo de ingreso está en uso, en '
                    . $tipoIngreso->fraseDeUsos() . '. Se puede desactivar, que lo saca de los '
                    . 'desplegables sin tocar los ingresos que ya se registraron con él.',
            ]);
        }

        $tipoIngreso->delete();

        Alert::success('Tipo de ingreso eliminado');

        return response()->json(['ok' => true]);
    }

    // ==================================================================
    // Lo de siempre
    // ==================================================================

    private function datosParaModal(TiposIngreso $tipoIngreso): array
    {
        $usos = $tipoIngreso->usos();

        return [
            'id' => $tipoIngreso->id,
            'nombre' => $tipoIngreso->nombre,
            'descripcion' => $tipoIngreso->descripcion,
            'estado' => (bool) $tipoIngreso->estado,
            'usos' => $usos,
            'frase_de_usos' => $tipoIngreso->fraseDeUsos(),
            'se_puede_borrar' => $usos === [],
        ];
    }

    private function validar(Request $request): array
    {
        $datos = $request->validate([
            TiposIngreso::NOMBRE => ['required', 'string', 'max:100'],
            TiposIngreso::DESCRIPCION => ['nullable', 'string', 'max:255'],

            /*
             * El estado se acepta como 0 o 1 y no como texto.
             *
             * Lo manda el interruptor del formulario, que manda un valor y no un
             * texto, y la regla de "booleano" aceptaria tambien "sí" y "on" y
             * cualquier cosa que se le ocurra a quien mande el formulario a mano.
             * Con in:0,1 se sabe exactamente que es un interruptor y no otra
             * cosa.
             */
            TiposIngreso::ESTADO => ['required', 'in:0,1'],
        ], [
            TiposIngreso::NOMBRE . '.required' => 'Escriba el nombre del tipo de ingreso.',
            TiposIngreso::NOMBRE . '.max' => 'El nombre no puede pasar de 100 caracteres.',
            TiposIngreso::DESCRIPCION . '.max' => 'La descripción no puede pasar de 255 caracteres.',
            TiposIngreso::ESTADO . '.required' => 'Diga si el tipo está activo o inactivo.',
            TiposIngreso::ESTADO . '.in' => 'El estado tiene que ser activo o inactivo.',
        ]);

        /*
         * El estado a booleano de verdad.
         *
         * La columna es un TINYINT(1) y el formulario manda un 0 o un 1. Sin
         * esto se guardaria el numero tal cual, que MySQL lo acepta, y el
         * resultado seria el mismo porque el cast lo convierte al leer. Pero si
         * mañana alguien quita el cast, un 2 seria distinto de un 1 sin que nada
         * avise, y el fallo sale en el desplegable como un tipo que no aparece.
         */
        $datos[TiposIngreso::ESTADO] = (bool) $datos[TiposIngreso::ESTADO];

        /*
         * Y la descripcion a null cuando no viene nada.
         *
         * Sin esto un campo vacio llega como cadena vacia y se guarda como "" en
         * vez de no guardar nada, y en la lista sale un guion y en la ficha una
         * cadena invisible. Y el "que no vine en nada" no es lo mismo que "vino
         * vacio": un campo que el usuario ni ha tocado no llega en la peticion.
         */
        $descripcion = $datos[TiposIngreso::DESCRIPCION] ?? null;

        $datos[TiposIngreso::DESCRIPCION] = ($descripcion === '' || $descripcion === null)
            ? null
            : $descripcion;

        return $datos;
    }
}
