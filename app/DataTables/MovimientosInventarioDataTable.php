<?php

namespace App\DataTables;

use App\DataTables\Concerns\TablaResponsiva;
use App\Models\MovimientosInventario;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

/**
 * El kardex: todo lo que ha entrado y salido del almacen.
 *
 * Solo entradas, salidas y ajustes. Los consumos de un proceso se ven en
 * la pantalla del proceso, que es donde se registran.
 */
class MovimientosInventarioDataTable extends DataTable
{
    use TablaResponsiva;

    protected ?string $tipo = null;

    /**
     * Acota la lista a un tipo de movimiento, para el filtro de la pantalla.
     */
    public function setTipo(?string $tipo): self
    {
        $this->tipo = $tipo;

        return $this;
    }

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return new EloquentDataTable($query)
            ->editColumn('fecha', fn($m) => $m->fecha?->format('d/m/Y') ?? '—')
            ->addColumn('tipo', function (MovimientosInventario $m) {
                $clase = match (true) {
                    $m->esEntrada() => 'bg-success',
                    $m->esSalida() => 'bg-warning',
                    default => 'bg-secondary',
                };

                return '<span class="badge ' . $clase . '">'
                    . e($m->nombreTipo()) . '</span>';
            })
            ->editColumn('cantidad', fn($m) => rtrim(
                rtrim(number_format((float) $m->cantidad, 3), '0'),
                '.'
            ))
            ->editColumn('costo_unitario', fn($m) => number_format((float) $m->costo_unitario, 2))
            ->editColumn('costo_total', fn($m) => number_format((float) $m->costo_total, 2))
            ->editColumn('referencia', fn($m) => $m->referencia ?: '—')
            ->addColumn('destino', function (MovimientosInventario $m) {
                $proceso = $m->proceso_orden?->etapa?->nombre
                    ?? ($m->proceso_orden?->codigo ?: null);

                if ($proceso !== null) {
                    return 'Proceso: ' . $proceso;
                }

                return $m->orden_trabajo
                    ? 'Orden: ' . $m->orden_trabajo->codigo
                    : '<span class="text-muted">Almacén</span>';
            })
            ->addColumn('action', fn($m) => view(
                'inventario.movimientos._action',
                ['movimiento' => $m]
            )->render())
            ->rawColumns(['tipo', 'destino', 'action'])
            ->setRowId('id');
    }

    public function query(MovimientosInventario $model): QueryBuilder
    {
        $query = $model->newQuery()
            ->with(['producto', 'orden_trabajo', 'proceso_orden.etapa']);

        if ($this->tipo !== null) {
            $query->where('tipo', $this->tipo);
        }

        return $query;
    }

    public function html(): HtmlBuilder
    {
        $builder = $this->ajustesComunes($this->builder());

        return $builder
            ->setTableId('movimientos-inventario-table')
            ->columns($this->getColumns())
            ->minifiedAjax(route('inventario.movimientos.index', array_filter([
                'tipo' => $this->tipo,
            ])))
            ->orderBy(0, 'desc');
    }

    public function getColumns(): array
    {
        return [
            Column::make('fecha')
                ->title('Fecha')
                ->width(100),

            Column::make('tipo')
                ->title('Tipo')
                ->addClass('text-center'),

            Column::make('producto')
                ->title('Material'),

            Column::make('cantidad')
                ->title('Cantidad')
                ->addClass('text-end'),

            Column::make('costo_unitario')
                ->title('Costo unitario')
                ->addClass('text-end')
                ->addClass(self::OCULTAR_HASTA_ESCRITORIO),

            Column::make('costo_total')
                ->title('Total')
                ->addClass('text-end fw-semibold'),

            Column::make('destino')
                ->title('Destino')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('referencia')
                ->title('Referencia')
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
        return 'Kardex_' . date('YmdHis');
    }
}
