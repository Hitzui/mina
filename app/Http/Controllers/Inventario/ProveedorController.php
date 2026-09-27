<?php

namespace App\Http\Controllers\Inventario;

use App\DataTables\ProveedoresDataTable;
use App\DataTables\ProveedorSelectorDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\Proveedore;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * Proveedores: los que venden al taller.
 *
 * Es dato maestro, como los materiales, asi que el alta, la edicion y la
 * ficha van en modal sobre el listado. El codigo lo pone el sistema.
 *
 * Un proveedor que ya tiene compras no se borra de verdad, se desactiva:
 * las compras registradas dependen de el, y borrarlo dejaria filas
 * apuntando a un proveedor que no existe.
 */
class ProveedorController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        // El selector va aqui, y no en el permiso de crear, porque elegir un
        // proveedor es mirar el catalogo: quien registra una compra tiene
        // que poder verlo para saber a quien le compra. Sin esto, el metodo
        // se quedaria sin permiso, y con el trait puesto no se cuela nadie.
        $this->authorizeModule('proveedores', ['index', 'show', 'selector']);
    }

    /**
     * Los proveedores para elegir uno desde un modal.
     *
     * Devuelve solo los datos de la tabla, sin pagina alrededor, porque lo
     * que lo pide es el buscador de DataTables dentro de una ventana y no
     * una visita. El buscador de la tabla pide filas paginadas y las
     * quiere en json; si esta ruta devolviera html, el buscador se
     * quedaria en blanco sin decir por que.
     *
     * El permission de ver va aqui explicito porque el constructor solo lo
     * pone en index y show. Escribir en un modal que se abre encima de un
     * formulario no es escribir en el catalogo: quien registra una compra
     * tiene que poder mirar el catalogo para saber a quien le compra.
     */
    public function selector(ProveedorSelectorDataTable $dataTable)
    {
        return $dataTable->ajax();
    }

    public function index(ProveedoresDataTable $dataTable)
    {
        $title = 'Proveedores';

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            ['label' => 'Proveedores'],
        ];

        return $dataTable->render(
            'inventario.proveedores.index',
            compact('title', 'breadcrumbs')
        );
    }

    /**
     * El alta se hace desde el modal de la lista. Esta ruta se conserva para
     * que la url no de error.
     */
    public function create()
    {
        return redirect()->route('inventario.proveedores.index');
    }

    public function store(Request $request)
    {
        $proveedor = Proveedore::crearConCodigo($this->validar($request));

        $mensaje = 'Proveedor creado correctamente con el código '
            . $proveedor->codigo . '.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $mensaje,
                'codigo' => $proveedor->codigo,
            ]);
        }

        Alert::toast($mensaje)->success()->flash();

        return redirect()->route('inventario.proveedores.index');
    }

    /**
     * La ficha del proveedor, en JSON para que la rellene el modal.
     *
     * Las compras se piden solo como un numero. Va en la ficha para poder
     * decir cuantos pedidos tiene este proveedor, que es lo que hace
     * falta para decidir si se desactiva o se borra.
     */
    public function show(Proveedore $proveedor)
    {
        return response()->json([
            'id' => $proveedor->id,
            'codigo' => $proveedor->codigo,
            'nombre' => $proveedor->nombre,
            'contacto' => $proveedor->contacto ?? '',
            'telefono' => $proveedor->telefono ?? '',
            'email' => $proveedor->email ?? '',
            'direccion' => $proveedor->direccion ?? '',
            'observaciones' => $proveedor->observaciones ?? '',
            'estado' => (bool) $proveedor->estado,
            'compras' => $proveedor->compras()->count(),
            'tiene_compras' => $proveedor->tieneCompras(),
        ]);
    }

    /**
     * Los datos para rellenar el formulario de edicion.
     */
    public function edit(Proveedore $proveedor)
    {
        return response()->json([
            'id' => $proveedor->id,
            'codigo' => $proveedor->codigo,
            'nombre' => $proveedor->nombre,
            'contacto' => $proveedor->contacto ?? '',
            'telefono' => $proveedor->telefono ?? '',
            'email' => $proveedor->email ?? '',
            'direccion' => $proveedor->direccion ?? '',
            'observaciones' => $proveedor->observaciones ?? '',
            'estado' => (bool) $proveedor->estado,
        ]);
    }

    public function update(Request $request, Proveedore $proveedor)
    {
        $proveedor->update($this->validar($request));

        $mensaje = 'Proveedor actualizado correctamente.';

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $mensaje]);
        }

        Alert::toast($mensaje)->success()->flash();

        return redirect()->route('inventario.proveedores.index');
    }

    public function destroy(Request $request, Proveedore $proveedor)
    {
        /*
         * Con compras registradas no se borra de verdad: se desactiva. Las
         * compras ya hechas dependen de este proveedor, y borrarlo dejaria
         * el historial de lo que se compro sin origen.
         */
        if ($proveedor->tieneCompras()) {
            $proveedor->update(['estado' => false]);

            $mensaje = 'El proveedor tiene compras registradas, así que se '
                . 'desactivó en vez de borrarse: el historial de las compras '
                . 'necesita que siga existiendo.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'desactivado' => true,
                    'message' => $mensaje,
                ]);
            }

            Alert::toast($mensaje)->warning()->flash();

            return redirect()->route('inventario.proveedores.index');
        }

        $proveedor->delete();

        $mensaje = 'Proveedor eliminado correctamente.';

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $mensaje]);
        }

        Alert::toast($mensaje)->success()->flash();

        return redirect()->route('inventario.proveedores.index');
    }

    /**
     * Lo que llega del formulario.
     *
     * El codigo no se valida ni se pide: lo pone Proveedore::crearConCodigo.
     * En la edicion tampoco se toca, porque un proveedor cambia de telefono o
     * de direccion, no de identidad.
     *
     * El correo se valida con la regla email de Laravel, que es la de
     * siempre y no se ha tocado. Se anota porque en compras el correo es por
     * donde se manda la factura, y ahi si seria raro dejarlo sin comprobar.
     *
     * @return array
     */
    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'contacto' => ['nullable', 'string', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'direccion' => ['nullable', 'string', 'max:180'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'estado' => ['nullable', 'boolean'],
        ], [
            'nombre.required' => 'El proveedor necesita un nombre.',
            'nombre.max' => 'El nombre es demasiado largo.',
            'email.email' => 'El correo no parece una dirección válida.',
            'telefono.max' => 'El teléfono es demasiado largo.',
            'contacto.max' => 'El contacto es demasiado largo.',
            'direccion.max' => 'La dirección es demasiado larga.',
            'observaciones.max' => 'Las observaciones son demasiado largas.',
        ]);
    }
}
