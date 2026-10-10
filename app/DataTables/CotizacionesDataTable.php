<?php

namespace App\DataTables;

use App\DataTables\Concerns\TablaResponsiva;
use App\Models\Cotizacion;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class CotizacionesDataTable extends DataTable
{
    use TablaResponsiva;

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('cliente', fn (Cotizacion $cotizacion) => e($cotizacion->cliente?->nombre ?? '—'))
            ->editColumn('fecha', fn (Cotizacion $cotizacion) => $cotizacion->fecha->format('d/m/Y'))
            ->addColumn('vigencia', fn (Cotizacion $cotizacion) => $cotizacion->fecha_vencimiento->format('d/m/Y'))
            ->addColumn('condicion', function (Cotizacion $cotizacion) {
                if ($cotizacion->modalidad() === 'tarifa') {
                    return e($cotizacion->moneda?->codigo.' '.number_format((float) $cotizacion->tarifa_servicio, 2, ',', '.'));
                }

                return e(number_format((float) $cotizacion->porcentaje_cliente, 2, ',', '.').'% cliente / '
                    .number_format((float) $cotizacion->porcentaje_empresa, 2, ',', '.').'% empresa');
            })
            ->addColumn('estado_etiqueta', fn (Cotizacion $cotizacion) => $cotizacion->estadoEtiqueta())
            ->addColumn('action', fn (Cotizacion $cotizacion) => view('comercial.cotizaciones._action', [
                'id' => $cotizacion->id,
                'puedeEditar' => $cotizacion->estado === Cotizacion::ESTADO_BORRADOR,
                'puedeEliminar' => $cotizacion->estado === Cotizacion::ESTADO_BORRADOR
                    && ! $cotizacion->estaVencida(),
            ])->render())
            ->rawColumns(['estado_etiqueta', 'action'])
            ->setRowId('id');
    }

    public function query(Cotizacion $model): QueryBuilder
    {
        return $model->newQuery()->with(['cliente', 'moneda']);
    }

    public function html(): HtmlBuilder
    {
        return $this->ajustesComunes($this->builder())
            ->setTableId('cotizaciones-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy(2, 'desc');
    }

    public function getColumns(): array
    {
        return [
            Column::make('codigo')->title('Código'),
            Column::make('cliente')->title('Cliente'),
            Column::make('fecha')->title('Fecha')->addClass('text-center'),
            Column::make('vigencia')->title('Válida hasta')->addClass('text-center')->addClass(self::OCULTAR_HASTA_ESCRITORIO),
            Column::make('peso_mineral')->title('Mineral')->addClass('text-end'),
            Column::make('unidad_peso')->title('Unidad')->addClass('text-center')->addClass(self::OCULTAR_HASTA_ESCRITORIO),
            Column::make('condicion')->title('Condición')->addClass(self::OCULTAR_HASTA_ESCRITORIO),
            Column::make('estado_etiqueta')->title('Estado')->addClass('text-center'),
            Column::computed('action')->title('Acciones')->exportable(false)->printable(false)
                ->width(100)->addClass('text-center')->addClass(self::COLUMNA_ACCIONES),
        ];
    }

    protected function filename(): string
    {
        return 'Cotizaciones_'.date('YmdHis');
    }
}
