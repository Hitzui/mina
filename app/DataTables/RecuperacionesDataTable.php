<?php

namespace App\DataTables;

use App\DataTables\Concerns\TablaResponsiva;
use App\Models\OrdenesTrabajo;
use App\Models\Recuperaciones;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

/**
 * El oro que ha salido del taller, orden a orden.
 *
 * Se ordena por fecha de mas reciente a mas antigua y no por id, que es lo
 * que haria el orden por defecto: esto es un historial de hechos —cuantos
 * gramos salieron y cuando— y lo que se mira es lo ultimo, no lo primero que
 * se escribio.
 *
 * La columna de la orden lleva su estado, y no por adorno. Una recuperacion
 * cuelga de una orden, y hay una regla que dice que una orden cerrada no
 * admite recuperaciones nuevas pero si admite corregirlas. Ver el estado al
 * lado de cada fila es lo que hace que esa regla se entienda sin tener que
 * abrir la orden: si la de arriba esta finalizada, esa recuperacion se puede
 * tocar pero no se pueden anadir mas debajo.
 */
class RecuperacionesDataTable extends DataTable
{
    use TablaResponsiva;

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return new EloquentDataTable($query)
            ->addColumn('orden', fn(Recuperaciones $r) => $r->orden_trabajo
                ? '<span class="font-monospace">' . e($r->orden_trabajo->codigo) . '</span>'
                    . ' <span class="text-muted small">· ' . e($r->orden_trabajo->cliente?->nombre ?? 'sin cliente') . '</span>'
                : '<span class="text-muted">—</span>')
            ->addColumn('estado_orden', fn(Recuperaciones $r) => $r->orden_trabajo
                ? $r->orden_trabajo->estadoEtiqueta()
                : '<span class="text-muted">—</span>')
            ->addColumn('gramos', fn(Recuperaciones $r) => '<span class="font-monospace">'
                . number_format((float) $r->gramos, 4) . '</span>')
            ->addColumn('pureza', fn(Recuperaciones $r) => $r->purezaEnPorcentaje() === null
                ? '<span class="text-muted" title="No se midió la pureza esa vez.">sin medir</span>'
                : '<span class="font-monospace">' . number_format($r->purezaEnPorcentaje(), 2) . ' %</span>')
            ->addColumn('observaciones', fn(Recuperaciones $r) => $r->observaciones ?: '<span class="text-muted">—</span>')
            ->addColumn('action', fn(Recuperaciones $r) => view(
                'procesos.recuperaciones._action',
                ['recuperacion' => $r]
            )->render())
            ->rawColumns(['orden', 'estado_orden', 'gramos', 'pureza', 'observaciones', 'action'])
            ->setRowId('id');
    }

    public function query(Recuperaciones $model): QueryBuilder
    {
        return $model->newQuery()
            ->with(['orden_trabajo.cliente'])
            ->orderByDesc('fecha')
            ->orderByDesc('id');
    }

    public function html(): HtmlBuilder
    {
        $builder = $this->ajustesComunes($this->builder());

        return $builder
            ->setTableId('recuperaciones-table')
            ->columns($this->getColumns())
            ->minifiedAjax(route('procesos.recuperaciones.index'))
            ->orderBy(0, 'desc');
    }

    public function getColumns(): array
    {
        return [
            Column::make('fecha')
                ->title('Fecha')
                ->addClass('font-monospace'),

            Column::make('orden')
                ->title('Orden de trabajo'),

            Column::make('estado_orden')
                ->title('Estado de la orden')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('gramos')
                ->title('Gramos')
                ->addClass('text-end'),

            Column::make('pureza')
                ->title('Pureza')
                ->addClass('text-end')
                ->addClass(self::OCULTAR_EN_MOVIL),

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
        return 'Recuperaciones_' . date('YmdHis');
    }
}
