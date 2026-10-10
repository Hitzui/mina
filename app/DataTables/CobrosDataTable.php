<?php

namespace App\DataTables;

use App\DataTables\Concerns\TablaResponsiva;
use App\Models\Cobro;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

/**
 * Los cobros, en la lista.
 *
 * ===========================================================================
 * LA COLUMNA DEL TOTAL, Y POR QUÉ NO ES LA COLUMNA `monto` DE LA TABLA
 * ===========================================================================
 *
 * Porque `monto` es un numero guardado y esta cuenta se calcula de las lineas, que
 * es donde esta la verdad. Se muestran las dos y no solo la calculada a proposito:
 *
 *   - Si solo se enseñara la calculada, y las dos no coincidieran por lo que sea,
 *     nadie veria el problema: se veria un total, y punto.
 *   - Y si solo se enseñara la guardada, se veria un numero que puede no ser la suma
 *     de lo que hay debajo.
 *
 * Las dos vienen en la misma columna, con la calculada grande y la guardada
 * debajo en pequeño. Cuando coinciden —lo normal— el usuario ve un numero y no se
 * entera de que hay dos, y cuando no coinciden ve los dos y sabe que hay algo que
 * mirar. Es la misma idea que el desglose del costo en el panel: un numero sin sus
 * partes es un numero del que hay que fiarse.
 *
 * ===========================================================================
 * Y LA COLUMNA DE CUÁNTOS INGRESOS TOCA
 * ===========================================================================
 *
 * Porque es lo que hace falta saber de un cobro de un vistazo: si es de una sola
 * cosa o de veinte. Y sale del numero de lineas, no de un calculo aparte, porque
 * son lo mismo y tener las dos cifras podrian ser distintas.
 */
class CobrosDataTable extends DataTable
{
    use TablaResponsiva;

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return new EloquentDataTable($query)
            ->addColumn('cliente', fn(Cobro $cobro) => $this->nombreDelCliente($cobro))
            ->addColumn('caja', fn(Cobro $cobro) => $this->nombreDeLaCaja($cobro))
            ->addColumn('metodo', fn(Cobro $cobro) => $this->nombreDelMetodo($cobro))
            ->addColumn('total', fn(Cobro $cobro) => $this->totalEnDosCifras($cobro))
            ->addColumn('lineas', fn(Cobro $cobro) => (string) $cobro->cantidad_lineas)
            ->addColumn('estado', fn(Cobro $cobro) => $this->etiquetaDeEstado($cobro))
            ->addColumn('action', fn(Cobro $cobro) => view(
                'procesos.cobros._action',
                ['cobro' => $cobro]
            )->render())
            ->rawColumns(['cliente', 'caja', 'metodo', 'total', 'lineas', 'estado', 'action'])
            ->setRowId('id');
    }

    public function query(Cobro $model): QueryBuilder
    {
        return $model->newQuery()->orderByDesc('fecha')->orderByDesc('id');
    }

    public function html(): HtmlBuilder
    {
        $builder = $this->ajustesComunes($this->builder());

        return $builder
            ->setTableId('cobros-table')
            ->columns($this->getColumns())
            /*
             * minifiedAjax y no un ajax a mano: sin el, la tabla sale con
             * "ajax":"" y no pide nada, y se ve como una tabla vacia sin que ninguna
             * pantalla parezca rota. Pasa con los DataTables que se crean a mano.
             */
            ->minifiedAjax(route('procesos.cobros.index'))
            ->orderBy(0, 'desc');
    }

    public function getColumns(): array
    {
        return [
            Column::make('codigo')
                ->title('Código')
                ->addClass('fw-semibold'),

            Column::make('fecha')
                ->title('Fecha'),

            Column::computed('cliente')
                ->title('Cliente'),

            Column::make('caja')
                ->title('Caja')
                ->addClass(self::OCULTAR_HASTA_ESCRITORIO),

            Column::make('metodo')
                ->title('Método')
                ->addClass(self::OCULTAR_HASTA_ESCRITORIO),

            Column::computed('total')
                ->title('Total')
                ->addClass('text-end'),

            Column::computed('lineas')
                ->title('Ingresos')
                ->addClass('text-center')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::computed('estado')
                ->title('Estado')
                ->addClass('text-center')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::computed('action')
                ->title('Acciones')
                ->exportable(false)
                ->printable(false)
                ->width(120)
                ->addClass('text-center')
                ->addClass(self::COLUMNA_ACCIONES),
        ];
    }

    // ==================================================================
    // Las celdas
    // ==================================================================

    /**
     * El total, con el guardado debajo.
     *
     * Cuando los dos coinciden —que es lo de siempre— se muestra solo el grande.
     * No porque sea mas limpio: porque enseñar dos numeros iguales invita a
     * compararlos, y comparar dos numeros que son lo mismo no aporta nada y hace
     * dudar de si hay una diferencia que no se ve.
     */
    private function totalEnDosCifras(Cobro $cobro): string
    {
        $guardado = (float) $cobro->monto;
        $calculado = $cobro->total();

        $html = sprintf(
            '<span class="fw-semibold">%s</span>',
            number_format($calculado, 2)
        );

        if (abs($guardado - $calculado) > 0.005) {
            $html .= sprintf(
                '<br><span class="text-danger d-block" style="font-size: .72rem;"'
                    . ' title="El total guardado en la fila no coincide con la suma de sus líneas.">'
                    . 'guardado %s</span>',
                number_format($guardado, 2)
            );
        }

        return $html;
    }

    /**
     * El cliente, con un guion si se borro.
     *
     * Un cliente puede haberse dado de baja, y un cobro que muestra un cliente
     * borrado tiene que seguir enseñando a quien se le cobró.
     */
    private function nombreDelCliente(Cobro $cobro): string
    {
        return e($cobro->cliente?->nombre ?? 'Cliente eliminado');
    }

    /**
     * La caja.
     *
     * Y con la caja desactivada sale un aviso al lado, porque es el caso que
     * confunde: un cobro de marzo con una caja que hoy esta apagada parece un cobro
     * mal apuntado, y lo que paso es que la caja se desactivo en junio porque ya no
     * se usa. El cobro se hizo con la caja correcta.
     */
    private function nombreDeLaCaja(Cobro $cobro): string
    {
        $caja = $cobro->caja;

        if ($caja === null) {
            return '<span class="text-muted">Caja eliminada</span>';
        }

        if (! $caja->estado) {
            return e($caja->nombre)
                . ' <span class="badge bg-secondary"'
                . ' title="Esta caja está desactivada ahora. El cobro se hizo con ella activa.">inactiva</span>';
        }

        return e($caja->nombre);
    }

    /**
     * El metodo de pago, que puede haber desaparecido si la pantalla se borro.
     */
    private function nombreDelMetodo(Cobro $cobro): string
    {
        $metodo = $cobro->metodo_pago;

        if ($metodo === null) {
            return '<span class="text-muted">Método eliminado</span>';
        }

        return e($metodo->nombre)
            . ($metodo->estado
                ? ''
                : ' <span class="badge bg-secondary"'
                    . ' title="Este método está desactivado ahora.">inactivo</span>');
    }

    /**
     * "Pendiente" en azul y no tachada, porque tachada suena a error y un cobro
     * pendiente no es un cobro equivocado: es dinero que se tiene que recibir, y eso
     * es un estado de verdad.
     *
     * Lo que no puede pasar es que se vea igual que uno finalizado, y por eso el
     * boton de cambiar de estado esta siempre a la vista en la fila.
     */
    private function etiquetaDeEstado(Cobro $cobro): string
    {
        return $cobro->estadoEtiqueta();
    }

    protected function filename(): string
    {
        return 'Cobros_' . date('YmdHis');
    }
}
