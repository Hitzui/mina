<?php

namespace App\DataTables;

use App\DataTables\Concerns\TablaResponsiva;
use App\Models\Liquidacione;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class LiquidacionesDataTable extends DataTable
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
            ->editColumn('fecha', fn (Liquidacione $liquidacion) => $liquidacion->fecha?->format('d/m/Y') ?? '—')
            ->editColumn('gramos_recuperados', fn (Liquidacione $liquidacion) => number_format((float) $liquidacion->gramos_recuperados, 4, '.', ','))
            ->addColumn('participacion', fn (Liquidacione $liquidacion) => number_format((float) $liquidacion->porcentaje_cliente, 4, '.', ',')
                .' % cliente / '.number_format((float) $liquidacion->porcentaje_empresa, 4, '.', ',').' % empresa')
            ->editColumn('gramos_cliente', fn (Liquidacione $liquidacion) => number_format((float) $liquidacion->gramos_cliente, 4, '.', ','))
            ->editColumn('gramos_empresa', fn (Liquidacione $liquidacion) => number_format((float) $liquidacion->gramos_empresa, 4, '.', ','))
            ->addColumn('action', fn (Liquidacione $liquidacion) => view(
                'procesos.ordenes_trabajo.liquidaciones._action',
                compact('liquidacion')
            )->render())
            ->rawColumns(['action'])
            ->setRowId('id');
    }

    public function query(Liquidacione $model): QueryBuilder
    {
        return $model->newQuery()
            ->deOrden($this->ordenTrabajoId)
            ->orderByDesc(Liquidacione::FECHA)
            ->orderByDesc(Liquidacione::ID);
    }

    public function html(): HtmlBuilder
    {
        return $this->ajustesComunes($this->builder())
            ->setTableId('liquidaciones-orden-table')
            ->columns($this->getColumns())
            ->minifiedAjax(route('procesos.ordenes_trabajo.liquidaciones.index', $this->ordenTrabajoId))
            ->orderBy(0, 'desc');
    }

    public function getColumns(): array
    {
        return [
            Column::make('fecha')->title('Fecha')->width(100),
            Column::make('gramos_recuperados')->title('Gramos liquidados')->addClass('text-end'),
            Column::make('participacion')->title('Acuerdo aplicado')->addClass(self::OCULTAR_EN_MOVIL),
            Column::make('gramos_cliente')->title('Gramos cliente')->addClass('text-end'),
            Column::make('gramos_empresa')->title('Gramos empresa')->addClass('text-end')->addClass(self::OCULTAR_EN_MOVIL),
            Column::computed('action')->title('Acciones')->exportable(false)->printable(false)->width(110)
                ->addClass('text-center')->addClass(self::COLUMNA_ACCIONES),
        ];
    }

    protected function filename(): string
    {
        return 'Liquidaciones_Orden_'.date('YmdHis');
    }
}
