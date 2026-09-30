<?php

namespace App\Http\Controllers\Configuracion;

use App\DataTables\CajasDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\Cajas;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * Las cajas: donde entra el dinero cuando el cliente paga.
 *
 * Va en configuracion/ y no colgando de los cobros, y por lo mismo que las
 * demas categorias: es una lista de consulta, y una pantalla para cambiar tres
 * campos seria mas sitio en blanco que otra cosa. Ademas la pantalla de cobros
 * todavia no existe, asi que la caja no tiene de donde colgarse.
 *
 * TRES REGLAS, Y LAS TRES ESTAN EN EL SERVIDOR PORQUE SE PUEDEN SALTAR.
 *
 *  1. Una caja en uso no se borra: se desactiva. Si hay cobros en esa caja,
 *     borrarla dejaria la FK apuntando a una fila que ya no esta. Y desactivar
 *     sirve para lo mismo —sacarla de los desplegables— sin tocar lo que ya se
 *     registro con ella. Es la misma regla que en las monedas, y por el mismo
 *     motivo: un registro mal puesto se corrige, una caja que ya se uso se deja
 *     de usar, y ninguna de las dos cosas es motivo para romper lo que hay.
 *
 *  2. Y se cuentan como uso los cobros dados de baja tambien, no solo los
 *     vivos. La FK no distingue: la fila borrada sigue escribiendo el numero. Si
 *     se contaran solo los vivos, el boton dejaria pulsar el borrar y reventaria
 *     con un error de MySQL en vez de con un aviso que explica.
 *
 *  3. Una caja desactivada sale de los desplegables pero se sigue viendo en la
 *     lista, con su etiqueta de inactiva. Si desapareciera de la lista no habria
 *     forma de volverla a activar, y desactivar es una decision que se cambia: un
 *     fondo con que se trabajo en enero puede volver a usarse en noviembre.
 *
 * Y el nombre NO es unico en la base, a proposito y en contra de lo que hace el
 * catalogo de monedas. Alli lo unico es el codigo ISO, que por definicion no
 * puede repetirse. Aqui el nombre es libre, y los tipos de ingreso, las categorias
 * de costo y los tipos de pago de empleado —los otros tres catalogos del
 * taller— tampoco lo tienen. La consecuencia se acepta: si se repite un nombre,
 * el saldo de esa caja sale partido en dos filas, y es decision del taller.
 */
class CajaController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        $this->authorizeModule('configuracion.cajas', ['index', 'show']);
    }

    public function index(CajasDataTable $dataTable)
    {
        return $dataTable->render(
            'configuracion.cajas.index',
            [
                'title' => 'Cajas',
                'breadcrumbs' => [
                    ['label' => 'Inicio', 'url' => route('home')],
                    ['label' => 'Configuración'],
                    ['label' => 'Cajas'],
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

        $caja = Cajas::create($datos);

        Alert::success('Caja guardada');

        return response()->json(['ok' => true, 'id' => $caja->id]);
    }

    public function show(Cajas $caja)
    {
        return response()->json($this->datosParaModal($caja));
    }

    public function edit(Cajas $caja)
    {
        return response()->json($this->datosParaModal($caja));
    }

    public function update(Request $request, Cajas $caja)
    {
        $datos = $this->validar($request);

        $caja->update($datos);

        Alert::success('Caja guardada');

        return response()->json(['ok' => true, 'id' => $caja->id]);
    }

    /**
     * Activar o desactivar, sin pasar por el modal.
     *
     * Va como boton propio en la fila y no como un campo mas del formulario, y
     * la razon es que es la accion que mas se va a repetir: dejar de usar un
     * fondo y volverlo a usar en temporada alta son dos clics, y si estuviera en
     * el modal habria que abrirlo, cambiar el interruptor y guardar, que es un
     * modo de equivocarse: guardar el nombre con una tilde quitada mientras
     * venia a cambiar el estado.
     *
     * El boton NO se apaga cuando la caja esta en uso, y tambien eso es
     * decision: desactivar una caja en uso es justamente lo que se quiere hacer,
     * es el otro camino del aviso de "no se puede borrar".
     */
    public function cambiarEstado(Cajas $caja)
    {
        $caja->update([
            Cajas::ESTADO => ! $caja->estado
        ]);

        Alert::success($caja->estado
            ? 'Caja activada'
            : 'Caja desactivada');

        return response()->json(['ok' => true]);
    }

    public function destroy(Cajas $caja)
    {
        /*
         * La razon por la que no se puede esta en el modelo, porque la misma
         * pregunta la hacen el boton de la fila y la ficha, y en cuanto estuviera
         * en el controlador habria que repetirla en los dos sitios. Aqui solo se
         * escribe el texto, que si tiene que decir donde esta en uso para que el
         * usuario sepa que hacer en vez de probar.
         */
        if (! $caja->sePuedeBorrar()) {
            throw ValidationException::withMessages([
                Cajas::NOMBRE => 'No se puede borrar: esta caja tiene cobros, en '
                    . $caja->fraseDeUsos() . '. Se puede desactivar, que la saca de los '
                    . 'desplegables sin tocar los cobros que ya se registraron en ella.',
            ]);
        }

        $caja->delete();

        Alert::success('Caja eliminada');

        return response()->json(['ok' => true]);
    }

    // ==================================================================
    // Lo de siempre
    // ==================================================================

    private function datosParaModal(Cajas $caja): array
    {
        $usos = $caja->usos();

        return [
            'id' => $caja->id,
            'nombre' => $caja->nombre,
            'descripcion' => $caja->descripcion,
            'estado' => (bool) $caja->estado,
            'usos' => $usos,
            'frase_de_usos' => $caja->fraseDeUsos(),
            'se_puede_borrar' => $usos === [],
        ];
    }

    private function validar(Request $request): array
    {
        $datos = $request->validate([
            Cajas::NOMBRE => ['required', 'string', 'max:100'],
            Cajas::DESCRIPCION => ['nullable', 'string', 'max:255'],

            /*
             * El estado se acepta como 0 o 1 y no como texto.
             *
             * Lo manda el interruptor del formulario, que manda un valor y no un
             * texto, y la regla de "booleano" aceptaria tambien "sí" y "on" y
             * cualquier cosa que se le ocurra a quien mande el formulario a mano.
             * Con in:0,1 se sabe exactamente que es un interruptor y no otra
             * cosa.
             */
            Cajas::ESTADO => ['required', 'in:0,1'],
        ], [
            Cajas::NOMBRE . '.required' => 'Escriba el nombre de la caja.',
            Cajas::NOMBRE . '.max' => 'El nombre no puede pasar de 100 caracteres.',
            Cajas::DESCRIPCION . '.max' => 'La descripción no puede pasar de 255 caracteres.',
            Cajas::ESTADO . '.required' => 'Diga si la caja está activa o inactiva.',
            Cajas::ESTADO . '.in' => 'El estado tiene que ser activa o inactiva.',
        ]);

        /*
         * El estado a booleano de verdad.
         *
         * La columna es un TINYINT(1) y el formulario manda un 0 o un 1. Sin
         * esto se guardaria el numero tal cual, que MySQL lo acepta, y el
         * resultado seria el mismo porque el cast lo convierte al leer. Pero si
         * mañana alguien quita el cast, un 2 seria distinto de un 1 sin que nada
         * avise, y el fallo sale en el desplegable como una caja que no aparece.
         */
        $datos[Cajas::ESTADO] = (bool) $datos[Cajas::ESTADO];

        /*
         * Y la descripcion a null cuando no viene nada.
         *
         * Sin esto un campo vacio llega como cadena vacia y se guarda como "" en
         * vez de no guardar nada, y en la lista sale un guion y en la ficha una
         * cadena invisible. Y el "que no vine en nada" no es lo mismo que "vino
         * vacio": un campo que el usuario ni ha tocado no llega en la peticion.
         */
        $descripcion = $datos[Cajas::DESCRIPCION] ?? null;

        $datos[Cajas::DESCRIPCION] = ($descripcion === '' || $descripcion === null)
            ? null
            : $descripcion;

        return $datos;
    }
}
