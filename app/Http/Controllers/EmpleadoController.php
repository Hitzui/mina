<?php

namespace App\Http\Controllers;

use App\DataTables\EmpleadosDataTable;
use App\DataTables\EmpleadosPagosDataTable;
use App\DataTables\EmpleadosSelectorDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\Empleado;
use App\Models\TiposEmpleado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RealRashid\SweetAlert\Facades\Alert;

class EmpleadoController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        $this->authorizeModule('empleados');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(EmpleadosDataTable $dataTable)
    {
        $title = 'Listado de Empleados';

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            ['label' => 'Empleados']
        ];

        return $dataTable->render(
            'admin.empleados.index',
            compact('title', 'breadcrumbs')
        );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $title = 'Ingresar Empleado';

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            [
                'label' => 'Empleados',
                'url' => route('admin.empleados.index')
            ],
            ['label' => 'Ingresar Empleado']
        ];

        $tiposEmpleado = TiposEmpleado::query()
            ->where('estado', true)
            ->orderBy('nombre')
            ->get();

        return view(
            'admin.empleados.create',
            compact(
                'title',
                'breadcrumbs',
                'tiposEmpleado'
            )
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:150',
            ],

            'telefono' => [
                'nullable',
                'string',
                'max:30',
            ],

            'tipo_empleado_id' => [
                'required',
                'integer',
                'exists:tipos_empleado,id',
            ],

            'fecha_ingreso' => [
                'nullable',
                'date',
            ],

            'estado' => [
                'required',
                'boolean',
            ],

            'observaciones' => [
                'nullable',
                'string',
            ],
        ]);

        $validated['codigo'] = $this->generarCodigo();

        Empleado::create($validated);

        Alert::toast(
            'Empleado ingresado correctamente.',
            'success'
        )->flash();

        return redirect()->route('admin.empleados.index');
    }

    /**
     * Display the specified resource.
     */
    /**
     * Display the specified resource.
     */
    public function show(
        Empleado $empleado,
        EmpleadosPagosDataTable $empleadosPagosDataTable
    ) {
        $title = 'Información del Empleado';

        $breadcrumbs = [
            [
                'label' => 'Dashboard',
                'url' => route('home')
            ],
            [
                'label' => 'Empleados',
                'url' => route('admin.empleados.index')
            ],
            [
                'label' => 'Información del Empleado'
            ]
        ];

        $empleado->load('tipo_empleado');

        /*
         * Configurar el DataTable para este empleado.
         */
        $empleadosPagosDataTable->setEmpleadoId(
            $empleado->id
        );

        return view(
            'admin.empleados.show',
            compact(
                'title',
                'breadcrumbs',
                'empleado',
                'empleadosPagosDataTable'
            )
        );
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Empleado $empleado)
    {
        $title = 'Editar Empleado';

        $breadcrumbs = [
            ['label' => 'Dashboard', 'url' => route('home')],
            [
                'label' => 'Empleados',
                'url' => route('admin.empleados.index')
            ],
            ['label' => 'Informacion del Empleado ' . $empleado->codigo, 'url' => route('admin.empleados.show', $empleado->id)],
            ['label' => 'Editar Empleado']
        ];

        $tiposEmpleado = TiposEmpleado::query()
            ->where(function ($query) use ($empleado) {
                $query->where('estado', true)
                    ->orWhere('id', $empleado->tipo_empleado_id);
            })
            ->orderBy('nombre')
            ->get();

        return view(
            'admin.empleados.edit',
            compact(
                'title',
                'breadcrumbs',
                'empleado',
                'tiposEmpleado'
            )
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Empleado $empleado)
    {
        $validated = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:150',
            ],

            'telefono' => [
                'nullable',
                'string',
                'max:30',
            ],

            'tipo_empleado_id' => [
                'required',
                'integer',
                'exists:tipos_empleado,id',
            ],

            'fecha_ingreso' => [
                'nullable',
                'date',
            ],

            'estado' => [
                'required',
                'boolean',
            ],

            'observaciones' => [
                'nullable',
                'string',
            ],
        ]);

        $empleado->update($validated);

        Alert::toast(
            'Empleado actualizado correctamente.',
            'success'
        )->flash();

        return redirect()->route('admin.empleados.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Empleado $empleado)
    {
        $empleado->delete();

        Alert::toast(
            'Empleado eliminado correctamente.',
            'success'
        )->flash();

        return redirect()->route('admin.empleados.index');
    }

    /**
     * Generate the employee code.
     */
    private function generarCodigo(): string
    {
        $prefijo = config('services.empleado.prefijo');
        $digitos = (int)config('services.empleado.digitos');

        $ultimoCodigo = Empleado::withTrashed()
            ->where('codigo', 'like', $prefijo . '-%')
            ->orderByDesc('id')
            ->value('codigo');

        if (!$ultimoCodigo) {
            $numero = 1;
        } else {
            $numero = (int)substr(
                $ultimoCodigo,
                strlen($prefijo) + 1
            );

            $numero++;
        }

        return $prefijo . '-' . str_pad(
                $numero,
                $digitos,
                '0',
                STR_PAD_LEFT
            );
    }

    public function selectorData(EmpleadosSelectorDataTable $dataTable)
    {
        return $dataTable->ajax();
    }
}
