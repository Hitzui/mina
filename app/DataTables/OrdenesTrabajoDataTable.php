<?php

namespace App\DataTables;

use App\Models\OrdenesTrabajo;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class OrdenesTrabajoDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder<OrdenesTrabajo> $query
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return new EloquentDataTable($query)
            ->addColumn('action', 'procesos.ordenes_trabajo._action')
            ->addColumn('cliente', function (OrdenesTrabajo $ordenTrabajo) {
                return $ordenTrabajo->cliente?->nombre ?? '-';
            })
            ->editColumn('fecha', function (OrdenesTrabajo $ordenTrabajo) {
                return $ordenTrabajo->fecha?->format('d/m/Y') ?? '-';
            })
            ->editColumn('peso_mineral', function (OrdenesTrabajo $ordenTrabajo) {
                return number_format(
                    $ordenTrabajo->peso_mineral,
                    2,
                    '.',
                    ','
                );
            })
            ->editColumn('estado', function (OrdenesTrabajo $ordenTrabajo) {
                return match ($ordenTrabajo->estado) {
                    1 => '<span class="badge bg-primary">Pendiente</span>',
                    2 => '<span class="badge bg-warning">En proceso</span>',
                    3 => '<span class="badge bg-success">Finalizada</span>',
                    4 => '<span class="badge bg-danger">Cancelada</span>',
                    default => '<span class="badge bg-secondary">Desconocido</span>',
                };
            })
            ->setRowId('id')
            ->rawColumns(['estado', 'action']);
    }

    /**
     * Get the dataTable query source.
     *
     * @return QueryBuilder<OrdenesTrabajo>
     */
    public function query(OrdenesTrabajo $model): QueryBuilder
    {
        return $model
            ->newQuery()
            ->with('cliente');
    }

    /**
     * Optional method if you want to use the HTML builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('ordenes-trabajo-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy(2, 'desc')
            ->selectStyleSingle()
            ->buttons([
                Button::make('excel'),
                Button::make('csv'),
                Button::make('pdf'),
                Button::make('print'),
                Button::make('reset'),
                Button::make('reload'),
            ]);
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        return [
            Column::make('codigo')
                ->title('Código'),

            Column::make('cliente')
                ->title('Cliente'),

            Column::make('fecha')
                ->title('Fecha')
                ->addClass('text-center'),

            Column::make('peso_mineral')
                ->title('Peso mineral')
                ->addClass('text-end'),

            Column::make('unidad_peso')
                ->title('Unidad')
                ->addClass('text-center'),

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

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'OrdenesTrabajo_' . date('YmdHis');
    }
}
