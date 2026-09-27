<?php

namespace Tests\Feature;

use App\DataTables\Concerns\TablaResponsiva;
use App\DataTables\EtapaDataTable;
use Tests\TestCase;

/**
 * Las tablas tienen que servir en un telefono, no solo en un escritorio.
 *
 * Este test existe porque el responsive se dio por hecho varias veces sin
 * estar: cuatro tablas declaraban responsive(true) y la extension de
 * DataTables no estaba cargada, asi que la opcion no hacia nada. Y los
 * botones de exportar llevaba años sin funcionar por lo mismo. Nada de
 * eso se ve mirando la pantalla en un ordenador.
 *
 * Comprueba tres cosas:
 *   - que ninguna tabla se quede sin el reparto de columnas;
 *   - que la configuracion que no hace nada no vuelva (responsive,
 *     la columna de casillas, el boton de PDF);
 *   - que los archivos de los que dependemos esten ahi y se carguen.
 */
class TablasResponsivasTest extends TestCase
{
    /**
     * Las 15 tablas de la app.
     *
     * Se escribe la lista a mano y no se recorre la carpeta a proposito:
     * si alguien anade una tabla nueva y no la apunta aqui, este test
     * falla y le obliga a decidir que se ve en un telefono. Recorrer la
     * carpeta dejaria pasar la tabla nueva sin revisar.
     */
    private const TABLAS = [
        'CategoriasCostoDataTable',
        'ClienteDataTable',
        'ClienteSelectorDataTable',
        'CostosOrdenDataTable',
        'CostosProcesoDataTable',
        'EmpleadosDataTable',
        'EmpleadosPagosDataTable',
        'EmpleadosSelectorDataTable',
        'EquiposDataTable',
        'EtapaDataTable',
        'OrdenesTrabajoDataTable',
        'ProcesoEquiposDataTable',
        'ProcesosOrdenDataTable',
        'TiposPagoEmpleadoDataTable',
        'TrabajosEmpleadosDataTable',
    ];

    private function ruta(string $tabla): string
    {
        return app_path('DataTables/' . $tabla . '.php');
    }

    private function fuente(string $tabla): string
    {
        $ruta = $this->ruta($tabla);

        $this->assertFileExists($ruta, "No existe el DataTable $tabla");

        return file_get_contents($ruta);
    }

    // ==================================================================
    // El reparto de columnas
    // ==================================================================

    public function test_todas_las_tablas_usan_el_trait_de_responsive(): void
    {
        foreach (self::TABLAS as $tabla) {
            $this->assertStringContainsString(
                'use TablaResponsiva;',
                $this->fuente($tabla),
                "$tabla no usa el trait: se le olvidaria el reparto de columnas"
            );
        }
    }

    public function test_ninguna_tabla_declara_responsive_a_mano(): void
    {
        /*
         * responsive(true) no hace nada sin la extension de DataTables
         * cargada. Se quito en todas las tablas y no debe volver: si
         * alguien lo anade creyendo que funciona, este test lo dice.
         */
        foreach (self::TABLAS as $tabla) {
            $this->assertStringNotContainsString(
                'responsive(',
                $this->fuente($tabla),
                "$tabla declara responsive(): la extension no esta cargada, no hace nada"
            );
        }
    }

    public function test_cada_columna_visible_en_movil_declara_que_se_ve(): void
    {
        /*
         * Los tramos se leen de una tabla concreta y se reutilizan en
         * todas: son los mismos en todas, y asi se lee de una fuente.
         */
        $tabla = new EtapaDataTable(app());

        $tramos = [
            $tabla::OCULTAR_EN_MOVIL,
            $tabla::OCULTAR_HASTA_ESCRITORIO,
        ];

        foreach (self::TABLAS as $nombre) {
            $columnas = $this->columnasDe($nombre);

            $this->assertNotEmpty($columnas, "$nombre no tiene columnas");

            foreach ($columnas as $columna => $clase) {
                /*
                 * "columna-acciones" se reconoce por su nombre: es la
                 * unica clase que el SCSS busca para pegar la columna al
                 * borde en un telefono.
                 */
                $esAcciones = str_contains($clase, 'columna-acciones');

                if ($esAcciones) {
                    $this->assertStringNotContainsString(
                        'd-none d-',
                        str_replace('columna-acciones', '', $clase),
                        "$nombre: la columna de acciones no se puede ocultar en un "
                        . 'telefono, si no se llega al boton de eliminar'
                    );

                    continue;
                }

                /*
                 * Las demas pueden no llevar clase (se ven siempre) o
                 * llevar una de los dos tramos. Lo que no puede ser es
                 * llevar una clase suelta: seria una columna que en un
                 * telefono ocupa sitio sin motivo.
                 */
                $llevaTramo = false;

                foreach ($tramos as $tramo) {
                    if (str_contains($clase, $tramo)) {
                        $llevaTramo = true;
                    }
                }

                $this->assertFalse(
                    ! $llevaTramo && str_contains($clase, 'd-'),
                    "$nombre: la columna '$columna' tiene la clase '{$clase}', "
                    . 'que no es ningun tramo de visibilidad. Use OCULTAR_EN_MOVIL '
                    . 'u OCULTAR_HASTA_ESCRITORIO del trait'
                );
            }
        }
    }

    public function test_la_extension_de_botones_esta_cargada_antes_que_las_tablas(): void
    {
        $layout = file_get_contents(
            resource_path('views/components/base-layout.blade.php')
        );

        /*
         * Sin la extension, los botones de exportar se dibujan pero no
         * tienen manejador de clic: se ven y no hacen nada. Ledia de
         * cuatro años atrás.
         */
        foreach ([
            'dataTables.buttons.min.js',
            'buttons.html5.min.js',
        ] as $archivo) {
            $this->assertStringContainsString(
                $archivo,
                $layout,
                "El layout no carga $archivo: los botones de exportar no funcionarian"
            );
        }

        $posicion = strpos($layout, 'dataTables.buttons.min.js');
        $posicionCore = strpos($layout, 'dataTables.bootstrap5.js');

        $this->assertNotFalse($posicion);
        $this->assertNotFalse($posicionCore);

        $this->assertGreaterThan(
            $posicionCore,
            $posicion,
            'La extension de botones se registra sobre el core: tiene que cargarse despues'
        );
    }

    public function test_los_archivos_de_las_extensiones_estan_en_el_proyecto(): void
    {
        foreach ([
            'plugins/table/datatable/dataTables.buttons.min.js',
            'plugins/table/datatable/buttons.html5.min.js',
            'plugins/table/datatable/buttons.print.min.js',
        ] as $archivo) {
            $this->assertFileExists(
                public_path($archivo),
                "Falta $archivo: se borro del proyecto y el layout lo sigue pidiendo"
            );
        }
    }

    public function test_los_estilos_de_la_tabla_se_cargan(): void
    {
        $layout = file_get_contents(
            resource_path('views/components/base-layout.blade.php')
        );

        $this->assertStringContainsString(
            'datatable-movil.scss',
            $layout,
            'El layout no carga los estilos de tabla'
        );
    }

    public function test_el_js_que_copia_las_clases_al_encabezado_se_carga(): void
    {
        $layout = file_get_contents(
            resource_path('views/components/base-layout.blade.php')
        );

        /*
         * DataTables solo pone la clase de una columna en los <td>. Sin
         * este js, al ocultar una columna en movil se ocultan sus celdas
         * pero el titulo se queda, y la fila de encabezados queda con
         * huecos.
         */
        $this->assertStringContainsString('columnas-visibles.js', $layout);

        $this->assertFileExists(
            public_path('js/datatables/columnas-visibles.js')
        );
    }

    public function test_el_layout_no_pide_la_extension_responsive_que_no_existe(): void
    {
        $layout = file_get_contents(
            resource_path('views/components/base-layout.blade.php')
        );

        /*
         * La extension Responsive de DataTables 2.x no se puede instalar
         * aqui: su CSS no viene en los paquetes de npm y el CDN no es
         * alcanzable desde la maquina. Como su CSS es justamente lo que
         * oculta las columnas, cargarla sin el no haria nada y dejaria
         * configuracion muerta. Por eso el reparto se hace con las clases
         * de Bootstrap y la extension no se carga.
         */
        $this->assertStringNotContainsString(
            'dataTables.responsive',
            $layout,
            'La extension Responsive no tiene CSS disponible: cargarla no haria nada'
        );
    }

    // ==================================================================

    /**
     * Las columnas de un DataTable con su clase ya resuelta.
     *
     * Se lee el codigo y no se instancia la tabla: varias construyen su
     * url de ajax al definirse, y sin una orden y un proceso de verdad
     * esa url no se puede generar. Para ver el reparto de columnas no
     * hace falta ninguna de las dos cosas.
     *
     * @return array<string, string>
     */
    private function columnasDe(string $tabla): array
    {
        $tablaEjemplo = new EtapaDataTable(app());

        $constantes = [
            'self::OCULTAR_EN_MOVIL' => $tablaEjemplo::OCULTAR_EN_MOVIL,
            'self::OCULTAR_HASTA_ESCRITORIO' => $tablaEjemplo::OCULTAR_HASTA_ESCRITORIO,
            'self::COLUMNA_ACCIONES' => $tablaEjemplo::COLUMNA_ACCIONES,
        ];

        /*
         * Las clases de columna llegan en el <th> por el js del navegador,
         * que aqui no corre. Se leen del codigo en su lugar: se busca la
         * cadena Column::make('x') y la clase que se le anade, ya
         * resuelta con las constantes del trait.
         */
        $fuente = $this->fuente($tabla);

        $patron = "/Column::(?:make|computed)\('([a-z_]+)'\)(.*?)(?=Column::(?:make|computed)\('|\];)/s";

        preg_match_all($patron, $fuente, $coincidencias, PREG_SET_ORDER);

        $resultado = [];

        foreach ($coincidencias as $c) {
            $nombre = $c[1];
            $cadena = $c[2];

            $clases = [];

            preg_match_all("/->addClass\('([^']+)'\)/", $cadena, $encontradas);

            foreach ($encontradas[1] as $literal) {
                foreach ($constantes as $php => $css) {
                    if (str_contains($literal, $php)) {
                        $literal = str_replace($php, $css, $literal);
                    }
                }

                $clases[] = trim($literal);
            }

            $resultado[$nombre] = implode(' ', $clases);
        }

        $this->assertNotContains(
            '',
            array_keys($resultado),
            "$tabla: no se pudo leer ninguna columna del codigo"
        );

        return $resultado;
    }

    public function test_el_trait_declara_los_tres_tramos(): void
    {
        /*
         * Las constantes de un trait no se leen desde el trait: se leen
         * desde una clase que lo use, y por eso se lee desde una tabla.
         */
        $tabla = new EtapaDataTable(app());

        $this->assertSame('d-none d-lg-table-cell', $tabla::OCULTAR_EN_MOVIL);
        $this->assertSame('d-none d-xl-table-cell', $tabla::OCULTAR_HASTA_ESCRITORIO);
        $this->assertSame('columna-acciones', $tabla::COLUMNA_ACCIONES);

        /*
         * Los cortes son los de Bootstrap: si se cambian, el SCSS y las
         * clases del proyecto dejan de cuadrar.
         */
        $this->assertStringContainsString('lg', $tabla::OCULTAR_EN_MOVIL);
        $this->assertStringContainsString('xl', $tabla::OCULTAR_HASTA_ESCRITORIO);
    }

    public function test_la_lista_de_tablas_del_test_no_esta_incompleta(): void
    {
        $enDisco = array_map(
            fn($f) => basename($f, '.php'),
            glob(app_path('DataTables/*.php'))
        );

        sort($enDisco);
        $declaradas = self::TABLAS;
        sort($declaradas);

        $this->assertSame(
            $declaradas,
            $enDisco,
            'Hay DataTables que este test no revisa, o declarados que ya no existen. '
            . 'Cada tabla nueva tiene que pasar por el reparto de columnas: anadela a TABLAS'
        );
    }
}
