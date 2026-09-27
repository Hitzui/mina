<?php

namespace App\Http\Controllers\Inventario;

use App\DataTables\ProductosDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * El catalogo de material del almacen: cemento, quimicos, reactivos.
 *
 * Es dato maestro. El producto por si solo no dice cuanto hay ni a cuanto
 * cuesta: eso vive en el saldo (inventario_productos), que se mantiene con
 * cada movimiento. Por eso al borrar un producto con movimientos no se
 * borra de verdad, para que el kardex no quede huerfano.
 */
class ProductoController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        $this->authorizeModule('productos');
    }

    public function index(ProductosDataTable $dataTable)
    {
        $title = 'Materiales';

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            ['label' => 'Materiales'],
        ];

        return $dataTable->render(
            'inventario.productos.index',
            compact('title', 'breadcrumbs')
        );
    }

    public function create()
    {
        $title = 'Nuevo Material';

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            ['label' => 'Materiales', 'url' => route('inventario.productos.index')],
            ['label' => 'Nuevo Material'],
        ];

        return view(
            'inventario.productos.create',
            compact('title', 'breadcrumbs')
        );
    }

    public function store(Request $request)
    {
        Producto::create($this->validar($request));

        Alert::toast('Material creado correctamente.')->success()->flash();

        return redirect()->route('inventario.productos.index');
    }

    public function show(Producto $producto)
    {
        $title = 'Informacion del Material';

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            ['label' => 'Materiales', 'url' => route('inventario.productos.index')],
            ['label' => $producto->nombre],
        ];

        // Los ultimos movimientos, para ver de donde viene el saldo
        $movimientos = $producto->movimientos()
            ->with(['orden_trabajo', 'proceso_orden.etapa'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return view(
            'inventario.productos.show',
            compact('title', 'breadcrumbs', 'producto', 'movimientos')
        );
    }

    public function edit(Producto $producto)
    {
        $title = 'Editar Material';

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            ['label' => 'Materiales', 'url' => route('inventario.productos.index')],
            ['label' => $producto->nombre],
            ['label' => 'Editar'],
        ];

        return view(
            'inventario.productos.edit',
            compact('title', 'breadcrumbs', 'producto')
        );
    }

    public function update(Request $request, Producto $producto)
    {
        $producto->update($this->validar($request, $producto));

        Alert::toast('Material actualizado correctamente.')->success()->flash();

        return redirect()->route('inventario.productos.index');
    }

    public function destroy(Producto $producto)
    {
        $conMovimientos = $producto->movimientos()->exists();

        /*
         * Un material que ya tiene movimientos no se borra de verdad: el
         * kardex lo dejaria apuntando a algo que no existe, y el costo de
         * los procesos donde se uso se quedaria sin poder explicar de donde
         * salio. Se desactiva, que es lo que ya se hace con el resto.
         */
        if ($conMovimientos) {
            $producto->update(['estado' => false]);

            Alert::toast(
                'El material tiene movimientos en el almacen, asi que se desactivo '
                . 'en vez de borrarse: el historial del kardex y los costos de los '
                . 'procesos necesitan que siga existiendo.'
            )->warning()->flash();

            return redirect()->route('inventario.productos.index');
        }

        $producto->delete();

        Alert::toast('Material eliminado correctamente.')->success()->flash();

        return redirect()->route('inventario.productos.index');
    }

    /**
     * @return array
     */
    private function validar(Request $request, ?Producto $producto = null): array
    {
        return $request->validate([
            'codigo' => [
                'required',
                'string',
                'max:30',
                Rule::unique('productos', 'codigo')
                    ->whereNull('deleted_at')
                    ->ignore($producto?->id),
            ],
            'nombre' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'unidad_medida' => ['required', 'string', 'max:15'],
            'categoria' => ['nullable', 'string', 'max:60'],
            'stock_minimo' => ['nullable', 'numeric', 'min:0'],
            'estado' => ['nullable', 'boolean'],
        ], [
            'codigo.required' => 'El material necesita un codigo.',
            'codigo.unique' => 'Ya existe un material con ese codigo.',
            'codigo.max' => 'El codigo es demasiado largo.',
            'nombre.required' => 'El material necesita un nombre.',
            'nombre.max' => 'El nombre es demasiado largo.',
            'unidad_medida.required' => 'Indique en que se mide el material (kg, litro, tm...).',
            'unidad_medida.max' => 'La unidad es demasiado larga.',
            'stock_minimo.min' => 'El minimo no puede ser negativo.',
        ]);
    }
}
