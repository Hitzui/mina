<?php

namespace App\DataTables;

use App\Models\EmpleadosPago;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class EmpleadosPagosDataTable extends DataTable
{
    /**
     * ID del empleado al que pertenecen las tarifas.
     */
    protected ?int $empleadoId = null;

    /**
     * Establecer el empleado para filtrar el DataTable.
     */
    public function setEmpleadoId(int $empleadoId): static
    {
        $this->empleadoId = $empleadoId;

        return $this;
    }

    /**
     * Construir DataTable.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))

            ->addColumn(
                'tipo_pago',
                fn (EmpleadosPago $empleadoPago) =>
                    $empleadoPago->tipo_pago?->nombre ?? '—'
            )

            ->addColumn(
                'moneda',
                fn (EmpleadosPago $empleadoPago) =>
                    $empleadoPago->moneda?->codigo ?? '—'
            )

            ->editColumn(
                'tarifa',
                fn (EmpleadosPago $empleadoPago) =>
                number_format($empleadoPago->tarifa, 2)
            )

            ->editColumn(
                'fecha_inicio',
                fn (EmpleadosPago $empleadoPago) =>
                    $empleadoPago->fecha_inicio?->format('d/m/Y') ?? '—'
            )

            ->editColumn(
                'fecha_fin',
                fn (EmpleadosPago $empleadoPago) =>
                    $empleadoPago->fecha_fin?->format('d/m/Y') ?? 'Vigente'
            )

            ->editColumn(
                'estado',
                function (EmpleadosPago $empleadoPago) {

                    if ($empleadoPago->estado) {
                        return '<span class="badge bg-success">
                                    Activo
                                </span>';
                    }

                    return '<span class="badge bg-danger">
                                Inactivo
                            </span>';
                }
            )

            ->addColumn(
                'action',
                'admin.empleados.pagos._action'
            )

            ->setRowId('id')

            ->rawColumns([
                'estado',
                'action',
            ]);
    }

    /**
     * Consulta principal.
     */
    public function query(EmpleadosPago $model): QueryBuilder
    {
        return $model->newQuery()
            ->with([
                'tipo_pago',
                'moneda',
            ])
            ->when(
                $this->empleadoId,
                function (QueryBuilder $query) {
                    $query->where(
                        'empleado_id',
                        $this->empleadoId
                    );
                }
            );
    }

    /**
     * Configuración HTML.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('empleados-pagos-table')
            ->columns($this->getColumns())
            ->minifiedAjax(
                route(
                    'admin.empleados.pagos.data',
                    $this->empleadoId
                )
            )
            ->orderBy(4, 'desc')
            ->selectStyleSingle()
            ->parameters([
                'responsive' => true,
                'autoWidth' => false,
                'pageLength' => 10,
                'language' => [
                    'url' => 'https://cdn.datatables.net/plug-ins/2.3.8/i18n/es-ES.json',
                ],
            ])
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
     * Columnas.
     */
    public function getColumns(): array
    {
        return [

            Column::make('tipo_pago')
                ->title('Tipo de pago'),

            Column::make('tarifa')
                ->title('Tarifa')
                ->addClass('text-end'),

            Column::make('moneda')
                ->title('Moneda')
                ->addClass('text-center'),

            Column::make('fecha_inicio')
                ->title('Inicio')
                ->addClass('text-center'),

            Column::make('fecha_fin')
                ->title('Fin')
                ->addClass('text-center'),

            Column::make('estado')
                ->title('Estado')
                ->addClass('text-center'),

            Column::computed('action')
                ->title('Acciones')
                ->exportable(false)
                ->printable(false)
                ->width(110)
                ->addClass('text-center'),
        ];
    }

    /**
     * Nombre de archivo para exportaciones.
     */
    protected function filename(): string
    {
        return 'EmpleadosPagos_' . date('YmdHis');
    }
}
