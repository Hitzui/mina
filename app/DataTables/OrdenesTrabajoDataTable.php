<?php

namespace App\DataTables;

use App\DataTables\Concerns\TablaResponsiva;
use App\Models\OrdenesTrabajo;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class OrdenesTrabajoDataTable extends DataTable
{
    use TablaResponsiva;

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
                return $ordenTrabajo->nombreTitular();
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
                return $ordenTrabajo->estadoEtiqueta();
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
        $query = $model
            ->newQuery()
            ->with('cliente');

        $estado = request()->query('estado');
        if (is_scalar($estado) && (string) $estado !== ''
            && filter_var((string) $estado, FILTER_VALIDATE_INT) !== false) {
            $query->where('estado', (int) $estado);
        }

        return $query;
    }

    /**
     * Optional method if you want to use the HTML builder.
     */
    public function html(): HtmlBuilder
    {
        $builder = $this->ajustesComunes($this->builder());
        $ajaxUrl = route('procesos.ordenes_trabajo.index');

        // DataTables hace una segunda petición y no conserva por sí solo el
        // filtro de la página. En particular, el estado 0 (Cancelada) debe
        // viajar aunque sea un valor falsey en algunos lenguajes.
        if (request()->query->has('estado')) {
            $ajaxUrl .= '?' . http_build_query(['estado' => request()->query('estado')]);
        }

        return $builder
            ->setTableId('ordenes-trabajo-table')
            ->columns($this->getColumns())
            ->minifiedAjax($ajaxUrl)
            ->orderBy(2, 'desc');
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
                ->addClass('text-end')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('unidad_peso')
                ->title('Unidad')
                ->addClass('text-center')
                ->addClass(self::OCULTAR_HASTA_ESCRITORIO),

            Column::make('estado')
                ->title('Estado')
                ->addClass('text-center'),

            Column::computed('action')
                ->title('Acciones')
                ->exportable(false)
                ->printable(false)
                ->width(140)
                ->addClass('text-center')
                ->addClass(self::COLUMNA_ACCIONES),
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
