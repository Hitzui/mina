<?php

namespace App\DataTables;

use App\Models\TrabajosEmpleado;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class TrabajosEmpleadosDataTable extends DataTable
{
    protected int $procesoOrdenId;

    protected ?int $ordenTrabajoId = null;

    /**
     * Los trabajos cuelgan del proceso, asi que la lista se acota al
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
            ->addColumn('empleado', fn($trabajo) => $trabajo->empleado?->nombre ?? '—')
            ->addColumn('tipo_pago', fn($trabajo) => $trabajo->tipo_pago?->nombre ?? '—')
            ->addColumn('moneda', fn($trabajo) => $trabajo->moneda?->codigo ?? '—')
            ->editColumn('fecha', fn($trabajo) => $trabajo->fecha?->format('d/m/Y') ?? '—')
            ->editColumn('cantidad', fn($trabajo) => number_format($trabajo->cantidad, 2))
            ->editColumn('tarifa', fn($trabajo) => number_format($trabajo->tarifa, 2))
            ->editColumn('total', fn($trabajo) => number_format($trabajo->total, 2))
            // Se pasa el modelo entero y no sus atributos sueltos: la
            // accion necesita el proceso para armar la url
            ->addColumn('action', fn($trabajo) => view(
                'procesos.ordenes_trabajo.trabajos_empleados._action',
                ['trabajo' => $trabajo]
            )->render())
            ->rawColumns(['action'])
            ->setRowId('id');
    }

    public function query(TrabajosEmpleado $model): QueryBuilder
    {
        return $model->newQuery()
            ->where('proceso_orden_id', $this->procesoOrdenId)
            ->with(['empleado', 'tipo_pago', 'moneda', 'proceso_orden.orden_trabajo']);
    }

    public function html(): HtmlBuilder
    {
        $url = route(
            'procesos.ordenes_trabajo.procesos.trabajos_empleados.index',
            [
                'ordenTrabajo' => $this->ordenTrabajoId,
                'procesoOrden' => $this->procesoOrdenId,
            ]
        );

        return $this->builder()
            ->setTableId('trabajos-empleados-table')
            ->columns($this->getColumns())
            ->minifiedAjax($url)
            ->orderBy(1, 'desc')
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

            Column::make('fecha')
                ->title('Fecha')
                ->width(100),

            Column::make('empleado')
                ->title('Empleado'),

            Column::make('tipo_pago')
                ->title('Tipo de pago'),

            Column::make('descripcion')
                ->title('Descripción'),

            Column::make('cantidad')
                ->title('Cantidad')
                ->addClass('text-end'),

            Column::make('unidad')
                ->title('Unidad')
                ->width(80)
                ->addClass('text-center'),

            Column::make('tarifa')
                ->title('Tarifa')
                ->addClass('text-end'),

            Column::make('moneda')
                ->title('Moneda')
                ->width(80)
                ->addClass('text-center'),

            Column::make('total')
                ->title('Total')
                ->addClass('text-end fw-semibold'),

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
        return 'TrabajosEmpleados_' . date('YmdHis');
    }
}
