<?php

namespace App\Http\Controllers\Procesos;

use App\DataTables\EmpleadosSelectorDataTable;
use App\DataTables\ProcesosOrdenDataTable;
use App\DataTables\TrabajosEmpleadosDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\Etapa;
use App\Models\OrdenesTrabajo;
use App\Models\ProcesosOrden;
use App\Models\TiposPagoEmpleado;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RealRashid\SweetAlert\Facades\Alert;

class ProcesoOrdenController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        $this->authorizeModule('procesos_orden');
    }

    /**
     * Pantalla del proceso.
     *
     * Aqui viven los trabajos de los empleados: el trabajo cuelga del
     * proceso, no de la orden, asi que es su sitio natural para
     * registrarlos y para ver cuanto se le paga al proceso.
     */
    public function show(
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden,
        TrabajosEmpleadosDataTable $trabajosDataTable,
        EmpleadosSelectorDataTable $empleadosSelectorDataTable
    ) {
        $this->validarProcesoPertenece($ordenTrabajo, $procesoOrden);

        $procesoOrden->load('etapa');

        $title = 'Información del Proceso';

        $breadcrumbs = [
            [
                'label' => 'Dashboard',
                'url' => route('home'),
            ],
            [
                'label' => 'Órdenes de Trabajo',
                'url' => route('procesos.ordenes_trabajo.index'),
            ],
            [
                'label' => $ordenTrabajo->codigo,
                'url' => route('procesos.ordenes_trabajo.show', $ordenTrabajo),
            ],
            [
                'label' => $procesoOrden->nombre_completo,
                'url' => '#',
            ],
        ];

        $trabajosDataTable->setProcesoOrdenId($procesoOrden->id);
        $trabajosDataTable->setOrdenTrabajoId($ordenTrabajo->id);

        // Para el texto de cuanto trabajo tiene el proceso
        $cantidadTrabajos = $procesoOrden->trabajos_empleados()->count();

        // El formulario del trabajo necesita los tipos de pago para su combo
        $tiposPago = TiposPagoEmpleado::query()
            ->where('estado', true)
            ->orderBy('nombre')
            ->get();

        return view(
            'procesos.procesos_orden.show',
            compact(
                'title',
                'breadcrumbs',
                'ordenTrabajo',
                'procesoOrden',
                'trabajosDataTable',
                'empleadosSelectorDataTable',
                'cantidadTrabajos',
                'tiposPago'
            )
        );
    }

    /**
     * El proceso de la URL tiene que ser de la orden de la URL.
     */
    private function validarProcesoPertenece(
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden
    ): void {
        abort_unless(
            (int) $procesoOrden->orden_trabajo_id === $ordenTrabajo->id,
            404
        );
    }

    public function create(OrdenesTrabajo $ordenTrabajo)
    {
        $title = 'Nuevo Proceso';

        $breadcrumbs = [
            [
                'label' => 'Órdenes de Trabajo',
                'url' => route('procesos.ordenes_trabajo.index'),
            ],
            [
                'label' => $ordenTrabajo->codigo,
                'url' => route(
                    'procesos.ordenes_trabajo.show',
                    $ordenTrabajo
                ),
            ],
            [
                'label' => 'Nuevo Proceso',
                'url' => '#',
            ],
        ];

        $etapas = Etapa::query()
            ->where('estado', true)
            ->orderBy('orden')
            ->get();

        return view(
            'procesos.procesos_orden.create',
            compact(
                'title',
                'breadcrumbs',
                'ordenTrabajo',
                'etapas'
            )
        );
    }

    public function store(
        Request $request,
        OrdenesTrabajo $ordenTrabajo
    ) {
        $validated = $request->validate([
            'etapa_id' => [
                'required',
                'integer',
                'exists:etapas,id'
            ],

            'fecha_inicio' => [
                'nullable',
                'date'
            ],

            'fecha_fin' => [
                'nullable',
                'date',
                'after_or_equal:fecha_inicio'
            ],

            'peso_entrada' => [
                'nullable',
                'numeric',
                'gte:0'
            ],

            'peso_salida' => [
                'nullable',
                'numeric',
                'gte:0'
            ],

            'observaciones' => [
                'nullable',
                'string'
            ],
        ]);

        $validated['orden_trabajo_id'] = $ordenTrabajo->id;
        $validated['codigo'] = $this->generarCodigo($ordenTrabajo);
        $validated['estado'] = 1;

        ProcesosOrden::create($validated);

        Alert::toast(
            'Proceso creado correctamente.'
        )->success()->flash();

        return redirect()->route(
            'procesos.ordenes_trabajo.show',
            $ordenTrabajo
        );
    }

    public function edit(
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden
    ) {
        abort_unless(
            $procesoOrden->orden_trabajo_id === $ordenTrabajo->id,
            404
        );

        $title = "Editar Proceso";

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            [
                'label' => 'Órdenes de Trabajo',
                'url' => route(
                    'procesos.ordenes_trabajo.index'
                )
            ],
            [
                'label' => $ordenTrabajo->codigo,
                'url' => route(
                    'procesos.ordenes_trabajo.show',
                    $ordenTrabajo
                )
            ],
            [
                'label' => 'Editar Proceso'
            ],
        ];

        $etapas = Etapa::query()
            ->where(function ($query) use ($procesoOrden) {

                $query
                    ->where('estado', true)
                    ->orWhere('id', $procesoOrden->etapa_id);

            })
            ->orderBy('orden')
            ->get();

        return view(
            'procesos.procesos_orden.edit',
            compact(
                'title',
                'breadcrumbs',
                'ordenTrabajo',
                'procesoOrden',
                'etapas',
            )
        );
    }

    public function update(
        Request $request,
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden
    ) {
        abort_unless(
            $procesoOrden->orden_trabajo_id === $ordenTrabajo->id,
            404
        );

        $validated = $request->validate([

            'etapa_id' => [
                'required',
                'integer',
                Rule::exists('etapas', 'id')
                    ->whereNull('deleted_at')
                    ->where('estado', true),
            ],

            'fecha_inicio' => [
                'nullable',
                'date',
            ],

            'fecha_fin' => [
                'nullable',
                'date',
                'after_or_equal:fecha_inicio',
            ],

            'peso_entrada' => [
                'nullable',
                'numeric',
                'gte:0',
            ],

            'peso_salida' => [
                'nullable',
                'numeric',
                'gte:0',
            ],

            'observaciones' => [
                'nullable',
                'string',
            ],

        ]);

        $procesoOrden->update($validated);

        Alert::toast(
            'Proceso actualizado correctamente.'
        )->success()->flash();

        return redirect()->route(
            'procesos.ordenes_trabajo.show',
            $ordenTrabajo
        );
    }

    public function destroy(
        OrdenesTrabajo $ordenTrabajo,
        ProcesosOrden $procesoOrden
    ) {
        abort_unless(
            $procesoOrden->orden_trabajo_id === $ordenTrabajo->id,
            404
        );

        $procesoOrden->delete();

        Alert::toast(
            'Proceso eliminado correctamente.'
        )->success()->flash();

        return redirect()->route(
            'procesos.ordenes_trabajo.show',
            $ordenTrabajo
        );
    }

    private function generarCodigo(
        OrdenesTrabajo $ordenTrabajo
    ): string {
        $ultimo = ProcesosOrden::withTrashed()
            ->where(
                'orden_trabajo_id',
                $ordenTrabajo->id
            )
            ->orderByDesc('id')
            ->first();

        $numero = 1;

        if ($ultimo) {
            $partes = explode('-', $ultimo->codigo);

            $numero = ((int) end($partes)) + 1;
        }

        return sprintf(
            'P-%03d',
            $numero
        );
    }

    public function data(OrdenesTrabajo $ordenTrabajo, ProcesosOrdenDataTable $dataTable)
    {
        $dataTable->setOrdenTrabajoId($ordenTrabajo->id);
        return $dataTable->ajax();
    }
}
