<?php

namespace App\DataTables;

use App\DataTables\Concerns\TablaResponsiva;
use App\Models\Cliente;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class ClienteDataTable extends DataTable
{
    use TablaResponsiva;

    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder<Cliente> $query
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return new EloquentDataTable($query)

            ->addColumn('action', 'admin.clientes._actions')

            ->editColumn('estado', function (Cliente $cliente) {
                if ($cliente->estado) {
                    return '<span class="badge bg-success">Activo</span>';
                }

                return '<span class="badge bg-danger">Inactivo</span>';
            })

            ->rawColumns(['estado', 'action'])

            ->setRowId('id');
    }

    /**
     * Get the dataTable query source.
     *
     * @return QueryBuilder<Cliente>
     */
    public function query(Cliente $model): QueryBuilder
    {
        return $model->newQuery();
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        $builder = $this->ajustesComunes($this->builder());

        return $builder
            ->setTableId('cliente-table')
            ->addTableClass([' table-hover','table-bordered'])
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy(0);
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        return [
            Column::make('nombre')
                ->title('Nombre'),

            Column::make('telefono')
                ->title('Teléfono')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('direccion')
                ->title('Dirección')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::computed('estado')
                ->title('Estado')
                ->addClass('text-center'),

            Column::computed('action')
                ->title('Acciones')
                ->exportable(false)
                ->printable(false)
                ->width(100)
                ->addClass('text-center')
                ->addClass(self::COLUMNA_ACCIONES),
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'Clientes_' . date('YmdHis');
    }
}
