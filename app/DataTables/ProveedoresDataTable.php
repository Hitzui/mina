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
 * El catalogo de proveedores.
 *
 * Las columnas se reparten en los mismos tres tramos que las demas tablas:
 * en un telefono se ven el codigo, el nombre, el contacto y las acciones, que
 * es lo justo para reconocer un proveedor y actuar sobre el. El resto
 * aparece en tableta o en escritorio.
 */
class ProveedoresDataTable extends DataTable
{
    use TablaResponsiva;

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return new EloquentDataTable($query)
            ->addColumn('contacto', fn($p) => $p->contacto ?: '—')
            ->addColumn('telefono', fn($p) => $p->telefono ?: '—')
            ->addColumn('email', fn($p) => $p->email
                ? '<a href="mailto:' . e($p->email) . '">' . e($p->email) . '</a>'
                : '—')
            ->editColumn('direccion', fn($p) => $p->direccion ?: '—')
            ->addColumn('compras', function (Proveedore $p) {
                // Con compras no se borra, se desactiva, asi que el numero
                // importa: es lo que explica despues por que el boton de
                // borrar no borro
                $cuantas = $p->compras()->count();

                return $cuantas === 0
                    ? '<span class="text-muted">—</span>'
                    : (string) $cuantas;
            })
            ->addColumn('estado', fn($p) => $p->estado
                ? '<span class="badge bg-success">Activo</span>'
                : '<span class="badge bg-secondary">Inactivo</span>')
            ->addColumn('action', fn($p) => view(
                'inventario.proveedores._action',
                ['proveedor' => $p]
            )->render())
            ->rawColumns(['email', 'compras', 'estado', 'action'])
            ->setRowId('id');
    }

    public function query(Proveedore $model): QueryBuilder
    {
        return $model->newQuery()->orderBy('nombre');
    }

    public function html(): HtmlBuilder
    {
        $builder = $this->ajustesComunes($this->builder());

        return $builder
            ->setTableId('proveedores-table')
            ->columns($this->getColumns())
            ->minifiedAjax(route('inventario.proveedores.index'))
            ->orderBy(0, 'asc');
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
                ->addClass(self::OCULTAR_HASTA_ESCRITORIO),

            Column::make('telefono')
                ->title('Teléfono')
                ->addClass(self::OCULTAR_HASTA_ESCRITORIO),

            Column::make('email')
                ->title('Correo')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('direccion')
                ->title('Dirección')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('compras')
                ->title('Compras')
                ->addClass('text-center')
                ->addClass(self::OCULTAR_HASTA_ESCRITORIO),

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
        return 'Proveedores_' . date('YmdHis');
    }
}
