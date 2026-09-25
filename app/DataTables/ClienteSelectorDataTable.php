<?php

namespace App\DataTables;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class ClienteSelectorDataTable extends DataTable
{
    /**
     * Construye el DataTable.
     *
     * @param QueryBuilder<Cliente> $query
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('seleccionar', function (Cliente $cliente) {
                return '
                    <button
                        type="button"
                        class="btn btn-sm btn-primary btn-seleccionar-cliente"
                        data-id="' . $cliente->id . '"
                        data-nombre="' . e($cliente->nombre) . '"
                    >
                        <i class="fa-solid fa-check"></i>
                        Seleccionar
                    </button>
                ';
            })
            ->rawColumns(['seleccionar'])
            ->setRowId('id');
    }

    /**
     * Fuente de datos.
     *
     * Solo clientes activos.
     *
     * @return QueryBuilder<Cliente>
     */
    public function query(Cliente $model): QueryBuilder
    {
        return $model
            ->newQuery()
            ->where('estado', true);
    }

    /**
     * Configuración HTML del DataTable.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('cliente-selector-table')
            ->addTableClass(['table-hover', 'table-bordered'])
            ->columns($this->getColumns())
            ->selectStyleSingle();
    }

    /**
     * Columnas del DataTable.
     */
    public function getColumns(): array
    {
        return [
            Column::make('nombre')
                ->title('Cliente'),

            Column::make('telefono')
                ->title('Teléfono'),

            Column::make('direccion')
                ->title('Dirección'),

            Column::computed('seleccionar')
                ->title('Acción')
                ->exportable(false)
                ->printable(false)
                ->width(120)
                ->addClass('text-center'),
        ];
    }

    /**
     * Nombre del archivo de exportación.
     *
     * No se utiliza en este DataTable, pero Yajra lo requiere.
     */
    protected function filename(): string
    {
        return 'ClienteSelector_' . date('YmdHis');
    }
}
