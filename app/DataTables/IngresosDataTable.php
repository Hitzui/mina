<?php

namespace App\DataTables;

use App\DataTables\Concerns\TablaResponsiva;
use App\Models\Ingreso;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

/**
 * Los ingresos de una orden: lo que entro por esta orden.
 *
 * Va en la ficha de la orden y no en una pantalla global, y al reves que las
 * recuperaciones y las valoraciones. Aqui la razon es la contraria: un ingreso
 * NO tiene sentido fuera de su orden. Los recuperadores de oro se pueden mirar
 * todos juntos porque el oro del taller es el mismo para todas las ordenes, pero
 * lo que entro por una orden es de esa orden: entrar en una pantalla global para
 * ver cuanto se facturo en total tiene sentido; entrar para ver quanto se
 * facturo en la OT-2026-0003 no lo tiene, porque se entra en la OT.
 *
 * Y el orden es por fecha de mas reciente a mas antigua, no por id: lo que se
 * mira al entrar en una orden es el ultimo ingreso que se registro.
 *
 * LAS COLUMNAS, Y POR QUE NO HAY UNA DE "TOTAL DE LA ORDEN".
 *
 * Y LOS BOTONES LOS PINTA UNA VISTA, y se le pasa la fila a mano con
 * ['ingreso' => ...]. Al dejar solo el nombre de la vista, Yajra la renderiza
 * sin ningun dato y salia un "Undefined variable $ingreso" dentro del html de
 * la celda. No se ve: la pagina carga, la tabla aparece, y las celdas de
 * acciones vienen a medio hacer. Es la misma forma que usan la de costos y la
 * de las de las ordenes, que por eso funcionan.
 *
 * Y el total de la tabla NO se pinta como fila de pie. Se calcula en el
 * servidor y se pasa a la vista, porque un pie de DataTables se pinta en el
 * navegador y no sabe de la conversion a cordoba: el total en dolares de una
 * orden con un ingreso en dolares y otro en cordoba no se puede sumar en la
 * celda del cliente sin traer el tipo de cambio de cada fila, y si se traen ya
 * se ha hecho todo el calculo que se queria evitar. Con el total desde el
 * servidor, la suma sale con las mismas cifras que hay guardadas y no puede
 * salir una suma que no cuadre con las filas.
 *
 * Y los dos totales —el de la moneda del ingreso y el en cordoba— van juntos,
 * porque en este taller hay ingresos en dolar y la cuenta va en cordoba, y ver
 * solo uno de los dos no dice si el otro esta bien.
 */
class IngresosDataTable extends DataTable
{
    use TablaResponsiva;

    private ?int $ordenTrabajoId = null;

    /**
     * A que orden se limita la tabla.
     *
     * Se pone en vez de sacarlo de la url en cada metodo porque el DataTable lo
     * construye el contenedor de inyeccion y no recibe la orden: si se sacara
     * del request, haria falta que la peticion fuera una peticion de ajax con la
     * orden en la url, que es lo que pasa, pero el constructor no lo ve. Pasarlo
     * desde el controlador lo deja explicito y evita que la tabla se paint de
     * otra orden si alguien la reutiliza sin querer.
     */
    public function setOrdenTrabajoId(int $ordenTrabajoId): self
    {
        $this->ordenTrabajoId = $ordenTrabajoId;

        return $this;
    }

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return new EloquentDataTable($query)
            ->addColumn('tipo', fn(Ingreso $ingreso) => $ingreso->tipo_ingreso?->nombre
                ?: '<span class="text-muted">—</span>')
            ->addColumn('descripcion', fn(Ingreso $ingreso) => $ingreso->descripcion
                ?: '<span class="text-muted">—</span>')
            ->addColumn('cantidad_unidad', fn(Ingreso $ingreso) => $this->cantidadConUnidad($ingreso))
            ->addColumn('precio_unitario', fn(Ingreso $ingreso) => '<span class="font-monospace">'
                . number_format((float) $ingreso->precio_unitario, 2) . '</span>')
            ->addColumn('total', fn(Ingreso $ingreso) => '<span class="font-monospace fw-bold">'
                . number_format((float) $ingreso->total, 2) . '</span>'
                . ' <span class="text-muted small">' . e($ingreso->moneda?->codigo ?? '—') . '</span>')
            ->addColumn('total_nio', fn(Ingreso $ingreso) => $this->totalEnCordoba($ingreso))
            ->addColumn('action', fn(Ingreso $ingreso) => view(
                'procesos.ingresos._action',
                ['ingreso' => $ingreso]
            )->render())
            ->rawColumns(['tipo', 'descripcion', 'cantidad_unidad', 'precio_unitario', 'total', 'total_nio', 'action'])
            ->setRowId('id');
    }

    public function query(Ingreso $model): QueryBuilder
    {
        $query = $model->newQuery()
            ->with(['tipo_ingreso', 'moneda']);

        if ($this->ordenTrabajoId !== null) {
            $query->deOrden($this->ordenTrabajoId);
        }

        return $query;
    }

    public function html(): HtmlBuilder
    {
        $builder = $this->ajustesComunes($this->builder());

        /*
         * LA URL DEL AJAX VA AQUI, y no puede faltar: es lo que hace que la
         * tabla pida sus filas. Sin ella la tabla se pinta con "ajax":"" y se
         * queda vacia sin decir nada, que es un fallo que no da error —la
         * pagina carga, la tabla aparece— y que solo se ve mirando la
         * pantalla. Todas las demas la traen.
         */
        $url = route(
            'procesos.ordenes_trabajo.ingresos.index',
            ['ordenTrabajo' => $this->ordenTrabajoId]
        );

        return $builder
            ->setTableId('ingresos-table')
            ->columns($this->getColumns())
            ->minifiedAjax($url)
            ->orderBy(0, 'desc');
    }

    public function getColumns(): array
    {
        return [
            Column::make('fecha')
                ->title('Fecha')
                ->addClass('font-monospace'),

            Column::make('tipo')
                ->title('Tipo'),

            Column::make('descripcion')
                ->title('Descripción')
                ->addClass(self::OCULTAR_HASTA_ESCRITORIO),

            Column::make('cantidad_unidad')
                ->title('Cantidad')
                ->addClass('text-end'),

            Column::make('precio_unitario')
                ->title('Precio unitario')
                ->addClass('text-end')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('total')
                ->title('Total')
                ->addClass('text-end'),

            Column::make('total_nio')
                ->title('Total en NIO')
                ->addClass('text-end')
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

    /**
     * La cantidad con su unidad al lado.
     *
     * Cuando no hay unidad, sale solo la cantidad y no un guion al lado: un
     * ingreso de participacion en oro va en gramos y el gramo ya esta en el
     * numero de la columna, pero un ingreso de servicio va en "1 servicio" y sin
     * la unidad no se sabe de que es.
     */
    private function cantidadConUnidad(Ingreso $ingreso): string
    {
        $numero = '<span class="font-monospace">'
            . rtrim(rtrim(number_format((float) $ingreso->cantidad, 4, '.', ''), '0'), '.')
            . '</span>';

        if (! $ingreso->unidad_medida) {
            return $numero;
        }

        return $numero . ' <span class="text-muted small">' . e($ingreso->unidad_medida) . '</span>';
    }

    /**
     * El equivalente en cordoba, y si no hay, por que.
     *
     * Con la moneda base sale el mismo numero y no un guion. En el resto del
     * sistema, cuando no hay tipo de cambio el equivalente se queda vacio
     * porque no hay nada que convertir; aqui un ingreso en cordoba SI tiene
     * equivalente en cordoba, y dejarlo vacio haria que la suma de la orden
     * saliera mas baja de lo que es sin que nada avise.
     *
     * Y cuando de verdad no se pudo calcular —una moneda sin tipo de cambio en
     * esa fecha— sale un guion con el motivo, que es lo unico que le dice al
     * usuario por que el total de abajo no cuadra con lo que espera.
     */
    private function totalEnCordoba(Ingreso $ingreso): string
    {
        if ($ingreso->total_nio === null) {
            return '<span class="text-muted" title="No se encontró tipo de cambio de esa moneda para esa fecha: el equivalente se quedó vacío.">—</span>';
        }

        return '<span class="font-monospace">'
            . number_format((float) $ingreso->total_nio, 2) . '</span>';
    }

    protected function filename(): string
    {
        return 'Ingresos_' . date('YmdHis');
    }
}
