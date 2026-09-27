<?php

namespace App\DataTables;

use App\Models\Equipo;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class EquiposDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return new EloquentDataTable($query)
            ->editColumn('fecha_adquisicion', fn($e) => $e->fecha_adquisicion?->format('d/m/Y') ?? '—')
            ->editColumn('valor_adquisicion', fn($e) => number_format($e->valor_adquisicion, 2))
            ->editColumn('valor_residual', fn($e) => number_format($e->valor_residual, 2))
            ->editColumn('descripcion', fn($e) => $e->descripcion ?: '—')
            // La tasa diaria sale del modelo: es la formula de la
            // depreciacion, no un dato guardado que se pueda desfasar
            ->addColumn(
                'depreciacion_diaria',
                fn($e) => number_format($e->depreciacionDiaria(), 4)
            )
            ->addColumn('procesos', fn($e) => $e->asignaciones_count ?? 0)
            ->editColumn('estado', fn($e) => $e->estado
                ? '<span class="badge bg-success">Activo</span>'
                : '<span class="badge bg-danger">Inactivo</span>')
            ->addColumn('action', 'admin.equipos.action')
            ->setRowId('id')
            ->rawColumns(['estado', 'action']);
    }

    public function query(Equipo $model): QueryBuilder
    {
        // El conteo de usos viene con withCount para no consultar una
        // vez por cada fila de la tabla
        return $model->newQuery()->withCount('asignaciones');
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('equipo-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy(1, 'asc')
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
            Column::make('codigo')
                ->title('Código')
                ->width(110),

            Column::make('nombre')
                ->title('Equipo'),

            Column::make('fecha_adquisicion')
                ->title('Adquisición')
                ->width(120),

            Column::make('valor_adquisicion')
                ->title('Valor adquisición')
                ->addClass('text-end'),

            Column::make('valor_residual')
                ->title('Valor residual')
                ->addClass('text-end'),

            Column::make('vida_util_meses')
                ->title('Vida útil (meses)')
                ->width(110)
                ->addClass('text-center'),

            Column::make('depreciacion_diaria')
                ->title('Depreciación /día')
                ->addClass('text-end'),

            Column::make('procesos')
                ->title('Procesos')
                ->width(90)
                ->addClass('text-center'),

            Column::make('estado')
                ->title('Estado')
                ->width(100)
                ->addClass('text-center'),

            Column::computed('action')
                ->title('Acciones')
                ->exportable(false)
                ->printable(false)
                ->width(120)
                ->addClass('text-center'),
        ];
    }

    protected function filename(): string
    {
        return 'Equipos_' . date('YmdHis');
    }
}
