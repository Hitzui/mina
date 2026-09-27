<?php

namespace App\Http\Controllers\Inventario;

use App\DataTables\ProductosDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\MovimientosInventario;
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

    /**
     * El alta se hace desde el modal de la lista.
     *
     * Esta ruta se conserva para que la url no de error, igual que en los
     * modales de costos y de equipos del proceso.
     */
    public function create()
    {
        return redirect()->route('inventario.productos.index');
    }

    public function store(Request $request)
    {
        Producto::create($this->validar($request));

        $mensaje = 'Material creado correctamente.';

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $mensaje]);
        }

        Alert::toast($mensaje)->success()->flash();

        return redirect()->route('inventario.productos.index');
    }

    /**
     * La ficha del material, en JSON para que la rellene el modal.
     *
     * Solo se manda el kardex reciente. El historial entero esta en la
     * pantalla del almacen, que es donde se consulta: traerlo entero en
     * cada apertura haria la respuesta grande sin que aportara nada, ya
     * que el modal no lo muestra entero.
     */
    public function show(Producto $producto)
    {
        $movimientos = $producto->movimientos()
            ->with(['orden_trabajo', 'proceso_orden.etapa'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn ($movimiento) => [
                'id' => $movimiento->id,
                'fecha' => $movimiento->fecha?->format('d/m/Y') ?? '—',
                'tipo' => $movimiento->nombreTipo(),
                'es_entrada' => $movimiento->esEntrada(),
                'cantidad' => rtrim(
                    rtrim(number_format((float) $movimiento->cantidad, 3), '0'),
                    '.'
                ) ?: '0',
                'costo_unitario' => number_format((float) $movimiento->costo_unitario, 2),
                'costo_total' => number_format((float) $movimiento->costo_total, 2),
                'destino' => $this->destinoDe($movimiento),
            ])
            ->all();

        return response()->json([
            'id' => $producto->id,
            'codigo' => $producto->codigo,
            'nombre' => $producto->nombre,
            'descripcion' => $producto->descripcion ?? '',
            'unidad_medida' => $producto->unidad_medida,
            'categoria' => $producto->categoria ?? '',
            'stock_minimo' => (float) $producto->stock_minimo,
            'estado' => (bool) $producto->estado,
            'existencia' => rtrim(
                rtrim(number_format($producto->existencia, 3), '0'),
                '.'
            ) ?: '0',
            'costo_promedio' => (float) $producto->costo_promedio,
            'valor_inventario' => (float) $producto->valor_inventario,
            'por_debajo_del_minimo' => $producto->estaPorDebajoDelMinimo(),

            // El enlace al kardex, para quien quiera ver el historial entero
            'url_kardex' => route('inventario.movimientos.index'),

            'movimientos' => $movimientos,
        ]);
    }

    /**
     * Donde fue a parar el material de un movimiento.
     */
    private function destinoDe(MovimientosInventario $movimiento): string
    {
        if ($movimiento->proceso_orden_id !== null) {
            $proceso = $movimiento->proceso_orden?->etapa?->nombre
                ?? $movimiento->proceso_orden?->codigo;

            return 'Proceso: ' . ($proceso ?? '—');
        }

        return $movimiento->orden_trabajo
            ? 'Orden: ' . $movimiento->orden_trabajo->codigo
            : 'Almacén';
    }

    /**
     * Los datos para rellenar el formulario de edicion.
     */
    public function edit(Producto $producto)
    {
        return response()->json([
            'id' => $producto->id,
            'codigo' => $producto->codigo,
            'nombre' => $producto->nombre,
            'descripcion' => $producto->descripcion ?? '',
            'unidad_medida' => $producto->unidad_medida,
            'categoria' => $producto->categoria ?? '',
            'stock_minimo' => (float) $producto->stock_minimo,
            'estado' => (bool) $producto->estado,
        ]);
    }

    public function update(Request $request, Producto $producto)
    {
        $producto->update($this->validar($request, $producto));

        $mensaje = 'Material actualizado correctamente.';

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $mensaje]);
        }

        Alert::toast($mensaje)->success()->flash();

        return redirect()->route('inventario.productos.index');
    }

    public function destroy(Request $request, Producto $producto)
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

            $mensaje = 'El material tiene movimientos en el almacén, así que se '
                . 'desactivó en vez de borrarse: el historial del kardex y los '
                . 'costos de los procesos necesitan que siga existiendo.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'desactivado' => true,
                    'message' => $mensaje,
                ]);
            }

            Alert::toast($mensaje)->warning()->flash();

            return redirect()->route('inventario.productos.index');
        }

        $producto->delete();

        $mensaje = 'Material eliminado correctamente.';

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $mensaje]);
        }

        Alert::toast($mensaje)->success()->flash();

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
