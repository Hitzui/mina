<?php

namespace App\DataTables;

use App\DataTables\Concerns\TablaResponsiva;
use App\Models\CierreCaja;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class CierresCajaDataTable extends DataTable
{
    use TablaResponsiva;

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return new EloquentDataTable($query)
            ->addColumn('caja', fn(CierreCaja $cierre) => e($cierre->caja?->nombre ?? 'Caja eliminada'))
            ->addColumn('periodo', fn(CierreCaja $cierre) => $cierre->fecha_desde->format('d/m/Y')
                . ' – ' . $cierre->fecha_hasta->format('d/m/Y'))
            ->addColumn('action', fn(CierreCaja $cierre) => view('procesos.cierres_caja._acciones', compact('cierre'))->render())
            ->rawColumns(['caja', 'periodo', 'action'])
            ->setRowId('id');
    }

    public function query(CierreCaja $model): QueryBuilder
    {
        return $model->newQuery()->with('caja')->orderByDesc('fecha_hasta')->orderByDesc('id');
    }

    public function html(): HtmlBuilder
    {
        return $this->ajustesComunes($this->builder())
            ->setTableId('cierres-caja-table')
            ->columns($this->getColumns())
            ->minifiedAjax(route('procesos.cierres_caja.index'))
            ->orderBy(1, 'desc');
    }

    public function getColumns(): array
    {
        return [
            Column::make('codigo')->title('Código')->addClass('fw-semibold'),
            Column::computed('periodo')->title('Período'),
            Column::computed('caja')->title('Caja'),
            Column::make('created_at')->title('Registrado'),
            Column::computed('action')->title('Acciones')->exportable(false)->printable(false)
                ->width(100)->addClass('text-center')->addClass(self::COLUMNA_ACCIONES),
        ];
    }

    protected function filename(): string
    {
        return 'Cierres_Caja_' . date('YmdHis');
    }
}
