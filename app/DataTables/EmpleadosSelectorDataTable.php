<?php

namespace App\DataTables;

use App\Models\Empleado;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class EmpleadosSelectorDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return new EloquentDataTable($query)
            ->addColumn('seleccionar', function (Empleado $empleado) {
                return '<input type="radio" name="empleado_selector" class="form-check-input empleado-selector" value="' . $empleado->id . '" data-nombre="' . e($empleado->nombre) . '">';
            })
            ->addColumn('tipo_empleado', function (Empleado $empleado) {
                return $empleado->tipo_empleado?->nombre ?? '—';
            })
            ->editColumn('estado', function (Empleado $empleado) {
                return $empleado->estado
                    ? '<span class="badge bg-success">Activo</span>'
                    : '<span class="badge bg-secondary">Inactivo</span>';
            })
            ->setRowId('id')
            ->rawColumns(['seleccionar', 'estado']);
    }

    public function query(Empleado $model): QueryBuilder
    {
        return $model->newQuery()
            ->with('tipo_empleado')
            ->where('estado', true);
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('empleados-selector-table')
            ->columns($this->getColumns())
            ->minifiedAjax(route('admin.empleados.selector.data'))
            ->orderBy(2, 'asc')
            ->responsive(true)
            ->autoWidth(false)
            ->selectStyleSingle();
    }

    public function getColumns(): array
    {
        return [
            Column::computed('seleccionar')
                ->title('')
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->width(45)
                ->addClass('text-center'),

            Column::make('codigo')
                ->title('Código'),

            Column::make('nombre')
                ->title('Empleado'),

            Column::make('tipo_empleado')
                ->title('Tipo de empleado'),

            Column::make('telefono')
                ->title('Teléfono'),

            Column::make('estado')
                ->title('Estado')
                ->addClass('text-center'),
        ];
    }

    protected function filename(): string
    {
        return 'Empleados_Selector_' . date('YmdHis');
    }
}
