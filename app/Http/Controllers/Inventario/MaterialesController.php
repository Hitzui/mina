<?php

namespace App\Http\Controllers\Inventario;

use App\DataTables\MovimientosInventarioDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Concerns\ValidaMovimientos;
use App\Http\Controllers\Controller;
use App\Models\MovimientosInventario;
use App\Services\InventarioService;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * El almacen: lo que entra de material y los ajustes de inventario.
 *
 * Aqui se mete el material al almacen. El consumo en un proceso no se
 * registra desde aqui, sino desde la pantalla del proceso, que es quien
 * sabe a que orden y a que etapa se le carga.
 */
class MaterialesController extends Controller
{
    use AuthorizesModule;
    use ValidaMovimientos;

    public function __construct()
    {
        $this->authorizeModule('movimientos_inventario');
    }

    public function index(Request $request, MovimientosInventarioDataTable $dataTable)
    {
        $title = 'Almacen';

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            ['label' => 'Almacen'],
        ];

        // El filtro de tipo viene por la url, que es lo que pinta el boton
        $tipo = $request->query('tipo');

        if (is_string($tipo) && $tipo !== '') {
            $dataTable->setTipo($tipo);
        }

        return $dataTable->render(
            'inventario.movimientos.index',
            compact('title', 'breadcrumbs', 'tipo')
        );
    }

    public function create()
    {
        $title = 'Registrar Entrada de Material';

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            ['label' => 'Almacen', 'url' => route('inventario.movimientos.index')],
            ['label' => 'Entrada'],
        ];

        $productos = $this->productosParaElFormulario();
        $monedas = $this->monedasParaElFormulario();

        return view(
            'inventario.movimientos.create',
            compact('title', 'breadcrumbs', 'productos', 'monedas')
        );
    }

    public function store(Request $request, InventarioService $inventario)
    {
        $validado = $this->validarMovimiento($request);
        $this->validarTipo($validado);

        /*
         * Aqui solo entran material y ajustes. Una salida sin proceso se
         * registra igual, pero no se pinta como materia prima de nadie: el
         * consumo del proceso tiene su propia pantalla, que es la que sabe
         * a que etapa se le carga.
         */
        $movimiento = $inventario->registrar($validado);

        Alert::toast($this->mensaje($movimiento))->success()->flash();

        return redirect()->route('inventario.movimientos.index');
    }

    public function show(MovimientosInventario $movimiento)
    {
        $title = 'Detalle del Movimiento';

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            ['label' => 'Almacen', 'url' => route('inventario.movimientos.index')],
            ['label' => 'Movimiento #' . $movimiento->id],
        ];

        $movimiento->load(['producto', 'orden_trabajo', 'proceso_orden.etapa', 'moneda']);

        return view(
            'inventario.movimientos.show',
            compact('title', 'breadcrumbs', 'movimiento')
        );
    }

    public function destroy(
        Request $request,
        MovimientosInventario $movimiento,
        InventarioService $inventario
    ) {
        /*
         * Un consumo de un proceso no se borra desde aqui: se borra desde
         * la pantalla del proceso, que deja el costo del proceso al dia. Si
         * se dejara, el material volveria al almacen pero el costo se
         * quedaria cobrado.
         */
        if ($movimiento->proceso_orden_id !== null) {
            Alert::toast(
                'Este consumo pertenece a un proceso y se borra desde la pantalla '
                . 'del proceso, para que el costo de ese proceso quede al dia.'
            )->warning()->flash();

            return redirect()->route('inventario.movimientos.index');
        }

        $inventario->revertir($movimiento);

        Alert::toast(
            'Movimiento eliminado. Las existencias se corrigieron solas.'
        )->success()->flash();

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('inventario.movimientos.index');
    }

    private function mensaje(MovimientosInventario $movimiento): string
    {
        $unidad = $movimiento->producto?->unidad_medida ?? '';

        $mensaje = sprintf(
            'Material registrado: %s de %s.',
            $this->cantidadLegible((float) $movimiento->cantidad)
                . ($unidad !== '' ? ' ' . $unidad : ''),
            $movimiento->producto?->nombre ?? 'material'
        );

        /*
         * El saldo se vuelve a pedir al producto en vez de usar el valor
         * cacheado: el movimiento ya esta guardado y el saldo actualizado,
         * asi que el numero que se muestra es el de ahora, no el que tenia
         * el producto antes de registrarlo.
         */
        $existencia = $movimiento->producto?->inventario()->value('cantidad_actual');

        if ($existencia !== null) {
            $mensaje .= sprintf(
                ' En el almacen quedan %s%s.',
                $this->cantidadLegible((float) $existencia),
                $unidad !== '' ? ' ' . $unidad : ''
            );

            if ($movimiento->producto?->estaPorDebajoDelMinimo()) {
                $mensaje .= ' Esta por debajo del minimo: conviene reponer.';
            }
        }

        return $mensaje;
    }

    private function cantidadLegible(float $cantidad): string
    {
        return rtrim(rtrim(number_format($cantidad, 3), '0'), '.') ?: '0';
    }
}
