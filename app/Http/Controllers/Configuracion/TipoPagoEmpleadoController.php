<?php

namespace App\Http\Controllers\Configuracion;

use App\DataTables\TiposPagoEmpleadoDataTable;
use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Http\Controllers\Controller;
use App\Models\TiposPagoEmpleado;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class TipoPagoEmpleadoController extends Controller
{
    use AuthorizesModule;

    public function __construct()
    {
        $this->authorizeModule('configuracion.tipos_pago_empleado');
    }

    public function index(TiposPagoEmpleadoDataTable $dataTable)
    {
        $title = 'Tipos de Pago de Empleado';

        $breadcrumbs = [
            [
                'label' => 'Configuración',
                'url' => '#',
            ],
            [
                'label' => 'Tipos de Pago de Empleado',
                'url' => '#',
            ],
        ];

        return $dataTable->render(
            'configuracion.tipos_pago_empleado.index',
            compact(
                'title',
                'breadcrumbs'
            )
        );
    }

    public function create()
    {
        $title = 'Nuevo Tipo de Pago';

        $breadcrumbs = [
            [
                'label' => 'Configuración',
                'url' => '#',
            ],
            [
                'label' => 'Tipos de Pago de Empleado',
                'url' => route(
                    'configuracion.tipos_pago_empleado.index'
                ),
            ],
            [
                'label' => 'Nuevo Tipo de Pago',
                'url' => '#',
            ],
        ];

        return view(
            'configuracion.tipos_pago_empleado.create',
            compact(
                'title',
                'breadcrumbs'
            )
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:50',
            ],
            'descripcion' => [
                'nullable',
                'string',
                'max:255',
            ],
            'estado' => [
                'required',
                'boolean',
            ],
        ]);

        $tipoPago = TiposPagoEmpleado::create($validated);

        Alert::toast(
            'Tipo de pago creado correctamente.'
        )->success()->flash();

        return redirect()->route(
            'configuracion.tipos_pago_empleado.show', $tipoPago->id
        );
    }

    public function show(TiposPagoEmpleado $tipoPagoEmpleado)
    {
        $title = 'Tipo de Pago de Empleado';

        $breadcrumbs = [
            [
                'label' => 'Configuración',
                'url' => '#',
            ],
            [
                'label' => 'Tipos de Pago de Empleado',
                'url' => route(
                    'configuracion.tipos_pago_empleado.index'
                ),
            ],
            [
                'label' => $tipoPagoEmpleado->nombre,
                'url' => '#',
            ],
        ];

        return view(
            'configuracion.tipos_pago_empleado.show',
            compact(
                'title',
                'breadcrumbs',
                'tipoPagoEmpleado'
            )
        );
    }

    public function edit(TiposPagoEmpleado $tipoPagoEmpleado)
    {
        $title = 'Editar Tipo de Pago';

        $breadcrumbs = [
            [
                'label' => 'Configuración',
                'url' => '#',
            ],
            [
                'label' => 'Tipos de Pago de Empleado',
                'url' => route(
                    'configuracion.tipos_pago_empleado.index'
                ),
            ],
            [
                'label' => $tipoPagoEmpleado->nombre,
                'url' => route(
                    'configuracion.tipos_pago_empleado.show',
                    $tipoPagoEmpleado
                ),
            ],
            [
                'label' => 'Editar',
                'url' => '#',
            ],
        ];

        return view(
            'configuracion.tipos_pago_empleado.edit',
            compact(
                'title',
                'breadcrumbs',
                'tipoPagoEmpleado'
            )
        );
    }

    public function update(
        Request $request,
        TiposPagoEmpleado $tipoPagoEmpleado
    ) {
        $validated = $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:50',
            ],
            'descripcion' => [
                'nullable',
                'string',
                'max:255',
            ],
            'estado' => [
                'required',
                'boolean',
            ],
        ]);

        $tipoPagoEmpleado->update($validated);

        Alert::toast(
            'Tipo de pago actualizado correctamente.'
        )->success()->flash();

        return redirect()->route(
            'configuracion.tipos_pago_empleado.show',
            $tipoPagoEmpleado
        );
    }

    public function destroy(TiposPagoEmpleado $tipoPagoEmpleado)
    {
        $tipoPagoEmpleado->delete();

        Alert::toast(
            'Tipo de pago eliminado correctamente.'
        )->success()->flash();

        return redirect()->route(
            'configuracion.tipos_pago_empleado.index'
        );
    }
}
