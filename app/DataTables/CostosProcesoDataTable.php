<?php

namespace App\DataTables;

use App\Models\MovimientosCosto;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class CostosProcesoDataTable extends DataTable
{
    protected int $procesoOrdenId;

    protected ?int $ordenTrabajoId = null;

    /**
     * Los costos cuelgan del proceso, asi que la lista se acota al
     * proceso que se esta viendo.
     */
    public function setProcesoOrdenId(int $procesoOrdenId): self
    {
        $this->procesoOrdenId = $procesoOrdenId;

        return $this;
    }

    /**
     * La orden solo hace falta para armar la url del ajax, que lleva el
     * proceso: se deduce de el, asi que se recibe aparte.
     */
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
            ->editColumn('observaciones', fn($costo) => $costo->observaciones ?: '—')
            // Se pasa el modelo entero y no sus atributos sueltos: la
            // accion necesita el proceso para armar la url
            ->addColumn('action', fn($costo) => view(
                'procesos.procesos_orden.costos._action',
                ['costo' => $costo]
            )->render())
            ->rawColumns(['action', 'costo_total_nio'])
            ->setRowId('id');
    }

    public function query(MovimientosCosto $model): QueryBuilder
    {
        return $model->newQuery()
            ->deProceso($this->procesoOrdenId)
            ->with(['categoria_costo', 'moneda', 'orden_trabajo']);
    }

    public function html(): HtmlBuilder
    {
        $url = route(
            'procesos.ordenes_trabajo.procesos.costos.index',
            [
                'ordenTrabajo' => $this->ordenTrabajoId,
                'procesoOrden' => $this->procesoOrdenId,
            ]
        );

        return $this->builder()
            ->setTableId('costos-proceso-table')
            ->columns($this->getColumns())
            ->minifiedAjax($url)
            ->orderBy(0, 'asc')
            ->responsive(true)
            ->autoWidth(false)
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

    public function getColumns(): array
    {
        return [
            Column::make('categoria')
                ->title('Categoría'),

            Column::make('fecha')
                ->title('Fecha')
                ->width(100),

            Column::make('descripcion')
                ->title('Descripción'),

            Column::make('cantidad')
                ->title('Cantidad')
                ->width(90)
                ->addClass('text-end'),

            Column::make('costo_unitario')
                ->title('Costo unitario')
                ->addClass('text-end'),

            Column::make('moneda')
                ->title('Moneda')
                ->width(80)
                ->addClass('text-center'),

            Column::make('costo_total')
                ->title('Total')
                ->addClass('text-end fw-semibold'),

            Column::make('costo_total_nio')
                ->title('Total NIO')
                ->addClass('text-end'),

            Column::computed('action')
                ->title('Acciones')
                ->exportable(false)
                ->printable(false)
                ->width(90)
                ->addClass('text-center'),
        ];
    }

    protected function filename(): string
    {
        return 'CostosDelProceso_' . date('YmdHis');
    }
}
