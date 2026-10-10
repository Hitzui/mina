<?php

namespace App\DataTables;

use App\DataTables\Concerns\TablaResponsiva;
use App\Models\Produccione;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class ProduccionesDataTable extends DataTable
{
    use TablaResponsiva;

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return new EloquentDataTable($query)
            ->addColumn('orden', fn (Produccione $produccion) => $produccion->orden_trabajo
                ? '<span class="font-monospace">'.e($produccion->orden_trabajo->codigo).'</span>'
                    .' <span class="text-muted small">· '.e($produccion->orden_trabajo->cliente?->nombre ?? 'sin cliente').'</span>'
                : '<span class="text-muted">—</span>')
            ->addColumn('estado_orden', fn (Produccione $produccion) => $produccion->orden_trabajo
                ? $produccion->orden_trabajo->estadoEtiqueta()
                : '<span class="text-muted">—</span>')
            ->addColumn('proceso', fn (Produccione $produccion) => $produccion->proceso_orden
                ? '<span class="font-monospace">'.e($produccion->proceso_orden->codigo).'</span>'
                    .' <span class="text-muted small">· '.e($produccion->proceso_orden->etapa?->nombre ?? '').'</span>'
                : '<span class="text-muted">Orden completa</span>')
            ->addColumn('tipo', fn (Produccione $produccion) => e($produccion->tipo_produccion?->nombre ?? '—'))
            ->addColumn('cantidad_formateada', fn (Produccione $produccion) => '<span class="font-monospace">'
                .number_format((float) $produccion->cantidad, 4, '.', ',').'</span> '
                .e($produccion->unidad_medida))
            ->addColumn('descripcion', fn (Produccione $produccion) => $produccion->descripcion
                ? e($produccion->descripcion)
                : '<span class="text-muted">—</span>')
            ->addColumn('action', fn (Produccione $produccion) => view(
                'procesos.producciones._action',
                ['produccion' => $produccion]
            )->render())
            ->rawColumns(['orden', 'estado_orden', 'proceso', 'cantidad_formateada', 'descripcion', 'action'])
            ->setRowId('id');
    }

    public function query(Produccione $model): QueryBuilder
    {
        return $model->newQuery()
            ->with(['orden_trabajo.cliente', 'proceso_orden.etapa', 'tipo_produccion'])
            ->orderByDesc(Produccione::FECHA)
            ->orderByDesc(Produccione::ID);
    }

    public function html(): HtmlBuilder
    {
        return $this->ajustesComunes($this->builder())
            ->setTableId('producciones-table')
            ->columns($this->getColumns())
            ->minifiedAjax(route('procesos.producciones.index'))
            ->orderBy(0, 'desc');
    }

    public function getColumns(): array
    {
        return [
            Column::make('fecha')->title('Fecha')->addClass('font-monospace'),
            Column::make('orden')->title('Orden de trabajo'),
            Column::make('estado_orden')->title('Estado OT')->addClass(self::OCULTAR_EN_MOVIL),
            Column::make('proceso')->title('Proceso')->addClass(self::OCULTAR_HASTA_ESCRITORIO),
            Column::make('tipo')->title('Tipo de producción'),
            Column::make('cantidad_formateada')->title('Cantidad')->addClass('text-end'),
            Column::make('descripcion')->title('Descripción')->addClass(self::OCULTAR_EN_MOVIL),
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
        return 'Producciones_'.date('YmdHis');
    }
}
