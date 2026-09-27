<?php

namespace App\DataTables;

use App\DataTables\Concerns\TablaResponsiva;
use App\Models\ProcesoEquipo;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class ProcesoEquiposDataTable extends DataTable
{
    use TablaResponsiva;

    protected int $procesoOrdenId;

    protected ?int $ordenTrabajoId = null;

    /**
     * El uso cuelga del proceso, asi que la lista se acota al proceso
     * que se esta viendo.
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
            ->addColumn('equipo', fn($uso) => $uso->equipo?->nombre ?? '—')
            ->addColumn('codigo', fn($uso) => $uso->equipo?->codigo ?? '—')
            ->editColumn('fecha_inicio', fn($uso) => $uso->fecha_inicio?->format('d/m/Y H:i') ?? '—')
            ->addColumn('fecha_fin', fn($uso) => $uso->fecha_fin
                ? $uso->fecha_fin->format('d/m/Y H:i')
                : '<span class="badge bg-warning text-dark">Sigue asignado</span>')
            ->addColumn('dias', fn($uso) => rtrim(rtrim(number_format($uso->diasDeUso(), 2), '0'), '.'))
            ->addColumn('depreciacion_diaria', fn($uso) => number_format(
                (float) $uso->equipo?->depreciacionDiaria(),
                4
            ))
            // La foto guardada, no un recalculo: es el costo que se
            // cargo al proceso aunque despues cambie el valor del equipo
            ->editColumn('depreciacion_total', fn($uso) => number_format($uso->depreciacion_total, 2))
            ->editColumn('observaciones', fn($uso) => $uso->observaciones ?: '—')
            // Se pasa el modelo entero y no sus atributos sueltos: la
            // accion necesita el proceso para armar la url
            ->addColumn('action', fn($uso) => view(
                'procesos.procesos_orden.equipos._action',
                ['uso' => $uso]
            )->render())
            ->rawColumns(['action', 'fecha_fin'])
            ->setRowId('id');
    }

    public function query(ProcesoEquipo $model): QueryBuilder
    {
        return $model->newQuery()
            ->where(ProcesoEquipo::PROCESO_ORDEN_ID, $this->procesoOrdenId)
            ->with('equipo');
    }

    public function html(): HtmlBuilder
    {
        $url = route(
            'procesos.ordenes_trabajo.procesos.equipos.index',
            [
                'ordenTrabajo' => $this->ordenTrabajoId,
                'procesoOrden' => $this->procesoOrdenId,
            ]
        );

        $builder = $this->ajustesComunes($this->builder());

        return $builder
            ->setTableId('proceso-equipos-table')
            ->columns($this->getColumns())
            ->minifiedAjax($url)
            ->orderBy(0, 'asc');
    }

    public function getColumns(): array
    {
        return [
            Column::make('codigo')
                ->title('Código')
                ->width(110),

            Column::make('equipo')
                ->title('Equipo'),

            Column::make('fecha_inicio')
                ->title('Inicio')
                ->width(130),

            Column::make('fecha_fin')
                ->title('Fin')
                ->width(130)
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('dias')
                ->title('Días')
                ->width(80)
                ->addClass('text-end')
                ->addClass(self::OCULTAR_HASTA_ESCRITORIO),

            Column::make('depreciacion_diaria')
                ->title('Deprec./día')
                ->width(110)
                ->addClass('text-end')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('depreciacion_total')
                ->title('Depreciación')
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
        return 'EquiposDelProceso_' . date('YmdHis');
    }
}
