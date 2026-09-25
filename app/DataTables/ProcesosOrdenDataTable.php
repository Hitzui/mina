<?php

namespace App\DataTables;

use App\Models\ProcesosOrden;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class ProcesosOrdenDataTable extends DataTable
{
    protected int $ordenTrabajoId;

    public function setOrdenTrabajoId(int $ordenTrabajoId): self
    {
        $this->ordenTrabajoId = $ordenTrabajoId;

        return $this;
    }

    /**
     * Construye la respuesta del DataTable.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return new EloquentDataTable($query)

            ->addColumn('etapa', function (ProcesosOrden $proceso) {
                return $proceso->etapa?->nombre ?? '-';
            })

            ->editColumn('fecha_inicio', function (ProcesosOrden $proceso) {
                return $proceso->fecha_inicio
                    ? $proceso->fecha_inicio->format('d/m/Y H:i')
                    : '-';
            })

            ->editColumn('fecha_fin', function (ProcesosOrden $proceso) {
                return $proceso->fecha_fin
                    ? $proceso->fecha_fin->format('d/m/Y H:i')
                    : '-';
            })

            ->editColumn('peso_entrada', function (ProcesosOrden $proceso) {
                return $proceso->peso_entrada !== null
                    ? number_format($proceso->peso_entrada, 4, '.', ',')
                    : '-';
            })

            ->editColumn('peso_salida', function (ProcesosOrden $proceso) {
                return $proceso->peso_salida !== null
                    ? number_format($proceso->peso_salida, 4, '.', ',')
                    : '-';
            })

            ->editColumn('estado', function (ProcesosOrden $proceso) {

                return match ($proceso->estado) {

                    1 => '<span class="badge bg-primary">
                            Pendiente
                          </span>',

                    2 => '<span class="badge bg-warning">
                            En proceso
                          </span>',

                    3 => '<span class="badge bg-success">
                            Finalizado
                          </span>',

                    4 => '<span class="badge bg-danger">
                            Cancelado
                          </span>',

                    default => '<span class="badge bg-secondary">
                                    Desconocido
                                </span>',
                };
            })

            ->addColumn('action', function (ProcesosOrden $proceso) {

                $ordenTrabajo = $proceso->orden_trabajo;

                $editar = route(
                    'procesos.ordenes_trabajo.procesos.edit',
                    [
                        'ordenTrabajo' => $ordenTrabajo,
                        'procesoOrden' => $proceso,
                    ]
                );

                $eliminar = route(
                    'procesos.ordenes_trabajo.procesos.destroy',
                    [
                        'ordenTrabajo' => $ordenTrabajo,
                        'procesoOrden' => $proceso,
                    ]
                );

                return '
                    <div class="btn-group" role="group">

                        <a href="' . $editar . '"
                           class="btn btn-sm btn-warning"
                           title="Editar">
                            <i class="bi bi-pencil"></i>
                        </a>

                        <a href="' . $eliminar . '"
                           class="btn btn-sm btn-danger"
                           data-confirm-delete
                           data-confirm-title="¿Eliminar el proceso?"
                           data-confirm-text="Esta acción no se puede deshacer."
                           data-confirm-button="Sí, eliminar"
                           title="Eliminar">
                            <i class="bi bi-trash"></i>
                        </a>

                    </div>
                ';
            })

            ->rawColumns([
                'estado',
                'action',
            ])

            ->setRowId('id');
    }

    /**
     * Consulta de procesos.
     *
     * La OT se obtiene de la ruta actual.
     */
    public function query(ProcesosOrden $model): QueryBuilder
    {
        return $model
            ->newQuery()
            ->with([
                'etapa',
                'orden_trabajo',
            ])
            ->where(
                'orden_trabajo_id',
                $this->ordenTrabajoId
            );
    }

    /**
     * Configuración HTML del DataTable.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()

            ->setTableId('procesos-orden-table')

            ->addTableClass([
                'table-hover',
                'table-bordered',
            ])

            ->columns($this->getColumns())

            ->minifiedAjax(
                route(
                    'procesos.ordenes_trabajo.procesos.data',
                    [
                        'ordenTrabajo' => $this->ordenTrabajoId,
                    ]
                )
            )

            ->orderBy(2, 'asc')

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

    /**
     * Columnas del DataTable.
     */
    public function getColumns(): array
    {
        return [

            Column::make('codigo')
                ->title('Código'),

            Column::computed('etapa')
                ->title('Etapa'),

            Column::make('fecha_inicio')
                ->title('Inicio')
                ->addClass('text-center'),

            Column::make('fecha_fin')
                ->title('Fin')
                ->addClass('text-center'),

            Column::make('peso_entrada')
                ->title('Peso entrada')
                ->addClass('text-end'),

            Column::make('peso_salida')
                ->title('Peso salida')
                ->addClass('text-end'),

            Column::computed('estado')
                ->title('Estado')
                ->addClass('text-center'),

            Column::computed('action')
                ->title('Acciones')
                ->exportable(false)
                ->printable(false)
                ->width(90)
                ->addClass('text-center'),
        ];
    }

    /**
     * Nombre de archivo para exportaciones.
     */
    protected function filename(): string
    {
        return 'ProcesosOrden_' . date('YmdHis');
    }
}
