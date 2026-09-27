<?php

namespace App\Http\Controllers\Procesos;

use App\DataTables\EmpleadosSelectorDataTable;
use App\DataTables\OrdenesTrabajoDataTable;
use App\DataTables\ProcesosOrdenDataTable;
use App\DataTables\TrabajosEmpleadosDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\OrdenesTrabajo;
use App\Models\ProcesosOrden;
use App\Models\TiposPagoEmpleado;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class OrdenTrabajoController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        $this->authorizeModule('ordenes_trabajo');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(OrdenesTrabajoDataTable $dataTable)
    {
        $title = "Órdenes de Trabajo";

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            ['label' => 'Órdenes de Trabajo'],
        ];

        return $dataTable->render(
            'procesos.ordenes_trabajo.index',
            compact('title', 'breadcrumbs')
        );
    }

    public function calendario()
    {
        $title = "Órdenes de Trabajo";

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            ['label' => 'Órdenes de Trabajo'],
        ];

        return view(
            'procesos.ordenes_trabajo.calendario',
            compact('title', 'breadcrumbs')
        );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $title = "Ingresar Orden de Trabajo";

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            [
                'label' => 'Órdenes de Trabajo',
                'url' => route('procesos.ordenes_trabajo.index')
            ],
            ['label' => 'Ingresar Orden de Trabajo'],
        ];

        return view(
            'procesos.ordenes_trabajo.create',
            compact('title', 'breadcrumbs')
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'cliente_id' => [
                'required',
                'integer',
                'exists:clientes,id',
            ],

            'fecha' => [
                'required',
                'date',
            ],

            'descripcion' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'peso_mineral' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'unidad_peso' => [
                'required',
                'string',
                'in:toneladas,kg,gramos',
            ],
        ]);

        $validated['codigo'] = $this->generarCodigo();
        $validated['estado'] = 1;

        $ordenTrabajo = OrdenesTrabajo::create($validated);

        Alert::toast(
            'Orden de trabajo creada correctamente.'
        )->success()->flash();

        return redirect()->route(
            'procesos.ordenes_trabajo.show',
            $ordenTrabajo
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(
        string                     $id,
        ProcesosOrdenDataTable     $dataTable
    )
    {
        $title = "Información de Orden de Trabajo";
        $ordenTrabajo = OrdenesTrabajo::findOrFail($id);
        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            [
                'label' => 'Órdenes de Trabajo',
                'url' => route('procesos.ordenes_trabajo.index')
            ],
            ['label' => 'Información de Orden de Trabajo'],
        ];

        if ($ordenTrabajo) {
            $dataTable->setOrdenTrabajoId($ordenTrabajo->id);

            return view(
                'procesos.ordenes_trabajo.show',
                compact(
                    'title',
                    'breadcrumbs',
                    'ordenTrabajo',
                    'dataTable'
                )
            );
        } else {
            return redirect()->route('procesos.ordenes_trabajo.index');
        }

    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $title = "Editar Orden de Trabajo";
        $ordenTrabajo = OrdenesTrabajo::findOrFail($id);
        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            [
                'label' => 'Órdenes de Trabajo',
                'url' => route('procesos.ordenes_trabajo.index')
            ],
            [
                'label' => 'Información de Orden de Trabajo',
                'url' => route(
                    'procesos.ordenes_trabajo.show',
                    $ordenTrabajo
                )
            ],
            ['label' => 'Editar Orden de Trabajo'],
        ];

        if ($ordenTrabajo) {
            return view(
                'procesos.ordenes_trabajo.edit',
                compact('title', 'breadcrumbs', 'ordenTrabajo')
            );
        } else {
            return redirect()->route('procesos.ordenes_trabajo.index');
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $ordenTrabajo = OrdenesTrabajo::findOrFail($id);
        $tieneOperaciones = $ordenTrabajo
            ->procesos_ordenes()
            ->exists();

        if ($tieneOperaciones) {

            $validated = $request->validate([
                'fecha' => [
                    'required',
                    'date',
                ],

                'descripcion' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],
            ]);

        } else {

            $validated = $request->validate([
                'cliente_id' => [
                    'required',
                    'integer',
                    'exists:clientes,id',
                ],

                'fecha' => [
                    'required',
                    'date',
                ],

                'descripcion' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],

                'peso_mineral' => [
                    'required',
                    'numeric',
                    'gt:0',
                ],

                'unidad_peso' => [
                    'required',
                    'string',
                    'in:toneladas,kg,gramos',
                ],
            ]);
        }

        $ordenTrabajo->update($validated);

        Alert::toast(
            'Orden de trabajo actualizada correctamente.'
        )->success()->flash();

        return redirect()->route(
            'procesos.ordenes_trabajo.show',
            $ordenTrabajo->id
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(OrdenesTrabajo $ordenTrabajo)
    {
        /*
         * TODO:
         *
         * Antes de eliminar una OT debemos validar:
         *
         * - Procesos registrados
         * - Costos
         * - Mano de obra
         * - Movimientos de inventario
         * - Producción
         * - Recuperaciones
         * - Liquidaciones
         * - Ingresos
         * - Cobros
         * - Pagos pendientes
         *
         * Estas validaciones se incorporarán cuando
         * dichos módulos estén implementados.
         */

        $ordenTrabajo->delete();

        Alert::toast(
            'Orden de trabajo eliminada correctamente.'
        )->success()->flash();

        return redirect()->route(
            'procesos.ordenes_trabajo.index'
        );
    }

    private function generarCodigo(): string
    {
        $anio = now()->year;

        $ultimo = OrdenesTrabajo::withTrashed()
            ->where('codigo', 'like', "OT-{$anio}-%")
            ->orderByDesc('id')
            ->first();

        $numero = 1;

        if ($ultimo) {
            $partes = explode('-', $ultimo->codigo);
            $numero = ((int)end($partes)) + 1;
        }

        return sprintf(
            'OT-%d-%04d',
            $anio,
            $numero
        );
    }
}
