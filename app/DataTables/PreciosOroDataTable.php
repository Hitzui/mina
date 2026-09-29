<?php

namespace App\DataTables;

use App\DataTables\Concerns\TablaResponsiva;
use App\Models\PreciosOro;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

/**
 * El historico del precio del oro.
 *
 * Se ordena por fecha de mas reciente a mas antigua y no por id, que es lo
 * que haria el orden por defecto: esto es una serie de fechas y lo que se
 * mira es el precio de hoy, no el ultimo que se escribio.
 *
 * La columna del precio tiene tres casos y no uno, y el tercero es el que mas
 * importa. Un dia con precio se ve el numero. Un dia en cero se ve tachado y
 * con la coletilla de que no se sabe, porque un cero a secas en una columna de
 * precios parece una cotizacion de cero y no lo que es. Y cuando el precio
 * guardado es de onza troy o de quilogramo y no del gramo, se dice cual es,
 * que es la forma de que nadie multiplique gramos por onzas sin enterarse: los
 * dos numeros se parecen y ninguna de las dos unidades es la que usa el
 * sistema.
 */
class PreciosOroDataTable extends DataTable
{
    use TablaResponsiva;

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return new EloquentDataTable($query)
            ->addColumn('moneda', fn(PreciosOro $p) => $p->moneda
                ? e($p->moneda->nombre) . ' (' . e($p->moneda->simbolo ?: e($p->moneda->codigo)) . ')'
                : '—')
            ->addColumn('unidad', fn(PreciosOro $p) => '<span class="text-muted">'
                . e($p->unidad) . '</span>')
            ->addColumn('precio', fn(PreciosOro $p) => $this->precioDe($p))
            ->addColumn('fuente', fn(PreciosOro $p) => $p->fuente ?: '<span class="text-muted">—</span>')
            ->addColumn('observaciones', fn(PreciosOro $p) => $p->observaciones ?: '<span class="text-muted">—</span>')
            ->addColumn('action', fn(PreciosOro $p) => view(
                'configuracion.precios_oro._action',
                ['precioOro' => $p]
            )->render())
            ->rawColumns(['moneda', 'unidad', 'precio', 'fuente', 'observaciones', 'action'])
            ->setRowId('id');
    }

    public function query(PreciosOro $model): QueryBuilder
    {
        return $model->newQuery()
            ->with('moneda')
            ->orderByDesc('fecha')
            ->orderByDesc('id');
    }

    public function html(): HtmlBuilder
    {
        $builder = $this->ajustesComunes($this->builder());

        return $builder
            ->setTableId('precios-oro-table')
            ->columns($this->getColumns())
            ->minifiedAjax(route('configuracion.precios_oro.index'))
            ->orderBy(0, 'desc');
    }

    public function getColumns(): array
    {
        return [
            Column::make('fecha')
                ->title('Fecha')
                ->addClass('font-monospace'),

            Column::make('precio')
                ->title('Precio'),

            Column::make('unidad')
                ->title('Unidad')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('moneda')
                ->title('Moneda')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('fuente')
                ->title('Fuente')
                ->addClass(self::OCULTAR_HASTA_ESCRITORIO),

            Column::make('observaciones')
                ->title('Observaciones')
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

    /**
     * Como se pinta el precio de una fila.
     *
     * Los ceros se enseñan tachados y con la coletilla, que es lo que evita el
     * malentendido: un cero sin explicar parece una cotizacion y hace que
     * alguien valore con el. Y el numero sale con los decimales que tiene, sin
     * ceros de sobra, que en una serie de precios los ceros a la derecha
     *ensanchan la columna y hacen mas dificil comparar dos dias de un vistazo.
     */
    private function precioDe(PreciosOro $precioOro): string
    {
        if ($precioOro->precioDesconocido()) {
            return '<span class="text-muted text-decoration-line-through font-monospace">0</span>'
                . ' <span class="text-muted small">no se sabe</span>';
        }

        $numero = rtrim(rtrim(number_format((float) $precioOro->precio, 4, '.', ''), '0'), '.');

        return '<span class="font-monospace">' . $numero . '</span>';
    }

    protected function filename(): string
    {
        return 'PreciosOro_' . date('YmdHis');
    }
}
