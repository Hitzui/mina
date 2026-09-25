<?php

namespace App\DataTables;

use App\Models\Etapa;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class EtapaDataTable extends DataTable
{
    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder<Etapa> $query
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return new EloquentDataTable($query)
            ->addColumn('action', 'admin.etapas.action')
            ->editColumn('estado', function (Etapa $etapa) {
                return $etapa->estado
                    ? '<span class="badge bg-success">Activo</span>'
                    : '<span class="badge bg-danger">Inactivo</span>';
            })
            ->editColumn('descripcion', function (Etapa $etapa) {
                return $etapa->descripcion ?: '-';
            })
            ->setRowId('id')
            ->rawColumns(['estado', 'action']);
    }

    /**
     * Get the query source of dataTable.
     *
     * @return QueryBuilder<Etapa>
     */
    public function query(Etapa $model): QueryBuilder
    {
        return $model->newQuery();
    }

    /**
     * Optional method if you want to use the HTML builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('etapa-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy(2, 'asc')
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
            Column::make('id')
                ->title('ID')
                ->width(60),

            Column::make('nombre')
                ->title('Nombre'),

            Column::make('descripcion')
                ->title('Descripción'),

            Column::make('orden')
                ->title('Orden')
                ->width(80)
                ->addClass('text-center'),

            Column::make('estado')
                ->title('Estado')
                ->width(100)
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
        return 'Etapas_' . date('YmdHis');
    }
}
