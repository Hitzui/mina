<?php

namespace App\DataTables;

use App\DataTables\Concerns\TablaResponsiva;
use App\Models\MovimientosCosto;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

/**
 * Los costos de la orden que no son de ningun proceso.
 *
 * Solo los que tienen proceso_orden_id en NULL. Los que si son de un
 * proceso se ven en la pantalla de ese proceso, y no aqui: un mismo costo
 * no debe aparecer en dos desgloses, porque al sumar los dos sale doble.
 */
class CostosOrdenDataTable extends DataTable
{
    use TablaResponsiva;

    protected int $ordenTrabajoId;

    public function setOrdenTrabajoId(int $ordenTrabajoId): self
    {
        $this->ordenTrabajoId = $ordenTrabajoId;

        return $this;
    }

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return new EloquentDataTable($query)
            ->addColumn('categoria', fn($costo) => $costo->categoria_costo?->nombre ?? '—')
            ->editColumn('fecha', fn($costo) => $costo->fecha?->format('d/m/Y') ?? '—')
            ->editColumn('descripcion', fn($costo) => $costo->descripcion ?: '—')
            ->editColumn('cantidad', fn($costo) => rtrim(rtrim(number_format($costo->cantidad, 3), '0'), '.'))
            ->editColumn('costo_unitario', fn($costo) => number_format($costo->costo_unitario, 2))
            ->addColumn('moneda', fn($costo) => $costo->moneda?->codigo ?? '—')
            ->editColumn('costo_total', fn($costo) => number_format($costo->costo_total, 2))
            ->editColumn('costo_total_nio', fn($costo) => $costo->costo_total_nio === null
                ? '<span class="text-muted">sin tipo de cambio</span>'
                : number_format($costo->costo_total_nio, 2))
            ->addColumn('action', fn($costo) => view(
                'procesos.ordenes_trabajo.costos._action',
                ['costo' => $costo]
            )->render())
            ->rawColumns(['action', 'costo_total_nio'])
            ->setRowId('id');
    }

    public function query(MovimientosCosto $model): QueryBuilder
    {
        return $model->newQuery()
            ->generalesDe($this->ordenTrabajoId)
            ->with(['categoria_costo', 'moneda']);
    }

    public function html(): HtmlBuilder
    {
        $url = route(
            'procesos.ordenes_trabajo.costos.index',
            ['ordenTrabajo' => $this->ordenTrabajoId]
        );

        $builder = $this->ajustesComunes($this->builder());

        return $builder
            ->setTableId('costos-orden-table')
            ->columns($this->getColumns())
            ->minifiedAjax($url)
            ->orderBy(1, 'desc');
    }

    public function getColumns(): array
    {
        return [
            Column::make('categoria')
                ->title('Categoría'),

            Column::make('fecha')
                ->title('Fecha')
                ->width(100),

            Column::make('descripcion')
                ->title('Descripción')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('cantidad')
                ->title('Cantidad')
                ->width(90)
                ->addClass('text-end')
                ->addClass(self::OCULTAR_HASTA_ESCRITORIO),

            Column::make('costo_unitario')
                ->title('Costo unitario')
                ->addClass('text-end')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('moneda')
                ->title('Moneda')
                ->width(80)
                ->addClass('text-center')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('costo_total')
                ->title('Total')
                ->addClass('text-end fw-semibold'),

            Column::make('costo_total_nio')
                ->title('Total NIO')
                ->addClass('text-end')
                ->addClass(self::OCULTAR_EN_MOVIL),

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
        return 'CostosGeneralesDeOrden_' . date('YmdHis');
    }
}
