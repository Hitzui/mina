<?php

namespace App\DataTables;

use App\DataTables\Concerns\TablaResponsiva;
use App\Models\Empleado;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class EmpleadosDataTable extends DataTable
{
    use TablaResponsiva;

    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder<Empleado> $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return new EloquentDataTable($query)
            ->addColumn('action', 'admin.empleados._actions')
            ->editColumn('tipo_empleado_nombre', function (Empleado $empleado) {
                return $empleado->tipo_empleado_nombre ?: '-';
            })
            ->editColumn('estado', function (Empleado $empleado) {
                return $empleado->estado
                    ? '<span class="badge bg-success">Activo</span>'
                    : '<span class="badge bg-danger">Inactivo</span>';
            })
            ->editColumn('fecha_ingreso', function (Empleado $empleado) {
                return $empleado->fecha_ingreso
                    ? $empleado->fecha_ingreso->format('d/m/Y')
                    : '';
            })
            ->setRowId('id')
            ->rawColumns(['estado', 'action']);
    }

    /**
     * Get the dataTable query source.
     *
     * @return QueryBuilder<Empleado>
     */
    public function query(Empleado $model): QueryBuilder
    {
        /*
         * El nombre del tipo de empleado se trae con un leftJoin y se
         * expone como una columna real de la consulta. Si se declarara
         * la columna como 'tipo_empleado.nombre' (que no existe en la
         * tabla empleados), Yajra generaría un ORDER BY/WHERE inválido
         * y el listado entero entraría en error al ordenar o buscar.
         * Es un LEFT JOIN porque tipo_empleado_id es NOT NULL pero la
         * relación bien podría estar borrada lógicamente.
         */
        return $model
            ->newQuery()
            ->select('empleados.*')
            ->addSelect(
                'tipos_empleado.nombre as tipo_empleado_nombre'
            )
            ->leftJoin(
                'tipos_empleado',
                'empleados.tipo_empleado_id',
                '=',
                'tipos_empleado.id'
            );
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        $builder = $this->ajustesComunes($this->builder());

        return $builder
            ->setTableId('empleados-table')
            ->columns($this->getColumns())
            ->addTableClass([' table-hover', 'table-bordered'])
            ->minifiedAjax()
            ->orderBy(1);
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        return [
            Column::make('codigo')
                ->title('Código'),

            Column::make('nombre')
                ->title('Nombre'),

            Column::make('telefono')
                ->title('Teléfono')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('tipo_empleado_nombre')
                ->title('Tipo de empleado')
                ->addClass('text-center')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('fecha_ingreso')
                ->title('Fecha de ingreso')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('estado')
                ->title('Estado'),

            Column::computed('action')
                ->title('Acciones')
                ->exportable(false)
                ->printable(false)
                ->width(60)
                ->addClass('text-center')
                ->addClass(self::COLUMNA_ACCIONES),
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'Empleados_' . date('YmdHis');
    }
}
