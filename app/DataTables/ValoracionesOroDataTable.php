<?php

namespace App\DataTables;

use App\DataTables\Concerns\TablaResponsiva;
use App\Models\ValoracionesOro;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

/**
 * Cuanto valio lo que salio del taller, y por cuanto.
 *
 * Se ordena por fecha de mas reciente a mas antigua, igual que las
 * recuperaciones, porque tambien es un historial: lo que se mira es la ultima
 * valoracion, no la primera que se escribio.
 *
 * LAS COLUMNAS ESTAN ELEGIDAS POR LO QUE SE PREGUNTA AL MIRAR UNA FILA.
 *
 * Las dos que de verdad importan son los gramos que se valoraron y el valor.
 * Los gramos valorados no son los de la recuperacion, y van aparte a
 * proposito: si la partida dio 2 gramos al 75 %, lo que se valoro fueron 1,5,
 * y poner lo mismo "2,0000 g" en la lista que "1,5000 g" deja a cualquiera
 * con la duda de si la cuenta esta mal. Con la cifra al lado y un aviso de que
 * se aplico la pureza, la pregunta se responde sola.
 *
 * Y el valor va con su moneda al lado, porque un numero suelto sin saber en
 * que moneda esta es peor que no enseñar nada: 7.050 son córdobas a 4.700 el
 * gramo y dolares a un precio que este taller no tiene.
 *
 * La columna de donde salio el precio guarda el dia, no el id. La id la guarda
 * la fila, que es donde sirve; en la lista lo util es poder ver que un valor se
 * cerro con el precio de un dia que no es el suyo, que es lo primero que hay
 * que comprobar cuando una valoracion parece alta.
 */
class ValoracionesOroDataTable extends DataTable
{
    use TablaResponsiva;

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return new EloquentDataTable($query)
            ->addColumn('orden', fn(ValoracionesOro $v) => $v->recuperacion?->orden_trabajo
                ? '<span class="font-monospace">' . e($v->recuperacion->orden_trabajo->codigo) . '</span>'
                    . ' <span class="text-muted small">· ' . e($v->recuperacion->orden_trabajo->cliente?->nombre ?? 'sin cliente') . '</span>'
                : '<span class="text-muted">—</span>')
            ->addColumn('estado_orden', fn(ValoracionesOro $v) => $v->recuperacion?->orden_trabajo
                ? $v->recuperacion->orden_trabajo->estadoEtiqueta()
                : '<span class="text-muted">—</span>')
            ->addColumn('recuperacion', fn(ValoracionesOro $v) => $this->textoRecuperacion($v))
            ->addColumn('gramos_valorados', fn(ValoracionesOro $v) => $this->textoGramos($v))
            ->addColumn('precio', fn(ValoracionesOro $v) => $this->textoPrecio($v))
            ->addColumn('valor', fn(ValoracionesOro $v) => '<span class="font-monospace fw-bold">'
                . number_format((float) $v->valor, 2) . '</span>'
                . ' <span class="text-muted small">' . e($v->moneda?->codigo ?? '—') . '</span>')
            ->addColumn('action', fn(ValoracionesOro $v) => view(
                'procesos.valoraciones_oro._action',
                ['valoracion' => $v]
            )->render())
            ->rawColumns(['orden', 'estado_orden', 'recuperacion', 'gramos_valorados', 'precio', 'valor', 'action'])
            ->setRowId('id');
    }

    public function query(ValoracionesOro $model): QueryBuilder
    {
        return $model->newQuery()
            ->with([
                'moneda',
                'precio_oro',
                'recuperacion.orden_trabajo.cliente',
            ])
            ->orderByDesc('fecha')
            ->orderByDesc('id');
    }

    public function html(): HtmlBuilder
    {
        $builder = $this->ajustesComunes($this->builder());

        return $builder
            ->setTableId('valoraciones-oro-table')
            ->columns($this->getColumns())
            ->minifiedAjax(route('procesos.valoraciones_oro.index'))
            ->orderBy(0, 'desc');
    }

    public function getColumns(): array
    {
        return [
            Column::make('fecha')
                ->title('Fecha')
                ->addClass('font-monospace'),

            Column::make('orden')
                ->title('Orden de trabajo'),

            Column::make('estado_orden')
                ->title('Estado de la orden')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('recuperacion')
                ->title('Recuperación')
                ->addClass(self::OCULTAR_HASTA_ESCRITORIO),

            Column::make('gramos_valorados')
                ->title('Gramos valorados')
                ->addClass('text-end'),

            Column::make('precio')
                ->title('Precio del gramo')
                ->addClass('text-end')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('valor')
                ->title('Valor')
                ->addClass('text-end'),

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
     * Como se reconoce la recuperacion de la que sale la valoracion.
     *
     * Se da la fecha y los gramos de partida, que es como las gasto el
     * taller. La id no se enseña porque no significa nada para quien la mira:
     * el 73 no le dice nada a nadie, mientras que "29/09 · 2,0000 g" si.
     */
    private function textoRecuperacion(ValoracionesOro $valoracion): string
    {
        $recuperacion = $valoracion->recuperacion;

        if (! $recuperacion) {
            return '<span class="text-muted">—</span>';
        }

        return '<span class="font-monospace">'
            . e($recuperacion->fecha->format('d/m/Y')) . '</span>'
            . ' <span class="text-muted small">· '
            . number_format((float) $recuperacion->gramos, 4) . ' g</span>';
    }

    /**
     * Los gramos que se valoraron, y el aviso de que se aplico la pureza.
     *
     * Cuando se uso la pureza, los gramos de la partida no son los que se
     * multiplicaron por el precio, y poner solo la cifra final deja la duda de
     * si el sistema se equivoco. Por eso el aviso va al lado: "1,5000 g
     * (75 % de 2,0000 g)" dice las dos cifras y se lee solo.
     */
    private function textoGramos(ValoracionesOro $valoracion): string
    {
        $g = $valoracion->gramosValorados();

        $texto = '<span class="font-monospace">'
            . number_format($g['valorados'], 4) . ' g</span>';

        if (! $g['usa_pureza']) {
            return $texto;
        }

        return $texto . '<br><span class="text-muted small" title="Se valoraron solo los gramos de oro fino, no los que salieron: la partida no era oro puro.">'
            . 'finos, ' . number_format($g['pureza'] * 100, 2) . ' % de '
            . number_format($g['gramos'], 4) . ' g</span>';
    }

    /**
     * De que dia salio el precio, y a que unidad estaba.
     *
     * Cuando el dia es el mismo que el de la valoracion no hace falta
     * recordarlo, y cuando no lo es es justo lo que hay que ver de un
     * vistazo. Por eso el "(del 12/09/2026)" solo aparece si el dia es otro.
     */
    private function textoPrecio(ValoracionesOro $valoracion): string
    {
        $precio = $valoracion->precio_oro;

        if (! $precio) {
            return '<span class="text-muted" title="Esta valoración se guardó sin precio guardado: se escribió a mano.">—</span>';
        }

        $texto = '<span class="font-monospace">'
            . number_format((float) $precio->precio, 4) . '</span>'
            . ' <span class="text-muted small">' . e($precio->unidad) . '</span>';

        if ($precio->fecha->toDateString() !== $valoracion->fecha->toDateString()) {
            $texto .= '<br><span class="text-muted small" title="Ese día no había cotización, así que se usó el último precio conocido.">'
                . 'del ' . e($precio->fecha->format('d/m/Y')) . '</span>';
        }

        return $texto;
    }

    protected function filename(): string
    {
        return 'Valoraciones_Oro_' . date('YmdHis');
    }
}
