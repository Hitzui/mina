<?php

namespace App\DataTables;

use App\DataTables\Concerns\TablaResponsiva;
use App\Models\Moneda;
use App\Models\TiposCambio;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

/**
 * El catalogo de monedas.
 *
 * Se ordena por el codigo y no por id, porque el codigo es lo que se lee de
 * un vistazo y la lista es corta: el orden por defecto seria el orden en que
 * se fueron dando de alta, que no dice nada.
 *
 * La columna de dias con tipo de cambio se cuenta con una consulta y no
 * cargando la relacion. La relacion es de miles de filas para una moneda que
 * lleva un ano de tipo de cambio, y aqui lo que se quiere es el numero: lo
 * unico que haria falta para pintar el numero es un COUNT, y traer las filas
 * para contar despues es tirar la fila entera para nada.
 */
class MonedasDataTable extends DataTable
{
    use TablaResponsiva;

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return new EloquentDataTable($query)
            ->addColumn('simbolo', fn(Moneda $m) => $m->simbolo
                ? '<span class="font-monospace">' . e($m->simbolo) . '</span>'
                : '<span class="text-muted">—</span>')
            ->addColumn('es_moneda_base', fn(Moneda $m) => $m->es_moneda_base
                ? '<span class="badge text-bg-primary">Base</span>'
                : '<span class="text-muted">—</span>')
            ->addColumn('dias_tipo_cambio', fn(Moneda $m) => $this->diasConTipoDeCambio($m))
            ->addColumn('estado', fn(Moneda $m) => $m->estado
                ? '<span class="badge text-bg-success">Activa</span>'
                : '<span class="badge text-bg-secondary">Inactiva</span>')
            ->addColumn('action', fn(Moneda $m) => view(
                'configuracion.monedas._action',
                ['moneda' => $m]
            )->render())
            ->rawColumns(['simbolo', 'es_moneda_base', 'dias_tipo_cambio', 'estado', 'action'])
            ->setRowId('id');
    }

    public function query(Moneda $model): QueryBuilder
    {
        return $model->newQuery()
            ->orderBy('codigo');
    }

    public function html(): HtmlBuilder
    {
        $builder = $this->ajustesComunes($this->builder());

        return $builder
            ->setTableId('monedas-table')
            ->columns($this->getColumns())
            ->minifiedAjax(route('configuracion.monedas.index'))
            ->orderBy(0, 'asc');
    }

    public function getColumns(): array
    {
        return [
            Column::make('codigo')
                ->title('Código')
                ->addClass('font-monospace'),

            Column::make('nombre')
                ->title('Moneda'),

            Column::make('simbolo')
                ->title('Símbolo')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('es_moneda_base')
                ->title('Base')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('dias_tipo_cambio')
                ->title('Días de tipo de cambio')
                ->addClass(self::OCULTAR_HASTA_ESCRITORIO),

            Column::make('estado')
                ->title('Estado'),

            Column::computed('action')
                ->title('Acciones')
                ->exportable(false)
                ->printable(false)
                ->width(120)
                ->addClass('text-center')
                ->addClass(self::COLUMNA_ACCIONES),
        ];
    }

    /**
     * Cuantos dias tiene tipo de cambio guardado esta moneda.
     *
     * La cuenta va con el tipo de cambio ya filtrado por borrados: un dia que
     * se borro no cuenta, porque ese dia la moneda no tiene tipo de cambio y
     * decirlo al reves haria creer que hay mas dias de los que hay.
     *
     * Una moneda base no tiene por que tener ninguno, y esa es la respuesta
     * que se ve con las dos de cordoba: cero dias, y no es un problema. Es
     * el cordoba contra si mismo, que siempre valia uno.
     */
    private function diasConTipoDeCambio(Moneda $moneda): string
    {
        $dias = TiposCambio::where(TiposCambio::MONEDA_ID, $moneda->id)->count();

        if ($dias === 0) {
            return $moneda->es_moneda_base
                ? '<span class="text-muted" title="La moneda base no necesita tipo de cambio: es el córdoba contra sí mismo.">No necesita</span>'
                : '<span class="text-muted" title="Todavía no se ha cargado ningún tipo de cambio de esta moneda.">Ninguno</span>';
        }

        return '<span class="font-monospace">' . $dias . '</span>';
    }

    protected function filename(): string
    {
        return 'Monedas_' . date('YmdHis');
    }
}
