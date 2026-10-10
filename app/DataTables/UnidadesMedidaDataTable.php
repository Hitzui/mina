<?php

namespace App\DataTables;

use App\DataTables\Concerns\TablaResponsiva;
use App\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class UnidadesMedidaDataTable extends DataTable
{
    use TablaResponsiva;

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return new EloquentDataTable($query)
            ->addColumn('estado', fn (UnidadMedida $unidad) => $unidad->estado
                ? '<span class="badge bg-success">Activa</span>'
                : '<span class="badge bg-secondary">Inactiva</span>')
            ->addColumn('action', fn (UnidadMedida $unidad) => view('configuracion.unidades_medida._action', compact('unidad'))->render())
            ->rawColumns(['estado', 'action'])
            ->setRowId('id');
    }

    public function query(UnidadMedida $model): QueryBuilder
    {
        return $model->newQuery()->orderBy('nombre');
    }

    public function html(): HtmlBuilder
    {
        return $this->ajustesComunes($this->builder())
            ->setTableId('unidades-medida-table')
            ->columns($this->getColumns())
            ->minifiedAjax(route('configuracion.unidades_medida.index'))
            ->orderBy(0, 'asc');
    }

    public function getColumns(): array
    {
        return [
            Column::make('nombre')->title('Unidad'),
            Column::make('estado')->title('Estado')->addClass('text-center'),
            Column::computed('action')->title('Acciones')->exportable(false)->printable(false)->width(130)->addClass('text-center')->addClass(self::COLUMNA_ACCIONES),
        ];
    }

    protected function filename(): string
    {
        return 'Unidades_Medida_'.date('YmdHis');
    }
}
