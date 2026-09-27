<?php

namespace App\DataTables;

use App\DataTables\Concerns\TablaResponsiva;
use App\Models\CategoriasCosto;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class CategoriasCostoDataTable extends DataTable
{
    use TablaResponsiva;

    /**
     * Build DataTable class.
     *
     * @param QueryBuilder<CategoriasCosto> $query
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return new EloquentDataTable($query)
            ->addColumn('action', 'configuracion.categorias_costos._action')
            ->editColumn('descripcion', function (CategoriasCosto $categoriaCosto) {
                return $categoriaCosto->descripcion ?: '-';
            })
            ->editColumn('estado', function (CategoriasCosto $categoriaCosto) {

                if ($categoriaCosto->estado) {
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
            })
            ->setRowId('id')
            ->rawColumns([
                'estado',
                'action',
            ]);
    }

    /**
     * Get the query source of dataTable.
     *
     * @return QueryBuilder<CategoriasCosto>
     */
    public function query(CategoriasCosto $model): QueryBuilder
    {
        return $model->newQuery();
    }

    /**
     * Optional method if you want to use the HTML builder.
     */
    public function html(): HtmlBuilder
    {
        $builder = $this->ajustesComunes($this->builder());

        return $builder
            ->setTableId('categorias-costos-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy(0, 'asc');
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        return [
            Column::make('nombre')
                ->title('Nombre'),

            Column::make('descripcion')
                ->title('Descripción')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('estado')
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
     * Get filename for export.
     */
    protected function filename(): string
    {
        return 'CategoriasCosto_' . date('YmdHis');
    }
}
