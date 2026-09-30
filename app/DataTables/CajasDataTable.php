<?php

namespace App\DataTables;

use App\DataTables\Concerns\TablaResponsiva;
use App\Models\Cajas;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

/**
 * Las cajas, en la lista.
 *
 * Se ordena por nombre y no por id. En un catalogo el orden por id es el orden
 * en que se fueron creando, que para el taller no significa nada: lo que se mira
 * al abrir la pantalla es la lista de los nombres, y esa tiene que estar en orden
 * alfabetico o hay que recorrerla entera para encontrar una.
 *
 * LAS CUATRO COLUMNAS, Y POR QUE NO HAY MAS.
 *
 * El estado va con su etiqueta y no como un interruptor. En la lista no se
 * cambia nada: la lista es para leer, y cambiar un estado pulsando un boton sin
 * querer se hace mal con un dedo en un movil. Lo que dice la etiqueta es que
 * esta inactiva, y el boton de la fila es el que cambia.
 *
 * Y una columna de "cobros en ella" con el numero de cada caja, que es lo que
 * responde a la pregunta que uno se hace al mirar un catalogo: "esta la puedo
 * borrar o no". Sin esa columna hay que pulsar el borrar para enterarse, y con
 * el boton escondido —"que es lo que haria una lista mas limpia"— nadie sabria
 * por que esta apagado. Se deja el boton a la vista, como en las monedas, y la
 * columna adelanta lo que va a pasar.
 *
 * Esa columna se calcula con una consulta por fila, y aqui son pocas. Cuando
 * sean cientos habria que pedirla al servidor linea con la tabla y no una por
 * fila, porque una consulta por fila en una tabla de mil filas son mil
 * consultas, y eso se nota en el movil.
 */
class CajasDataTable extends DataTable
{
    use TablaResponsiva;

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return new EloquentDataTable($query)
            ->addColumn('descripcion', fn(Cajas $caja) => $caja->descripcion
                ?: '<span class="text-muted">—</span>')
            ->addColumn('estado', fn(Cajas $caja) => $this->etiquetaDeEstado($caja))
            ->addColumn('en_uso', fn(Cajas $caja) => $this->textoDeUso($caja))
            ->addColumn('action', fn(Cajas $caja) => view(
                'configuracion.cajas._action',
                ['caja' => $caja]
            )->render())
            ->rawColumns(['descripcion', 'estado', 'en_uso', 'action'])
            ->setRowId('id');
    }

    public function query(Cajas $model): QueryBuilder
    {
        return $model->newQuery()->orderBy(Cajas::NOMBRE);
    }

    public function html(): HtmlBuilder
    {
        $builder = $this->ajustesComunes($this->builder());

        return $builder
            ->setTableId('cajas-table')
            ->columns($this->getColumns())
            ->minifiedAjax(route('configuracion.cajas.index'))
            ->orderBy(0, 'asc');
    }

    public function getColumns(): array
    {
        return [
            Column::make('nombre')
                ->title('Nombre'),

            Column::make('descripcion')
                ->title('Descripción')
                ->addClass(self::OCULTAR_HASTA_ESCRITORIO),

            Column::make('en_uso')
                ->title('Cobros en ella')
                ->addClass('text-center')
                ->addClass(self::OCULTAR_EN_MOVIL),

            Column::make('estado')
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

    /**
     * La etiqueta de activa o inactiva.
     *
     * "Inactiva" en gris y no tachada, porque tachado suena a error y una caja
     * inactiva no es una caja equivocada: es una caja que se dejo de usar, y eso
     * es una decision. Lo que no puede pasar es que se vea igual que una activa.
     */
    private function etiquetaDeEstado(Cajas $caja): string
    {
        return $caja->estado
            ? '<span class="badge bg-success">Activa</span>'
            : '<span class="badge bg-secondary">Inactiva</span>';
    }

    /**
     * Cuantos cobros hay en esta caja, y si se puede borrar.
     *
     * Con cero sale un guion y no un "0", porque un cero aqui no es un dato: es
     * la ausencia de datos, y escribiendolo como cero parece que se quebro la
     * columna. Con alguno sale el numero y el boton de borrar avisa de que no va
     * a poder.
     */
    private function textoDeUso(Cajas $caja): string
    {
        $usos = $caja->usos();

        if ($usos === []) {
            return '<span class="text-muted" title="No hay cobros en esta caja: se puede borrar.">—</span>';
        }

        $cuantas = $usos[0]['cuantas'];

        return '<span class="badge bg-warning text-dark" title="'
            . e('No se puede borrar mientras tenga cobros: se desactiva, que la saca de los desplegables. En ' . $caja->fraseDeUsos() . '.')
            . '">' . $cuantas . '</span>';
    }

    protected function filename(): string
    {
        return 'Cajas_' . date('YmdHis');
    }
}
