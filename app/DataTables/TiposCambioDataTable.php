<?php

namespace App\DataTables;

use App\DataTables\Concerns\TablaResponsiva;
use App\Models\TiposCambio;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

/**
 * El historico del tipo de cambio.
 *
 * Se ordena por fecha de mas reciente a mas antigua y no por id, que es lo
 * que haria el orden por defecto: este registro es una serie de fechas y lo
 * que se mira es el dia de hoy, no el ultimo que se escribio. Ademas se
 * ensegnan los dosUltimos dias, que es lo que se necesita para trabajar.
 */
class TiposCambioDataTable extends DataTable
{
    use TablaResponsiva;

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return new EloquentDataTable($query)
            ->addColumn('moneda', fn(TiposCambio $t) => $t->moneda
                ? e($t->moneda->nombre) . ' (' . e($t->moneda->simbolo ?: e($t->moneda->codigo)) . ')'
                : '—')
            ->addColumn('valor', fn(TiposCambio $t) => '<span class="font-monospace">' . number_format((float) $t->valor, 4) . '</span>')
            ->addColumn('fuente', fn(TiposCambio $t) => $t->fuente ?: '<span class="text-muted">—</span>')
            ->addColumn('observaciones', fn(TiposCambio $t) => $t->observaciones ?: '<span class="text-muted">—</span>')
            ->addColumn('action', fn(TiposCambio $t) => view(
                'configuracion.tipos_cambio._action',
                ['tipoCambio' => $t]
            )->render())
            ->rawColumns(['moneda', 'valor', 'fuente', 'observaciones', 'action'])
            ->setRowId('id');
    }

    public function query(TiposCambio $model): QueryBuilder
    {
        return $model->newQuery()
            ->with('moneda')
            ->orderByDesc('fecha')
            ->orderByDesc('id');
    }

    public function html(): HtmlBuilder
    {
        $builder = $this->ajustesComunes($this->builder());

        return $builder
            ->setTableId('tipos-cambio-table')
            ->columns($this->getColumns())
            ->minifiedAjax(route('configuracion.tipos_cambio.index'))
            ->orderBy(0, 'desc');
    }

    public function getColumns(): array
    {
        return [
            Column::make('fecha')
                ->title('Fecha')
                ->addClass('font-monospace'),

            Column::make('moneda')
                ->title('Moneda')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('valor')
                ->title('Valor')
                ->addClass('text-end'),

            Column::make('fuente')
                ->title('Fuente')
                ->addClass(self::OCULTAR_HASTA_ESCRITORIO),

            Column::make('observaciones')
                ->title('Observaciones')
                ->addClass(self::OCULTAR_HASTA_ESCRITORIO),

            Column::computed('action')
                ->title('Acciones')
                ->exportable(false)
                ->printable(false)
                ->width(90)
                ->addClass('text-center')
                ->addClass(self::COLUMNA_ACCIONES),
        ];
    }

    protected function filename(): string
    {
        return 'TiposCambio_' . date('YmdHis');
    }
}
