<?php

namespace App\DataTables;

use App\DataTables\Concerns\TablaResponsiva;
use App\Models\MovimientosInventario;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

/**
 * La materia prima consumida en un proceso.
 *
 * Solo las salidas: lo que entra al almacen no es un consumo de este
 * proceso, se registra en el kardex general.
 */
class MaterialesProcesoDataTable extends DataTable
{
    use TablaResponsiva;

    protected int $procesoOrdenId;

    protected ?int $ordenTrabajoId = null;

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
            ->editColumn('fecha', fn($m) => $m->fecha?->format('d/m/Y') ?? '—')
            ->addColumn('producto', fn($m) => e($m->producto?->nombre ?? 'Producto eliminado')
                . ' <span class="text-muted">('
                . e($m->producto?->unidad_medida ?? '') . ')</span>')
            ->editColumn('cantidad', fn($m) => rtrim(
                rtrim(number_format((float) $m->cantidad, 3), '0'),
                '.'
            ))
            ->editColumn('costo_unitario', fn($m) => number_format((float) $m->costo_unitario, 2))
            ->editColumn('costo_total_nio', fn($m) => $m->costo_total_nio === null
                ? '<span class="text-muted">sin equivalente</span>'
                : number_format((float) $m->costo_total_nio, 2))
            ->editColumn('observaciones', fn($m) => $m->observaciones ?: '—')
            ->addColumn('action', fn($m) => view(
                'procesos.procesos_orden.materiales._action',
                [
                    'movimiento' => $m,
                    'ordenTrabajoId' => $this->ordenTrabajoId,
                    'procesoOrdenId' => $this->procesoOrdenId,
                ]
            )->render())
            ->rawColumns(['producto', 'costo_total_nio', 'action'])
            ->setRowId('id');
    }

    public function query(MovimientosInventario $model): QueryBuilder
    {
        return $model->newQuery()
            ->deProceso($this->procesoOrdenId)
            ->where('tipo', MovimientosInventario::TIPO_SALIDA)
            ->with(['producto']);
    }

    public function html(): HtmlBuilder
    {
        $url = route(
            'procesos.ordenes_trabajo.procesos.materiales.index',
            [
                'ordenTrabajo' => $this->ordenTrabajoId,
                'procesoOrden' => $this->procesoOrdenId,
            ]
        );

        $builder = $this->ajustesComunes($this->builder());

        return $builder
            ->setTableId('materiales-proceso-table')
            ->columns($this->getColumns())
            ->minifiedAjax($url)
            ->orderBy(0, 'desc');
    }

    public function getColumns(): array
    {
        return [
            Column::make('fecha')
                ->title('Fecha')
                ->width(100),

            Column::make('producto')
                ->title('Material'),

            Column::make('cantidad')
                ->title('Cantidad')
                ->addClass('text-end fw-semibold'),

            Column::make('costo_unitario')
                ->title('Costo unitario')
                ->addClass('text-end')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('costo_total_nio')
                ->title('Total NIO')
                ->addClass('text-end fw-semibold'),

            Column::make('observaciones')
                ->title('Observaciones')
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
        return 'MateriaPrimaDelProceso_' . date('YmdHis');
    }
}
