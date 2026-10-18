<?php

namespace App\DataTables;

use App\DataTables\Concerns\TablaResponsiva;
use App\Models\AcuerdosOrden;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class AcuerdosOrdenDataTable extends DataTable
{
    use TablaResponsiva;

    protected int $ordenTrabajoId;

    public function setOrdenTrabajoId(int $ordenTrabajoId): self
    {
        $this->ordenTrabajoId = $ordenTrabajoId;

        return $this;
    }

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return new EloquentDataTable($query)
            ->addColumn('tipo', fn (AcuerdosOrden $acuerdo) => e($acuerdo->tipo_participacion?->nombre ?? '—'))
            ->addColumn('modalidad', fn (AcuerdosOrden $acuerdo) => $acuerdo->modalidad() === 'tarifa'
                ? 'Tarifa de servicio'
                : 'Participación')
            ->addColumn('condicion', fn (AcuerdosOrden $acuerdo) => $acuerdo->modalidad() === 'tarifa'
                ? number_format((float) $acuerdo->tarifa_servicio, 2).' '.e($acuerdo->moneda?->codigo ?? '')
                : number_format((float) $acuerdo->porcentaje_cliente, 4).' % cliente / '
                    .number_format((float) $acuerdo->porcentaje_empresa, 4).' % empresa')
            ->addColumn('estado_texto', fn (AcuerdosOrden $acuerdo) => $acuerdo->estado
                ? '<span class="badge bg-success">Activo</span>'
                : '<span class="badge bg-secondary">Inactivo</span>')
            ->addColumn('lama', fn (AcuerdosOrden $acuerdo) => $acuerdo->lama_destino === 'sin_definir' ? 'Sin acordar' : 'Conserva: '.$acuerdo->lama_destino.' · Oro empresa '.$acuerdo->lama_porcentaje_empresa.'% / cliente '.(100 - $acuerdo->lama_porcentaje_empresa).'%')
            ->addColumn('action', fn (AcuerdosOrden $acuerdo) => view(
                'procesos.ordenes_trabajo.acuerdos._action',
                compact('acuerdo')
            )->render())
            ->rawColumns(['estado_texto', 'action'])
            ->setRowId('id');
    }

    public function query(AcuerdosOrden $model): QueryBuilder
    {
        return $model->newQuery()
            ->deOrden($this->ordenTrabajoId)
            ->with(['tipo_participacion', 'moneda', 'orden_trabajo'])
            ->orderByDesc(AcuerdosOrden::CREATED_AT);
    }

    public function html(): HtmlBuilder
    {
        return $this->ajustesComunes($this->builder())
            ->setTableId('acuerdos-orden-table')
            ->columns($this->getColumns())
            ->minifiedAjax(route('procesos.ordenes_trabajo.acuerdos.index', $this->ordenTrabajoId))
            ->orderBy(0, 'asc');
    }

    public function getColumns(): array
    {
        return [
            Column::make('tipo')->title('Tipo de participación'),
            Column::make('modalidad')->title('Modalidad')->addClass(self::OCULTAR_EN_MOVIL),
            Column::make('condicion')->title('Condición')->addClass('text-end'),
            Column::make('lama')->title('Lama y oro posterior')->searchable(false)->orderable(false),
            Column::make('estado_texto')->title('Estado')->addClass('text-center')->addClass(self::OCULTAR_EN_MOVIL),
            Column::computed('action')->title('Acciones')->exportable(false)->printable(false)->width(130)
                ->addClass('text-center')->addClass(self::COLUMNA_ACCIONES),
        ];
    }

    protected function filename(): string
    {
        return 'Acuerdos_Orden_'.date('YmdHis');
    }
}
