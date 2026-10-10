<?php

namespace App\DataTables;

use App\DataTables\Concerns\TablaResponsiva;
use App\Models\TiposProduccion;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class TiposProduccionDataTable extends DataTable
{
    use TablaResponsiva;

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return new EloquentDataTable($query)
            ->addColumn('descripcion', fn (TiposProduccion $tipo) => $tipo->descripcion
                ?: '<span class="text-muted">—</span>')
            ->addColumn('en_uso', fn (TiposProduccion $tipo) => $this->textoDeUso($tipo))
            ->addColumn('estado', fn (TiposProduccion $tipo) => $tipo->estado
                ? '<span class="badge bg-success">Activo</span>'
                : '<span class="badge bg-secondary">Inactivo</span>')
            ->addColumn('action', fn (TiposProduccion $tipo) => view(
                'configuracion.tipos_produccion._action',
                ['tipoProduccion' => $tipo]
            )->render())
            ->rawColumns(['descripcion', 'en_uso', 'estado', 'action'])
            ->setRowId('id');
    }

    public function query(TiposProduccion $model): QueryBuilder
    {
        return $model->newQuery()->orderBy(TiposProduccion::NOMBRE);
    }

    public function html(): HtmlBuilder
    {
        return $this->ajustesComunes($this->builder())
            ->setTableId('tipos-produccion-table')
            ->columns($this->getColumns())
            ->minifiedAjax(route('configuracion.tipos_produccion.index'))
            ->orderBy(0, 'asc');
    }

    public function getColumns(): array
    {
        return [
            Column::make('nombre')->title('Nombre'),
            Column::make('descripcion')->title('Descripción')->addClass(self::OCULTAR_HASTA_ESCRITORIO),
            Column::make('en_uso')->title('Producciones con él')->addClass('text-center')->addClass(self::OCULTAR_EN_MOVIL),
            Column::make('estado')->title('Estado')->addClass('text-center')->addClass(self::OCULTAR_EN_MOVIL),
            Column::computed('action')
                ->title('Acciones')
                ->exportable(false)
                ->printable(false)
                ->width(120)
                ->addClass('text-center')
                ->addClass(self::COLUMNA_ACCIONES),
        ];
    }

    private function textoDeUso(TiposProduccion $tipo): string
    {
        $usos = $tipo->usos();

        if ($usos === []) {
            return '<span class="text-muted" title="No hay producciones con este tipo: se puede borrar.">—</span>';
        }

        return '<span class="badge bg-warning text-dark" title="'
            .e('No se puede borrar mientras tenga producciones; se puede desactivar. En '.$tipo->fraseDeUsos().'.')
            .'">'.$usos[0]['cuantas'].'</span>';
    }

    protected function filename(): string
    {
        return 'Tipos_Produccion_'.date('YmdHis');
    }
}
