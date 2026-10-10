<?php

namespace App\DataTables;

use App\DataTables\Concerns\TablaResponsiva;
use App\Models\MovimientoCaja;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class MovimientosCajaDataTable extends DataTable
{
    use TablaResponsiva;

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return new EloquentDataTable($query)
            ->addColumn('caja', fn(MovimientoCaja $movimiento) => e($movimiento->caja?->nombre ?? 'Caja eliminada'))
            ->addColumn('tipo_texto', fn(MovimientoCaja $movimiento) => $movimiento->tipo === MovimientoCaja::ENTRADA
                ? '<span class="badge bg-success">Entrada</span>'
                : '<span class="badge bg-danger">Salida</span>')
            ->addColumn('referencia_texto', fn(MovimientoCaja $movimiento) => e($movimiento->referencia ?: '—'))
            ->editColumn('fecha', fn(MovimientoCaja $movimiento) => $movimiento->fecha->format('d/m/Y'))
            ->editColumn('monto', fn(MovimientoCaja $movimiento) => number_format((float) $movimiento->monto, 2, '.', ','))
            ->addColumn('metodo', fn(MovimientoCaja $movimiento) => e($movimiento->metodoPago?->nombre ?? '—'))
            ->addColumn('moneda', fn(MovimientoCaja $movimiento) => e($movimiento->moneda?->codigo ?? '—'))
            ->rawColumns(['tipo_texto'])
            ->setRowId('id');
    }

    public function query(MovimientoCaja $model): QueryBuilder
    {
        return $model->newQuery()
            ->with(['caja', 'metodoPago', 'moneda'])
            ->orderByDesc('fecha')->orderByDesc('id');
    }

    public function html(): HtmlBuilder
    {
        return $this->ajustesComunes($this->builder())
            ->setTableId('movimientos-caja-table')
            ->columns($this->getColumns())
            ->minifiedAjax(route('procesos.movimientos_caja.index'))
            ->orderBy(0, 'desc');
    }

    public function getColumns(): array
    {
        return [
            Column::make('fecha')->title('Fecha'),
            Column::make('codigo')->title('Código')->addClass('fw-semibold'),
            Column::computed('caja')->title('Caja'),
            Column::computed('tipo_texto')->title('Tipo'),
            Column::make('descripcion')->title('Concepto'),
            Column::computed('metodo')->title('Método')->addClass(self::OCULTAR_EN_MOVIL),
            Column::computed('moneda')->title('Moneda')->addClass(self::OCULTAR_HASTA_ESCRITORIO),
            Column::make('monto')->title('Importe')->addClass('text-end'),
            Column::computed('referencia_texto')->title('Referencia')->addClass(self::OCULTAR_HASTA_ESCRITORIO),
        ];
    }

    protected function filename(): string
    {
        return 'Movimientos_Caja_' . date('YmdHis');
    }
}
