<?php

namespace App\DataTables;

use App\Models\TiposPagoEmpleado;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class TiposPagoEmpleadoDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return new EloquentDataTable($query)

            ->addColumn('action', 'configuracion.tipos_pago_empleado._action')

            ->editColumn(
                'descripcion',
                function (TiposPagoEmpleado $tipoPagoEmpleado) {
                    return $tipoPagoEmpleado->descripcion ?: '-';
                }
            )

            ->editColumn(
                'estado',
                function (TiposPagoEmpleado $tipoPagoEmpleado) {

                    if ($tipoPagoEmpleado->estado) {
                        return '
                            <span class="badge bg-success">
                                Activo
                            </span>
                        ';
                    }

                    return '
                        <span class="badge bg-secondary">
                            Inactivo
                        </span>
                    ';
                }
            )

            ->setRowId('id')

            ->rawColumns([
                'estado',
                'action',
            ]);
    }

    public function query(
        TiposPagoEmpleado $model
    ): QueryBuilder {
        return $model->newQuery();
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('tipos-pago-empleado-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy(0, 'asc')
            ->selectStyleSingle();
    }

    public function getColumns(): array
    {
        return [
            Column::make('nombre')
                ->title('Nombre'),

            Column::make('descripcion')
                ->title('Descripción'),

            Column::make('estado')
                ->title('Estado')
                ->addClass('text-center'),

            Column::computed('action')
                ->title('Acciones')
                ->exportable(false)
                ->printable(false)
                ->width(100)
                ->addClass('text-center'),
        ];
    }

    protected function filename(): string
    {
        return 'TiposPagoEmpleado_' . date('YmdHis');
    }
}
