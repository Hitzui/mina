<?php

namespace App\DataTables;

use App\DataTables\Concerns\TablaResponsiva;
use App\Models\ProcesosOrden;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class ProcesosOrdenDataTable extends DataTable
{
    use TablaResponsiva;

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

            /*
             * Lo que se le paga a los empleados por este proceso: la suma
             * de los trabajos registrados, calculada al momento. Se
             * muestra en la lista de la orden para poder comparar de un
             * vistazo cuanto cuesta cada proceso.
             *
             * Los tres importes se calculan al momento. La mano de obra se
             * lee de la relacion ya cargada para no lanzar una consulta por
             * fila; los otros dos son sumas cortas sobre su propia tabla.
             */
            ->addColumn('costo_empleados', function (ProcesosOrden $proceso) {
                return number_format($proceso->costo_empleados, 2);
            })

            ->addColumn('costo_otros', function (ProcesosOrden $proceso) {
                return number_format($proceso->costo_otros, 2);
            })

            ->addColumn('costo_total', function (ProcesosOrden $proceso) {
                return '<span class="fw-semibold">'
                    . number_format($proceso->costo_total, 2)
                    . '</span>';
            })

            ->addColumn('action', function (ProcesosOrden $proceso) {

                $ordenTrabajo = $proceso->orden_trabajo;

                $ver = route(
                    'procesos.ordenes_trabajo.procesos.show',
                    [
                        'ordenTrabajo' => $ordenTrabajo,
                        'procesoOrden' => $proceso,
                    ]
                );

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

                        <a href="' . $ver . '"
                           class="btn btn-sm btn-primary"
                           title="Ver el proceso y sus trabajos">
                            <i class="bi bi-eye"></i>
                        </a>

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
                'costo_total',
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
                // Para el costo de mano de obra, que se calcula al momento
                'trabajos_empleados',
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
        $builder = $this->ajustesComunes($this->builder());

        return $builder

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

            ->orderBy(2, 'asc');
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
                ->addClass('text-center')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('fecha_fin')
                ->title('Fin')
                ->addClass('text-center')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('peso_entrada')
                ->title('Peso entrada')
                ->addClass('text-end')
                ->addClass(self::OCULTAR_HASTA_ESCRITORIO),

            Column::make('peso_salida')
                ->title('Peso salida')
                ->addClass('text-end')
                ->addClass(self::OCULTAR_HASTA_ESCRITORIO),

            Column::computed('estado')
                ->title('Estado')
                ->addClass('text-center'),

            Column::computed('costo_empleados')
                ->title('Mano de obra')
                ->addClass('text-end')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::computed('costo_otros')
                ->title('Otros costos')
                ->addClass('text-end')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::computed('costo_total')
                ->title('Costo total')
                ->addClass('text-end'),

            Column::computed('action')
                ->title('Acciones')
                ->exportable(false)
                ->printable(false)
                ->width(130)
                ->addClass('text-center')
                ->addClass(self::COLUMNA_ACCIONES),
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
