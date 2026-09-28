<?php

namespace App\Http\Controllers\Inventario;

use App\DataTables\ComprasDataTable;
use App\DataTables\ProveedorSelectorDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\Moneda;
use App\Models\Producto;
use App\Services\ComprasInventarioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * Compras: lo que se le compra a un proveedor.
 *
 * A diferencia de los proveedores y los materiales, aqui no hay modal. Una
 * compra lleva cabecera y un numero cualquiera de lineas de material, y una
 * rejilla de lineas en un modal se queda corta y es incomoda de rellenar.
 *
 * Lo que si decide el estado es el almacen: mientras la compra no este
 * finalizada, el material no entra. Al finalizarla, cada linea genera una
 * entrada de almacen; al deshacerla, el material sale. Eso vive en
 * ComprasInventarioService, no aqui, para que ningun camino lo esquive.
 */
class CompraController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        $this->authorizeModule('compras');
    }

    public function index(ComprasDataTable $dataTable)
    {
        $title = 'Compras';

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            ['label' => 'Compras'],
        ];

        return $dataTable->render(
            'inventario.compras.index',
            compact('title', 'breadcrumbs')
        );
    }

    public function create(ProveedorSelectorDataTable $selectorProveedor)
    {
        $compra = new Compra([
            Compra::FECHA => today(),
            Compra::ESTADO => Compra::ESTADO_PENDIENTE,
        ]);

        return view(
            'inventario.compras.create',
            $this->datosDeFormulario($compra, $selectorProveedor)
        );
    }

    public function store(Request $request, ComprasInventarioService $inventario)
    {
        $datos = $this->validarCabecera($request);
        $lineas = $this->validarLineas($request);

        $compra = DB::transaction(function () use ($datos, $lineas, $inventario) {
            $compra = Compra::crearConCodigo($datos);

            $this->guardarLineas($compra, $lineas);

            /*
             * Los totales se calculan con lo que dicen las lineas, nunca con
             * lo que venga del formulario. Y van antes de sincronizar con el
             * almacen, porque el total es lo que se guarda en el movimiento
             * de cada linea.
             */
            $compra->calcularTotales($datos['porcentaje_impuesto'] ?? null);
            $compra->save();

            return $inventario->sincronizar($compra);
        });

        Alert::toast($this->mensaje($compra))->success()->flash();

        return redirect()->route('inventario.compras.show', $compra);
    }

    public function show(Compra $compra)
    {
        $compra->load(['proveedor', 'moneda', 'detalles.producto']);

        $title = 'Compra ' . $compra->codigo;

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            ['label' => 'Compras', 'url' => route('inventario.compras.index')],
            ['label' => $compra->codigo],
        ];

        // Si la compra esta en el almacen, que movimientos genero: hace
        // falta para entender de donde sale el stock
        $movimientos = $compra->movimientos()->whereNull('deleted_at')->get();

        return view(
            'inventario.compras.show',
            compact('title', 'breadcrumbs', 'compra', 'movimientos')
        );
    }

    public function edit(Compra $compra, ProveedorSelectorDataTable $selectorProveedor)
    {
        $compra->load('detalles');

        return view(
            'inventario.compras.edit',
            $this->datosDeFormulario($compra, $selectorProveedor)
        );
    }

    public function update(
        Request $request,
        Compra $compra,
        ComprasInventarioService $inventario
    ) {
        $datos = $this->validarCabecera($request);
        $lineas = $this->validarLineas($request);

        $compra = DB::transaction(function () use ($compra, $datos, $lineas, $inventario) {
            unset($datos['porcentaje_impuesto']);

            $compra->update($datos);

            $this->guardarLineas($compra, $lineas);

            $compra->calcularTotales();
            $compra->save();

            return $inventario->sincronizar($compra);
        });

        Alert::toast($this->mensaje($compra))->success()->flash();

        return redirect()->route('inventario.compras.show', $compra);
    }

    public function destroy(Compra $compra, ComprasInventarioService $inventario)
    {
        /*
         * La compra se borra de verdad, con forceDelete(), y no con el
         * borrado logico de siempre. El motivo es el almacen: el servicio
         * de inventario da de baja los movimientos de la compra, y si la
         * compra se quedara tambien ella dada de baja, un borrado logico
         * en cascada de la base los volveria a activar al restaurarla, con
         * el almacen descuadrado y sin que nadie los volviera a crear.
         */
        $inventario->olvidar($compra);

        DB::transaction(function () use ($compra) {
            $compra->detalles()->get()->each->delete();
            $compra->forceDelete();
        });

        Alert::toast('Compra eliminada. Los movimientos de almacén se quitaron con ella.')->success()->flash();

        return redirect()->route('inventario.compras.index');
    }

    /**
     * Lo que necesitan los formularios de alta y de edicion.
     */
    private function datosDeFormulario(
        Compra $compra,
        ProveedorSelectorDataTable $selectorProveedor
    ): array
    {

        $productos = Producto::query()
            ->where('estado', true)
            ->whereNull('deleted_at')
            ->with('inventario')
            ->orderBy('nombre')
            ->get();

        $monedas = Moneda::query()
            ->where('estado', true)
            ->orderBy('codigo')
            ->get();

        $esEdicion = $compra->exists;

        /*
         * Las lineas que ya tiene la compra, para el javascript de la
         * rejilla. Se mandan con numeros y no con texto formateado: un
         * "1,000" dentro de un input de numero no se lee, y el separador
         * de miles depende del navegador y no del servidor.
         */
        $lineasParaJs = $compra->exists
            ? $compra->detalles
                ->map(fn($detalle) => [
                    'producto_id' => (int) $detalle->producto_id,
                    'cantidad' => (float) $detalle->cantidad,
                    'costo_unitario' => (float) $detalle->costo_unitario,
                ])
                ->values()
                ->all()
            : [];

        $title = $esEdicion ? 'Editar compra ' . $compra->codigo : 'Nueva compra';

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            ['label' => 'Compras', 'url' => route('inventario.compras.index')],
            ['label' => $esEdicion ? $compra->codigo : 'Nueva', 'url' => $esEdicion ? route('inventario.compras.show', $compra) : '#'],
            ['label' => $esEdicion ? 'Editar' : 'Nueva'],
        ];

        return compact(
            'title',
            'breadcrumbs',
            'compra',
            'productos',
            'monedas',
            'esEdicion',
            'lineasParaJs',
            'selectorProveedor'
        );
    }

    /**
     * La cabecera de la compra.
     *
     * El codigo no se acepta: lo pone Compra::crearConCodigo. Los totales
     * tampoco, y eso se explica en el comentario del metodo calcularTotales
     * del modelo.
     */
    private function validarCabecera(Request $request): array
    {
        return $request->validate([
            'proveedor_id' => [
                'required',
                'exists:proveedores,id',
            ],
            'fecha' => ['required', 'date'],
            'numero_documento' => ['nullable', 'string', 'max:60'],
            'moneda_id' => ['required', 'exists:monedas,id'],
            'porcentaje_impuesto' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'estado' => ['required', 'in:0,1,2,3'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ], [
            'proveedor_id.required' => 'Elija el proveedor al que se le compra.',
            'fecha.required' => 'Indique la fecha de la compra.',
            'moneda_id.required' => 'Elija la moneda de la compra.',
            'porcentaje_impuesto.max' => 'El impuesto no puede pasar de 100 %.',
            'estado.in' => 'Ese estado de compra no existe.',
        ]);
    }

    /**
     * Lee las lineas de la compra del formulario y devuelve solo las que
     * llevan material.
     *
     * El nombre viene como productos[] con los indices de los inputs.
     *
     * Lo importante es que se valida lo que se devuelve, no el dato entero.
     * Antes se Filtraban las filas vacias y despues se validaba el dato
     * completo con $request->validate(), que es validar justamente lo que se
     * acaba de tirar. Con lo cual la fila de ejemplo —la que esta oculta y
     * solo sirve de molde— y cualquier fila anadida y dejada en blanco se
     * quejaban de que faltaba el material, con el material de verdad elegido
     * en otra fila. El mensaje senalaba a la fila equivocada, que es la
     * peor manera de fallar.
     *
     * Se conservan las claves de origen de cada fila, en vez de
     * renumerarlas, para que el error siga hablando del indice que el
     * usuario tiene delante en la pantalla y no de un numero que ya no
     * existe.
     */
    private function validarLineas(Request $request): array
    {
        $crudo = $request->input('productos', []);

        if (! is_array($crudo)) {
            return [];
        }

        $limpias = [];

        foreach ($crudo as $clave => $fila) {
            if (! is_array($fila) || ($fila['producto_id'] ?? '') === '') {
                continue;
            }

            $limpias[$clave] = $fila;
        }

        if (empty($limpias)) {
            throw ValidationException::withMessages([
                'productos' => 'La compra tiene que llevar al menos una línea de material.',
            ]);
        }

        $validador = Validator::make(['productos' => $limpias], [
            'productos' => ['required', 'array', 'min:1'],
            'productos.*.producto_id' => ['required', 'exists:productos,id'],
            'productos.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'productos.*.costo_unitario' => ['required', 'numeric', 'min:0'],
        ], [
            'productos.required' => 'La compra tiene que llevar al menos una línea de material.',
            'productos.*.producto_id.required' => 'Elija el material de la línea.',
            'productos.*.producto_id.exists' => 'Ese material ya no está en el catálogo. Vuelva a elegirlo.',
            'productos.*.cantidad.required' => 'Indique la cantidad de la línea.',
            'productos.*.cantidad.gt' => 'La cantidad tiene que ser mayor que cero.',
            'productos.*.costo_unitario.required' => 'Indique el costo unitario de la línea.',
        ]);

        if ($validador->fails()) {
            throw new ValidationException($validador);
        }

        return $validador->validated()['productos'];
    }

    /**
     * Deja las lineas de la compra como deben quedar.
     *
     * Se borran las que desaparecieron y se guardan las nuevas. Las que se
     * quedan se tocan, para no perderlas. Cada linea calcula su subtotal en
     * el modelo, nunca desde aqui.
     */
    private function guardarLineas(Compra $compra, array $lineas): void
    {
        $quedan = [];

        foreach ($compra->detalles as $existente) {
            $quedan[$existente->producto_id] = $existente;
        }

        foreach ($lineas as $fila) {
            $productoId = (int) $fila['producto_id'];

            if (isset($quedan[$productoId])) {
                $detalle = $quedan[$productoId];
                unset($quedan[$productoId]);
            } else {
                $detalle = new DetalleCompra();
                $detalle->compra_id = $compra->id;
                $detalle->producto_id = $productoId;
            }

            $detalle->cantidad = $fila['cantidad'];
            $detalle->costo_unitario = $fila['costo_unitario'];
            $detalle->calcularSubtotal();
            $detalle->save();
        }

        // Las que ya no estan en el formulario
        foreach ($quedan as $sobrante) {
            $sobrante->delete();
        }
    }

    /**
     * El aviso que sale al guardar, segun lo que pasara con el almacen.
     */
    private function mensaje(Compra $compra): string
    {
        $mensaje = 'Compra ' . $compra->codigo . ' guardada correctamente.';

        if ($compra->esFinalizada()) {
            $mensaje .= ' El material entró al almacén y el costo promedio se recalculó.';
        } else {
            $mensaje .= ' El material todavía no entra al almacén: solo entra cuando la compra se finaliza.';
        }

        return $mensaje;
    }
}
