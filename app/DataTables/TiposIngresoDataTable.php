<?php

namespace App\DataTables;

use App\DataTables\Concerns\TablaResponsiva;
use App\Models\TiposIngreso;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

/**
 * Los tipos de ingreso, en la lista.
 *
 * Se ordena por nombre y no por id. En un catalogo el orden por id es el orden
 * en que se fueron dando de alta, que para el taller no significa nada: lo que
 * se mira al abrir la pantalla es la lista de los nombres, y esa tiene que
 * estar en orden alfabetico o hay que recorrerla entera para encontrar uno.
 *
 * LAS CUATRO COLUMNAS, Y POR QUE NO HAY MAS.
 *
 * El estado va con su etiqueta y no como un interruptor. En la lista no se
 * cambia nada: la lista es para leer, y cambiar un estado pulsando un boton sin
 * querer se hace mal con un dedo en un movil. Lo que dice la etiqueta es que
 * esta inactivo, y el boton de la fila es el que cambia.
 *
 * Y una columna de "en uso" con el numero de ingresos de cada tipo, que es lo
 * que responde a la pregunta que uno se hace al mirar un tipo: "este lo puedo
 * borrar o no". Sin esa columna hay que pulsar el borrar para enterarse, y con
 * el boton escondido —que es lo que haria una lista mas limpia— nadie sabria
 * por que esta apagado. Se deja el boton a la vista, como en las monedas, y la
 * columna adelanta lo que va a pasar.
 *
 * Esa columna se calcula con una consulta por fila y son cuatro filas, que es
 * lo que tiene el taller. Cuando sean cientos habria que pedirla al servidor
 * linea con la tabla y no una por fila, porque una consulta por fila en una
 * lineas son mil consultas, y eso se nota en el movil.
 */
class TiposIngresoDataTable extends DataTable
{
    use TablaResponsiva;

    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return new EloquentDataTable($query)
            ->addColumn('descripcion', fn(TiposIngreso $t) => $t->descripcion
                ?: '<span class="text-muted">—</span>')
            ->addColumn('estado', fn(TiposIngreso $t) => $this->etiquetaDeEstado($t))
            ->addColumn('en_uso', fn(TiposIngreso $t) => $this->textoDeUso($t))
            ->addColumn('action', fn(TiposIngreso $t) => view(
                'configuracion.tipos_ingreso._action',
                ['tipoIngreso' => $t]
            )->render())
            ->rawColumns(['descripcion', 'estado', 'en_uso', 'action'])
            ->setRowId('id');
    }

    public function query(TiposIngreso $model): QueryBuilder
    {
        return $model->newQuery()->orderBy(TiposIngreso::NOMBRE);
    }

    public function html(): HtmlBuilder
    {
        $builder = $this->ajustesComunes($this->builder());

        return $builder
            ->setTableId('tipos-ingreso-table')
            ->columns($this->getColumns())
            ->minifiedAjax(route('configuracion.tipos_ingreso.index'))
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
                ->title('Ingresos con él')
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
     * La etiqueta de activo o inactivo.
     *
     * "Inactivo" en gris y no tachado, porque tachado suena a error y un tipo
     * inactivo no es un tipo equivocado: es un tipo que se dejo de usar, y esa
     * es una decision. Lo que no puede pasar es que se vea igual que uno activo.
     */
    private function etiquetaDeEstado(TiposIngreso $tipo): string
    {
        return $tipo->estado
            ? '<span class="badge bg-success">Activo</span>'
            : '<span class="badge bg-secondary">Inactivo</span>';
    }

    /**
     * Cuantos ingresos hay con este tipo, y si se puede borrar.
     *
     * Con cero sale un guion y no un "0", porque un cero aqui no es un dato: es
     * la ausencia de datos, y escribiendolo como cero parece que se quebro la
     * columna. Con alguno sale el numero y el boton de borrar avisa de que no
     * va a poder.
     */
    private function textoDeUso(TiposIngreso $tipo): string
    {
        $usos = $tipo->usos();

        if ($usos === []) {
            return '<span class="text-muted" title="No hay ingresos con este tipo: se puede borrar.">—</span>';
        }

        $cuantas = $usos[0]['cuantas'];

        return '<span class="badge bg-warning text-dark" title="'
            . e('No se puede borrar mientras tenga ingresos: se desactiva, que lo saca de los desplegables. En ' . $tipo->fraseDeUsos() . '.')
            . '">' . $cuantas . '</span>';
    }

    protected function filename(): string
    {
        return 'Tipos_Ingreso_' . date('YmdHis');
    }
}
