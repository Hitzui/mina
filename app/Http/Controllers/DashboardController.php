<?php

namespace App\Http\Controllers;

use App\Services\PanelDelTaller;
use Illuminate\Http\Request;

/**
 * El panel de inicio.
 *
 * Un controlador de treinta lineas, y es a proposito: todo el calculo esta en
 * PanelDelTaller, que es donde estan escritas las reglas. Un controlador con las
 * consultas dentro seria mas corto de escribir pero peor de mantener, porque las
 * reglas —el costo tiene cuatro sumandos, el estado de un proceso no esta en su
 * columna— son cosas que hay que poder leer seguidas y no entrelazadas con el
 * title y las migas de pan.
 *
 * ---------------------------------------------------------------------------
 * POR QUE NO HAY PERMISO DE MODULO
 * ---------------------------------------------------------------------------
 *
 * Las otras pantallas usan Concerns\AuthorizesModule y la mayoria tiene su modulo
 * en el seeder. Esta no, y no es un olvido: el panel es la pagina a la que entra
 * todo el que se autentica, y un permiso que casi todo el mundo tiene no protege
 * nada —solo protege al que se le OLVIDO darselo—. Ademas el unico contenido son
 * numeros y nombres de ordenes que el propio usuario ve en cuanto entra a su
 * pantalla, asi que ocultarlo del panel no ocultaria nada.
 *
 * Si algun dia hiciera falta —un rol nuevo que no deba ver cifras—, el sitio es
 * este: un middleware de permiso aqui, y el modulo "panel" en el seeder. No se ha
 * puesto antes porque seria un permiso que solo existe para no ponerse.
 */
class DashboardController extends Controller
{
    /**
     * El panel.
     *
     * El periodo llega por la url —?periodo=anio— y no por un formulario con
     * boton, porque un filtro que solo se cambia con javascript no funciona si el
     * javascript no carga, y ademas asi el filtro se puede compartir: el enlace
     * "el panel de este año" es una url, se copia y se manda.
     *
     * El servicio se queda con el periodo en vez de que el controlador lo reparta:
     * asi el servicio es el unico que sabe que "todo" no tiene limite de abajo, y
     * no hay dos sitios donde decidir desde que fecha se cuenta.
     */
    public function __invoke(Request $request)
    {
        $panel = new PanelDelTaller((string) $request->query('periodo', 'mes'));

        $ordenesPorEstado = $panel->ordenesPorEstado();
        $serie = $panel->serieDeDoceMeses();

        return view('dashboard.index', [
            'title' => 'Panel',
            'breadcrumbs' => [],

            'periodoNombre' => $panel->nombrePeriodo(),
            'botonesPeriodo' => $panel->botones(),

            'ordenesPorEstado' => $ordenesPorEstado,
            'procesosPorEstado' => $panel->procesosPorEstado(),

            'dinero' => $panel->dinero(),
            'oro' => $panel->oro(),
            'serie' => $serie,

            'ordenesAbiertas' => $panel->ordenesAbiertas(),
            'procesosAtrasados' => $panel->procesosAtrasados(),
            'ultimasOrdenes' => $panel->ultimasOrdenes(),

            /*
             * El json de las dos graficas se arma aqui y no en la vista.
             *
             * Blade no parsea bien un @json con un array de varias lineas dentro de
             * un atributo de html: el compilador se pierde contando los corchetes y
             * la vista sale con un error de sintaxis que solo se ve al abrir la
             * pagina. Se podria escribir en una sola linea y ya no pasa, pero
             * entonces el atributo queda larguisimo e ilegible.
             *
             * Y de todas formas el sitio correcto es aqui: la vista no tiene que
             * saber que forma tiene el json que espera ApexCharts. Si manana la
             * grafica pide otra cosa, se cambia esta linea y no el html.
             */
            'jsonEstados' => json_encode(
                array_map(
                    fn(array $t) => [
                        'texto' => $t['texto'],
                        'cuantas' => $t['cuantas'],
                        'hex' => $t['hex'],
                    ],
                    $ordenesPorEstado
                ),
                JSON_UNESCAPED_UNICODE
            ),

            'jsonSerie' => json_encode(
                [
                    'etiquetas' => $serie['etiquetas'],
                    'costo' => $serie['costo'],
                    'ingreso' => $serie['ingreso'],
                ],
                JSON_UNESCAPED_UNICODE
            ),
        ]);
    }
}
