<?php

namespace App\Http\Controllers\Configuracion;

use App\DataTables\MetodosPagoDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\MetodosPagos;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * Los metodos de pago: como recibe el taller el dinero de un cobro.
 *
 * NO SON LOS TIPOS DE PAGO DE EMPLEADO, que son otra cosa. Aquellos son como se
 * le paga a la gente que trabaja aqui; estos son como se le paga a quien compra.
 * Las dos pantallas se parecen porque las dos son catálogos de formas de pago,
 * y estan en el mismo bloque de Configuracion, pero las filas que meten no se
 * mezclan: un metodo de pago de un cobro no es un metodo de pago de un
 * empleado, y por eso son dos tablas y no una con un campo que las separe.
 *
 * VA EN CONFIGURACION y no colgando de los cobros, y por lo mismo que las demas
 * categorias: es una lista de consulta de tres campos, y una pantalla para
 * escribirlos seria mas sitio en blanco que otra cosa. Ademas los cobros todavia
 * no tienen pantalla, asi que el metodo de pago no tiene de donde colgarse.
 *
 * TRES REGLAS, Y LAS TRES ESTAN EN EL SERVIDOR PORQUE SE PUEDEN SALTAR.
 *
 *  1. Un metodo en uso no se borra: se desactiva. Si hay cobros con ese metodo,
 *     borrarlo dejaria la FK apuntando a una fila que ya no esta. Y desactivar
 *     sirve para lo mismo —sacarlo de los desplegables— sin tocar lo que ya se
 *     registro con el. Es la misma regla que en las monedas, y por el mismo
 *     motivo: un registro mal puesto se corrige, un metodo que ya se uso se deja
 *     de usar, y ninguna de las dos cosas es motivo para romper lo que hay.
 *
 *  2. Y se cuentan como uso los cobros dados de baja tambien, no solo los vivos.
 *     La FK no distingue: la fila borrada sigue escribiendo el numero. Si se
 *     contaran solo los vivos, el boton dejaria pulsar el borrar y reventaria con
 *     un error de MySQL en vez de con un aviso que explica.
 *
 *  3. Un metodo desactivado sale de los desplegables pero se sigue viendo en la
 *     lista, con su etiqueta de inactivo. Si desapareciera de la lista no habria
 *     forma de volverlo a activar, y desactivar es una decision que se cambia: un
 *     talon con que se cobra dos veces al año vuelve a usarse.
 *
 * Y el nombre NO es unico en la base, a proposito y en contra de lo que hace el
 * catalogo de monedas. Alli lo unico es el codigo ISO, que por definicion no
 * puede repetirse. Aqui el nombre es libre, y los tipos de ingreso, las categorias
 * de costo, los tipos de pago de empleado y las cajas —los otros cuatro catalogos
 * del taller— tampoco lo tienen. A diferencia de la caja, aqui un nombre
 * repetido no estropea nada: los metodos de pago no se suman entre si, asi que un
 * duplicado sale dos veces en el desplegable y ya esta.
 */
class MetodoPagoController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        $this->authorizeModule('configuracion.metodos_pago', ['index', 'show']);
    }

    public function index(MetodosPagoDataTable $dataTable)
    {
        return $dataTable->render(
            'configuracion.metodos_pago.index',
            [
                'title' => 'Métodos de pago',
                'breadcrumbs' => [
                    ['label' => 'Inicio', 'url' => route('home')],
                    ['label' => 'Configuración'],
                    ['label' => 'Métodos de pago'],
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

        $metodo = MetodosPagos::create($datos);

        Alert::success('Método de pago guardado');

        return response()->json(['ok' => true, 'id' => $metodo->id]);
    }

    public function show(MetodosPagos $metodoPago)
    {
        return response()->json($this->datosParaModal($metodoPago));
    }

    public function edit(MetodosPagos $metodoPago)
    {
        return response()->json($this->datosParaModal($metodoPago));
    }

    public function update(Request $request, MetodosPagos $metodoPago)
    {
        $datos = $this->validar($request);

        $metodoPago->update($datos);

        Alert::success('Método de pago guardado');

        return response()->json(['ok' => true, 'id' => $metodoPago->id]);
    }

    /**
     * Activar o desactivar, sin pasar por el modal.
     *
     * Va como boton propio en la fila y no como un campo mas del formulario, y
     * la razon es que es la accion que mas se va a repetir: dejar de aceptar un
     * talon y volverlo a aceptar en temporada alta son dos clics, y si estuviera
     * en el modal habria que abrirlo, cambiar el interruptor y guardar, que es un
     * modo de equivocarse: guardar el nombre con una tilde quitada mientras venia
     * a cambiar el estado.
     *
     * El boton NO se apaga cuando el metodo esta en uso, y tambien eso es
     * decision: desactivar un metodo en uso es justamente lo que se quiere hacer,
     * es el otro camino del aviso de "no se puede borrar".
     */
    public function cambiarEstado(MetodosPagos $metodoPago)
    {
        $metodoPago->update([
            MetodosPagos::ESTADO => ! $metodoPago->estado
        ]);

        Alert::success($metodoPago->estado
            ? 'Método de pago activado'
            : 'Método de pago desactivado');

        return response()->json(['ok' => true]);
    }

    public function destroy(MetodosPagos $metodoPago)
    {
        /*
         * La razon por la que no se puede esta en el modelo, porque la misma
         * pregunta la hacen el boton de la fila y la ficha, y en cuanto estuviera
         * en el controlador habria que repetirla en los dos sitios. Aqui solo se
         * escribe el texto, que si tiene que decir donde esta en uso para que el
         * usuario sepa que hacer en vez de probar.
         */
        if (! $metodoPago->sePuedeBorrar()) {
            throw ValidationException::withMessages([
                MetodosPagos::NOMBRE => 'No se puede borrar: este método de pago está en uso, en '
                    . $metodoPago->fraseDeUsos() . '. Se puede desactivar, que lo saca de los '
                    . 'desplegables sin tocar los cobros que ya se registraron con él.',
            ]);
        }

        $metodoPago->delete();

        Alert::success('Método de pago eliminado');

        return response()->json(['ok' => true]);
    }

    // ==================================================================
    // Lo de siempre
    // ==================================================================

    private function datosParaModal(MetodosPagos $metodoPago): array
    {
        $usos = $metodoPago->usos();

        return [
            'id' => $metodoPago->id,
            'nombre' => $metodoPago->nombre,
            'descripcion' => $metodoPago->descripcion,
            'estado' => (bool) $metodoPago->estado,
            'usos' => $usos,
            'frase_de_usos' => $metodoPago->fraseDeUsos(),
            'se_puede_borrar' => $usos === [],
        ];
    }

    private function validar(Request $request): array
    {
        $datos = $request->validate([
            MetodosPagos::NOMBRE => ['required', 'string', 'max:100'],
            MetodosPagos::DESCRIPCION => ['nullable', 'string', 'max:255'],

            /*
             * El estado se acepta como 0 o 1 y no como texto.
             *
             * Lo manda el interruptor del formulario, que manda un valor y no un
             * texto, y la regla de "booleano" aceptaria tambien "sí" y "on" y
             * cualquier cosa que se le ocurra a quien mande el formulario a mano.
             * Con in:0,1 se sabe exactamente que es un interruptor y no otra cosa.
             */
            MetodosPagos::ESTADO => ['required', 'in:0,1'],
        ], [
            MetodosPagos::NOMBRE . '.required' => 'Escriba el nombre del método de pago.',
            MetodosPagos::NOMBRE . '.max' => 'El nombre no puede pasar de 100 caracteres.',
            MetodosPagos::DESCRIPCION . '.max' => 'La descripción no puede pasar de 255 caracteres.',
            MetodosPagos::ESTADO . '.required' => 'Diga si el método está activo o inactivo.',
            MetodosPagos::ESTADO . '.in' => 'El estado tiene que ser activo o inactivo.',
        ]);

        /*
         * El estado a booleano de verdad.
         *
         * La columna es un TINYINT(1) y el formulario manda un 0 o un 1. Sin esto
         * se guardaria el numero tal cual, que MySQL lo acepta, y el resultado
         * seria el mismo porque el cast lo convierte al leer. Pero si mañana
         * alguien quita el cast, un 2 seria distinto de un 1 sin que nada avise,
         * y el fallo sale en el desplegable como un metodo que no aparece.
         */
        $datos[MetodosPagos::ESTADO] = (bool) $datos[MetodosPagos::ESTADO];

        /*
         * Y la descripcion a null cuando no viene nada.
         *
         * Sin esto un campo vacio llega como cadena vacia y se guarda como "" en
         * vez de no guardar nada, y en la lista sale un guion y en la ficha una
         * cadena invisible. Y el "que no vino en nada" no es lo mismo que "vino
         * vacio": un campo que el usuario ni ha tocado no llega en la peticion.
         */
        $descripcion = $datos[MetodosPagos::DESCRIPCION] ?? null;

        $datos[MetodosPagos::DESCRIPCION] = ($descripcion === '' || $descripcion === null)
            ? null
            : $descripcion;

        return $datos;
    }
}
