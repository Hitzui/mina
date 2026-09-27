<?php

namespace App\DataTables;

use App\DataTables\Concerns\TablaResponsiva;
use App\Models\Proveedore;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

/**
 * El catalogo de proveedores, para elegir uno desde un modal.
 *
 * Es una tabla aparte y no una sublista del listado de proveedores, porque
 * aqui no se busca el historial de uno: se busca a quien se le compra. Por eso
 * no lleva la columna de compras, ni la de estado, ni los botones de editar y
 * eliminar: esas cosas son de la pantalla del catalogo, y en una ventana que
 * se abre encima de un formulario solo estorban.
 *
 * En un telefono se ven el codigo, el nombre y el boton. Con eso se elige sin
 * salirse de la lista: el contacto y el telefono estan en la ficha del
 * proveedor, que es donde se consultan.
 */
class ProveedorSelectorDataTable extends DataTable
{
    use TablaResponsiva;

    /**
     * @param  QueryBuilder<Proveedore>  $query
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            /*
             * El boton lleva los datos en atributos y no un id suelto: el
             * javascript necesita el nombre y el codigo para ponerlos en el
             * formulario, y si solo pasara el id tendria que volver a
             * preguntar al servidor lo que ya tiene delante.
             */
            ->addColumn('seleccionar', fn(Proveedore $p) => sprintf(
                '<button type="button"'
                . ' class="btn btn-sm btn-primary btn-seleccionar-proveedor"'
                . ' data-id="%s"'
                . ' data-codigo="%s"'
                . ' data-nombre="%s">'
                . '<i class="bi bi-check"></i> Seleccionar'
                . '</button>',
                $p->id,
                e($p->codigo),
                e($p->nombre)
            ))
            ->editColumn('codigo', fn(Proveedore $p) => '<span class="font-monospace">' . e($p->codigo) . '</span>')
            ->editColumn('contacto', fn(Proveedore $p) => $p->contacto ?: '—')
            ->editColumn('telefono', fn(Proveedore $p) => $p->telefono ?: '—')
            ->rawColumns(['codigo', 'seleccionar'])
            ->setRowId('id');
    }

    /**
     * Solo los proveedores con los que se puede comprar ahora.
     *
     * Los desactivados no salen. Un proveedor desactivado se puede volver a
     * activar desde su ficha y se sigue viendo en las compras viejas, pero no
     * se le compra de nuevo, asi que no tiene sentido ofrecerlo al elegir uno.
     *
     * @return QueryBuilder<Proveedore>
     */
    public function query(Proveedore $model): QueryBuilder
    {
        return $model
            ->newQuery()
            ->where('estado', true);
    }

    public function html(): HtmlBuilder
    {
        return $this->ajustesComunes($this->builder())
            ->setTableId('proveedor-selector-table')
            ->columns($this->getColumns())
            ->minifiedAjax(route('inventario.proveedores.selector'))
            ->orderBy(1, 'asc');
    }

    public function getColumns(): array
    {
        return [
            Column::make('codigo')
                ->title('Código')
                ->addClass('font-monospace'),

            Column::make('nombre')
                ->title('Proveedor'),

            Column::make('contacto')
                ->title('Contacto')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('telefono')
                ->title('Teléfono')
                ->addClass(self::OCULTAR_HASTA_ESCRITORIO),

            Column::computed('seleccionar')
                ->title('Elegir')
                ->exportable(false)
                ->printable(false)
                ->width(120)
                ->addClass('text-center')
                ->addClass(self::COLUMNA_ACCIONES),
        ];
    }

    protected function filename(): string
    {
        return 'Proveedores_' . date('YmdHis');
    }
}
