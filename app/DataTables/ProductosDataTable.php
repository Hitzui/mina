<?php

namespace App\DataTables;

use App\DataTables\Concerns\TablaResponsiva;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

/**
 * El catalogo de material del almacen.
 *
 * Muestra tambien lo que hay de cada producto, porque un catalogo sin
 * existencias no sirve para decidir que reponer.
 */
class ProductosDataTable extends DataTable
{
    use TablaResponsiva;

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return new EloquentDataTable($query)
            ->editColumn('stock_minimo', fn($p) => rtrim(
                rtrim(number_format($p->stock_minimo, 3), '0'),
                '.'
            ))
            ->addColumn('existencia', function (Producto $p) {
                $existencia = $p->existencia;

                $texto = rtrim(rtrim(number_format($existencia, 3), '0'), '.') . ' '
                    . $p->unidad_medida;

                /*
                 * El minimo es un aviso de reponer, no un estado. Se pinta
                 * distinto para que se vea de un vistazo que material hay
                 * que pedir, que es lo que se viene a mirar en esta pantalla.
                 */
                if ($p->estaPorDebajoDelMinimo()) {
                    return '<span class="text-danger fw-semibold">' . $texto . '</span>';
                }

                return $existencia > 0
                    ? $texto
                    : '<span class="text-muted">' . $texto . '</span>';
            })
            ->addColumn('costo_promedio', fn($p) => $p->costo_promedio > 0
                ? number_format($p->costo_promedio, 4)
                : '<span class="text-muted">—</span>')
            ->addColumn('valor_inventario', fn($p) => number_format($p->valor_inventario, 2))
            ->addColumn('estado', fn($p) => $p->estado
                ? '<span class="badge bg-success">Activo</span>'
                : '<span class="badge bg-secondary">Inactivo</span>')
            ->addColumn('action', fn($p) => view(
                'inventario.productos._action',
                ['producto' => $p]
            )->render())
            ->rawColumns(['existencia', 'costo_promedio', 'estado', 'action'])
            ->setRowId('id');
    }

    public function query(Producto $model): QueryBuilder
    {
        // Con el saldo cargado: sin el, la existencia sale a cero siempre
        return $model->newQuery()->with('inventario')->orderBy('nombre');
    }

    public function html(): HtmlBuilder
    {
        $builder = $this->ajustesComunes($this->builder());

        return $builder
            ->setTableId('productos-table')
            ->columns($this->getColumns())
            ->minifiedAjax(route('inventario.productos.index'))
            ->orderBy(0, 'asc');
    }

    public function getColumns(): array
    {
        return [
            Column::make('codigo')
                ->title('Código')
                ->addClass('font-monospace'),

            Column::make('nombre')
                ->title('Material'),

            Column::make('unidad_medida')
                ->title('Unidad')
                ->width(90)
                ->addClass('text-center')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('categoria')
                ->title('Categoría')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('existencia')
                ->title('Existencia')
                ->addClass('text-end fw-semibold'),

            Column::make('stock_minimo')
                ->title('Mínimo')
                ->addClass('text-end')
                ->addClass(self::OCULTAR_HASTA_ESCRITORIO),

            Column::make('costo_promedio')
                ->title('Costo prom.')
                ->addClass('text-end')
                ->addClass(self::OCULTAR_HASTA_ESCRITORIO),

            Column::make('valor_inventario')
                ->title('Valor')
                ->addClass('text-end')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('estado')
                ->title('Estado')
                ->addClass('text-center')
                ->addClass(self::OCULTAR_HASTA_ESCRITORIO),

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
        return 'Productos_' . date('YmdHis');
    }
}
