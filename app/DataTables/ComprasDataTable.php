<?php

namespace App\DataTables;

use App\DataTables\Concerns\TablaResponsiva;
use App\Models\Compra;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

/**
 * La lista de compras.
 *
 * La columna que importa mas que ninguna es la del estado, porque decide si
 * el material esta en el almacen o no. Por eso va antes que el total: es lo
 * que se viene a mirar de un vistazo, para saber cuantas compras hay
 * pendientes de recibir.
 */
class ComprasDataTable extends DataTable
{
    use TablaResponsiva;

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return new EloquentDataTable($query)
            ->addColumn('proveedor', fn(Compra $c) => $c->proveedor?->nombre_completo ?? '—')
            ->editColumn('fecha', fn(Compra $c) => $c->fecha?->format('d/m/Y') ?? '—')
            ->editColumn('numero_documento', fn(Compra $c) => $c->numero_documento ?: '—')
            ->addColumn('lineas', fn(Compra $c) => (string) $c->detalles()->count())
            ->editColumn('subtotal', fn(Compra $c) => number_format((float) $c->subtotal, 2))
            ->addColumn('moneda', fn(Compra $c) => $c->moneda?->codigo ?? '—')
            ->editColumn('total', fn(Compra $c) => number_format((float) $c->total, 2))

            // La columna de estado se pinta con la etiqueta del modelo, que
            // es la que decide si el material entra al almacen
            ->addColumn('estado', fn(Compra $c) => $c->estadoEtiqueta())

            ->editColumn('total_nio', fn(Compra $c) => $c->total_nio === null
                ? '<span class="text-muted">sin tipo de cambio</span>'
                : number_format((float) $c->total_nio, 2))
            ->addColumn('action', fn(Compra $c) => view(
                'inventario.compras._action',
                ['compra' => $c]
            )->render())
            ->rawColumns(['estado', 'total_nio', 'action'])
            ->setRowId('id');
    }

    public function query(Compra $model): QueryBuilder
    {
        return $model->newQuery()
            ->with(['proveedor', 'moneda'])
            ->orderByDesc('fecha')
            ->orderByDesc('id');
    }

    public function html(): HtmlBuilder
    {
        $builder = $this->ajustesComunes($this->builder());

        return $builder
            ->setTableId('compras-table')
            ->columns($this->getColumns())
            ->minifiedAjax(route('inventario.compras.index'))
            ->orderBy(0, 'desc');
    }

    public function getColumns(): array
    {
        return [
            Column::make('codigo')
                ->title('Código')
                ->addClass('font-monospace'),

            Column::make('fecha')
                ->title('Fecha')
                ->width(100),

            Column::make('proveedor')
                ->title('Proveedor'),

            Column::make('numero_documento')
                ->title('Documento')
                ->addClass(self::OCULTAR_HASTA_ESCRITORIO),

            Column::make('lineas')
                ->title('Líneas')
                ->width(70)
                ->addClass('text-center')
                ->addClass(self::OCULTAR_EN_MOVIL),

            // El estado va antes del total: es lo que se viene a mirar
            Column::make('estado')
                ->title('Estado')
                ->addClass('text-center'),

            Column::make('total')
                ->title('Total')
                ->addClass('text-end fw-semibold'),

            Column::make('moneda')
                ->title('Moneda')
                ->width(80)
                ->addClass('text-center')
                ->addClass(self::OCULTAR_HASTA_ESCRITORIO),

            Column::make('total_nio')
                ->title('Total NIO')
                ->addClass('text-end')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::computed('action')
                ->title('Acciones')
                ->exportable(false)
                ->printable(false)
                ->width(90)
                ->addClass('text-center')
                ->addClass(self::COLUMNA_ACCIONES),
        ];
    }

    protected function filename(): string
    {
        return 'Compras_' . date('YmdHis');
    }
}
