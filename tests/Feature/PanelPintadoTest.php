<?php
/**
 * El panel se pinta. Una prueba que mira el html, no los numeros.
 *
 * ---------------------------------------------------------------------------
 * POR QUÉ HACE FALTA SI YA HAY PanelTest
 * ---------------------------------------------------------------------------
 *
 * Porque PanelTest mira lo que el panel CALCULA y este mira lo que el panel
 * PINTA, y son dos cosas que se rompen de forma distinta.
 *
 * Un número mal calculado sale un número raro en la tarjeta y se ve enseguida. Un
 * html mal escrito —un @if sin cerrar, una llave de blade que no cuadra, un
 * atributo mal escrito— no sale nada: la pantalla da un error de sintaxis de php
 * en un archivo temporal con un número de línea que no corresponde con el blade que
 * se escribió, y el error aparece al abrir la página, no al correr las pruebas.
 *
 * Y hay un segundo motivo que es el que más ha merecido la pena: LOS ARCHIVOS QUE
 * CARGA. Una etiqueta mal escrita que apunta a "js/dashboard/panel.js" cuando el
 * archivo se llama "panel2.js" no da ningún error. La página sale entera, con las
 * tarjetas y las listas, y sin gráficas, porque el archivo no se encuentra. Y eso
 * no se ve hasta que alguien abre el panel en el taller y pregunta por qué no sale
 * el círculo de los estados.
 *
 * ---------------------------------------------------------------------------
 * POR QUÉ SIEMBRA DATOS Y NO COMPRUEBA UNA PANTALLA VACÍA
 * ---------------------------------------------------------------------------
 *
 * Porque una pantalla vacía es la que menos falla: sin filas no hay celdas que
 * impar, sin lista no hay bucle, sin barras no hay porcentaje que se corte. Los
 * fallos de una vista están casi siempre en el camino con contenido.
 *
 * Se siembra dentro de la transacción de la prueba, que se deshace al terminar.
 *
 * ---------------------------------------------------------------------------
 * NUNCA SE CUENTA LA TABLA ENTERA
 * ---------------------------------------------------------------------------
 *
 * La base es la de verdad y el taller la usa. Cada cuenta que se comprueba es la
 * diferencia entre antes y después de sembrar, y las fechas del filtro de periodo
 * se ponen explícitamente en el mes en curso, para que la prueba no dependa del día
 * en que se ejecuta.
 */

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PanelPintadoTest extends TestCase
{
    use DatabaseTransactions;

    private const RAIZ = 'C:/xampp/htdocs/mina';

    protected function setUp(): void
    {
        parent::setUp();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'panel-pintado-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);

        $this->actingAs($admin);
    }

    // ==================================================================
    // El html sale entero
    // ==================================================================

    public function test_el_html_del_panel_no_se_corta_en_ningun_lado(): void
    {
        $html = $this->panel();

        /*
         * Un blade con un @if sin cerrar compila pero deja el html a medias, y el
         * sintoma es que las tarjetas de abajo no salen y no hay ningun error: la
         * pagina devuelve 200 y parece que those cards no existen.
         *
         * Y el cierre del body y del html tienen que estar los dos. Un layout mal
         * cerrado en un movil hace cosas raras que son muy dificil deackbar en el
         * navegador.
         */
        $this->assertStringContainsString('</body>', $html);
        $this->assertStringContainsString('</html>', $html);

        // Y las cuatro secciones, de arriba abajo. En orden, porque el orden es la
        // decision de diseño del panel y no un detalle.
        $posiciones = [];

        foreach (['Órdenes de este mes', 'Dinero de este mes', 'Para hoy'] as $titulo) {
            $posiciones[$titulo] = mb_strpos($html, $titulo);

            $this->assertNotFalse(
                $posiciones[$titulo],
                'La sección "' . $titulo . '" no sale en el panel.'
            );
        }

        $this->assertLessThan(
            $posiciones['Dinero de este mes'],
            $posiciones['Órdenes de este mes'],
            'Las órdenes tienen que ir antes que el dinero.'
        );

        $this->assertLessThan(
            $posiciones['Para hoy'],
            $posiciones['Dinero de este mes'],
            'El dinero tiene que ir antes que las listas.'
        );
    }

    // ==================================================================
    // Los titulos, que es donde se rompio el idioma
    // ==================================================================

    public function test_los_titulos_de_las_tarjetas_estan_en_plural(): void
    {
        $html = $this->panel();

        foreach (['Pendientes', 'En proceso', 'Finalizadas', 'Canceladas'] as $titulo) {
            $this->assertStringContainsString($titulo, $html);
        }

        /*
         * Y el caso que mas se rompe: un nombre en singular detras de un plural.
         *
         * La primera version de este bloque hacia "Procesos " mas el nombre del
         * estado en minuscula, que es lo automatico, y salia "Procesos finalizado" y
         * "Procesos cancelado". Un titulo mal dicho no rompe la pagina, y por eso se
         * cuela: nadie lo ve hasta que lo lee alguien.
         */
        foreach (['Procesos finalizado', 'Procesos cancelado', 'Procesos pendiente'] as $mal) {
            $this->assertStringNotContainsString(
                '>' . $mal . '<',
                $html,
                'Un nombre en singular detrás de "Procesos": "' . $mal . '".'
            );
        }

        foreach (['Sin empezar', 'Terminados', 'Cancelados'] as $titulo) {
            $this->assertStringContainsString($titulo, $html);
        }
    }

    // ==================================================================
    // Las listas, con contenido
    // ==================================================================

    public function test_las_listas_se_pintan_con_sus_filas(): void
    {
        $html = $this->panel();

        $filasAntes = $this->cuentaFilas($html);

        $this->sembrar();

        $html = $this->panel();

        /*
         * Una fila por cada cosa sembrada, en la lista que le toca.
         *
         * Y se cuenta la diferencia y no el total, porque el taller ya tiene una
         * orden abierta y esa sale en dos listas —la de abiertas y la de ultimas—.
         * Un assertSame sobre el numero absoluto pasaria hoy y fallaria el dia que
         * el taller meta su segunda orden, sin que haya cambiado el panel.
         */
        $this->assertSame(
            $filasAntes + 3,
            $this->cuentaFilas($html),
            'Cada fila sembrada tiene que salir una vez en su lista: un proceso en la de atrasados, '
                . 'y la orden en la de abiertas y en la de últimas.'
        );
    }

    /**
     * Cuantas filas pintan las tres listas del panel.
     */
    private function cuentaFilas(string $html): int
    {
        return preg_match_all('/<li class="border-bottom px-3 py-2">/', $html);
    }

    public function test_la_barra_de_avance_va_a_medias_cuando_falta_un_proceso(): void
    {
        $this->sembrar();

        $html = $this->panel();

        /*
         * La orden sembrada tiene dos procesos: uno acabado y otro sin acabar. La
         * barra tiene que decir 50, no 100 —que seria lo que sale contando filas con
         * fecha de fin puesta sin mirar si la fecha ya paso— ni 0, que seria lo que
         * sale contando con la columna `estado` del proceso, que va atrasada.
         */
        $this->assertMatchesRegularExpression(
            '/aria-valuenow="50"/',
            $html,
            'La barra de avance de una orden con un proceso de dos sin acabar tiene que decir 50.'
        );
    }

    public function test_el_proceso_sin_terminar_sale_con_los_dias_que_lleva(): void
    {
        $this->sembrar();

        $html = $this->panel();

        /*
         * Los dias van en una pastilla. Si la lista se pinta pero el numero no, el
         * taller ve que hay procesos a medias y no cuanto llevan, que es justo lo
         * que estaba mirando.
         */
        $this->assertMatchesRegularExpression(
            '/aria-label="|title="[^"]*(d[ií]a|d[ií]as) sin terminar"/',
            $html,
            'La pastilla de días sin terminar tiene que estar en el html.'
        );
    }

    // ==================================================================
    // Lo que la pantalla carga de verdad
    // ==================================================================

    public function test_los_archivos_que_carga_el_panel_existen_de_verdad(): void
    {
        $html = $this->panel();

        /*
         * Se comprueban SOLO los archivos del panel —los que salen por sus slots de
         * headerFiles y footerFiles— y no todos los del html.
         *
         * Motivo: el layout carga tambien los de Vite, y en desarrollo esos los
         * sirve el servidor de Vite y no la carpeta public. En el html salen como
         * "http://localhost/build/assets/main-XXXX.css", sin la carpeta del
         * proyecto, y buscarlos en public/ daria que faltan todos. Y el nombre lleva
         * un hash que cambia en cada compilación, asi que tampoco se puede
         * comprobar contra una lista fija.
         *
         * Lo que si se puede comprobar, y es lo que importa, es que los archivos que
         * esta vista declara por su cuenta existen. Un archivo que no existe no da
         * ningún error: la pagina sale entera, con las tarjetas y las listas, y sin
         * gráficas. Y eso no se ve hasta que alguien abre el panel en el taller.
         */
        $propios = [];

        if (preg_match('#href="[^"]*(plugins/apex/[^"]+\.css)"#', $html, $m)) {
            $propios[] = $m[1];
        }

        if (preg_match('#src="[^"]*(plugins/apex/[^"]+\.js)"#', $html, $m)) {
            $propios[] = $m[1];
        }

        if (preg_match('#src="[^"]*(js/dashboard/[^"]+\.js)"#', $html, $m)) {
            $propios[] = $m[1];
        }

        $this->assertCount(
            3,
            $propios,
            'El panel deberia declarar su hoja de estilo, la libreria de graficas y su javascript.'
        );

        $faltan = [];

        foreach ($propios as $ruta) {
            if (! file_exists(self::RAIZ . '/public/' . $ruta)) {
                $faltan[] = $ruta;
            }
        }

        $this->assertSame(
            [],
            $faltan,
            'El panel carga estos archivos y no existen: ' . implode(', ', $faltan)
            . '. La página saldrá entera pero sin gráficas y sinAviso de nada.'
        );
    }

    public function test_el_panel_carga_la_libreria_de_graficas_y_su_javascript(): void
    {
        $html = $this->panel();

        $this->assertStringContainsString(
            'apexcharts.min.js',
            $html,
            'Sin ApexCharts no hay gráficas, y sin gráficas el bloque queda con un hueco en blanco.'
        );

        $this->assertStringContainsString('js/dashboard/panel.js', $html);
    }

    public function test_el_contenedor_de_la_grafica_tiene_texto_por_si_no_carga(): void
    {
        $html = $this->panel();

        /*
         * Un contenedor de gráfica vacio es un rectángulo en blanco del tamaño
         * equivocado, que se lee como un fallo de la pantalla. Y si ApexCharts no
         * carga —una ruta mal puesta, un archivo que no se sube— el texto se queda a
         * la vista y por lo menos dice algo.
         */
        $this->assertMatchesRegularExpression(
            '/id="graficaEstados"[^>]*>\s*<p[^>]*>\s*Cargando/',
            $html
        );

        $this->assertMatchesRegularExpression(
            '/id="graficaMeses"[^>]*>\s*<p[^>]*>\s*Cargando/',
            $html
        );
    }

    // ==================================================================
    // El divisor de las columnas
    // ==================================================================

    public function test_las_columnas_se_reparten_para_que_cupan_en_un_movil(): void
    {
        $html = $this->panel();

        /*
         * En un movil lo esencial son las cifras y las listas, y tiene que haber dos
         * tarjetas de numero por fila: con una sola, la pantalla se alarga el doble y
         * el taller tiene que desplazarse para ver un panel de cuatro lineas.
         *
         * Las clases del reparto van en el mismo orden en toda la pantalla —la
         * caja deBootstrap empieza por la mas estrecha—, asi que "col-6 col-md-4"
         * significa dos por fila en movil y tres en tablet. Y hay que mirar que
         * las tarjetas de dinero, que son las anchas, digan col-12 y no col-6: el
         * importe de un costo con cuatro decimales no cabe en media pantalla.
         */
        $this->assertStringContainsString('class="col-6 col-md-4 col-xl-3"', $html);

        // Las de dinero, en una columna en movil
        $this->assertGreaterThanOrEqual(
            3,
            preg_match_all('/class="col-12 col-lg-4"/', $html),
            'Las tarjetas de dinero tienen que ocupar la fila entera en un móvil.'
        );

        // Y las listas, tambien, porque tienen una linea por elemento
        $this->assertGreaterThanOrEqual(
            3,
            preg_match_all('/class="col-12 col-lg-4"/', $html)
        );
    }

    // ==================================================================
    // Las clases, que es lo que no falla nunca y por eso no se ve
    // ==================================================================

    public function test_las_clases_del_panel_existen_de_verdad(): void
    {
        /*
         * Una clase de css que no existe NO DA ERROR. No sale nada en la consola, la
         * pagina devuelve 200, el elemento sale sin ese estilo y no hay forma de
         * saber que falta nada salvo que se este mirando.
         *
         * Es lo que pasó con "widget-card", que no existe en ninguna parte: solo
         * aparece en el nombre de unos ficheros scss del tema, de modo que se
         * encuentra buscando el texto en el archivo y no como clase. Con ella las
         * tarjetas salian sin fondo ni bordes y el panel se veia como un formulario
         * suelto. Y "avatar-xs" tampoco existe —los tamaños del tema son sm, lg y
         * xl—, y salia del tamaño de bootstrap sin el estilo del circulito.
         *
         * Las dos se encontraron revisando el html a mano, que es la unica forma de
         * que se encuentre un fallo asi. Este test es para que no haya que
         * revisarlo a mano otra vez.
         *
         * Y se buscan en las hojas COMPILADAS, que es donde estan, y no en los
         * ficheros scss del tema, que es donde se encuentran los nombres de archivo
         * y no las clases.
         */
        $css = $this->cssDelTema();

        foreach (['br-8', 'avatar', 'avatar-sm', 'layout-top-spacing'] as $clase) {
            $this->assertMatchesRegularExpression(
                '/\.' . preg_quote($clase, '/') . '(?![\w-])/',
                $css,
                'La clase ".' . $clase . '" no está en ninguna hoja de estilos. '
                    . 'El elemento saldrá sin ese estilo y nada lo avisará.'
            );
        }

        // Y que el panel no use ninguna clase propia del tema que no sea de las suyas
        $html = $this->panel();

        preg_match_all('/class="([^"]+)"/', $html, $coincidencias);

        $propias = [];

        foreach ($coincidencias[1] as $atributo) {
            foreach (preg_split('/\s+/', $atributo) as $clase) {
                // Las del panel y las de bootstrap no se comprueban aqui
                if (str_starts_with($clase, 'panel-') || str_starts_with($clase, 'col-')) {
                    continue;
                }

                $propias[$clase] = true;
            }
        }

        /*
         * Estas cuatro son las que se inventaron al escribir el panel y no existen.
         * Se dejan escritas a proposito: si alguien las vuelve a usar un dia, el
         * test lo dice en el acto en vez de dejar la pagina medio estilo.
         */
        foreach (['widget-card', 'avatar-xs', 'last-child-border-0', 'br-8-xs'] as $inventada) {
            if (isset($propias[$inventada])) {
                continue;
            }

            $this->assertStringNotContainsString(
                '.' . $inventada . ' ',
                $css,
                'Se ha vuelto a usar "' . $inventada . '", que no existe en el tema.'
            );
        }

        $this->assertArrayNotHasKey(
            'widget-card',
            $propias,
            'La clase "widget-card" no existe en el tema. Sale en el nombre de ficheros scss, '
                . 'que es donde aparece el texto, y no como clase.'
        );
    }

    public function test_los_iconos_del_panel_existen_en_bootstrap_icons(): void
    {
        /*
         * Un icono que no existe tampoco da error: sale el nombre del icono escrito en
         * vez del dibujo. Y en una tarjeta pequeña, donde el icono es del tamaño de
         * una letra, eso se ve como un texto suelto que nadie sabe de donde sale.
         */
        $css = '';

        foreach ([
            self::RAIZ . '/node_modules/bootstrap-icons/font/bootstrap-icons.css',
        ] as $ruta) {
            if (file_exists($ruta)) {
                $css .= file_get_contents($ruta);
            }
        }

        $this->assertNotEmpty($css, 'No se encuentra la hoja de bootstrap-icons.');

        $html = $this->panel();

        preg_match_all('/class="bi bi-([\w-]+)"/', $html, $coincidencias);

        $this->assertNotEmpty($coincidencias[1], 'El panel no usa ningún icono.');

        foreach (array_unique($coincidencias[1]) as $icono) {
            $this->assertMatchesRegularExpression(
                '/\.bi-' . preg_quote($icono, '/') . '(?![\w-])/',
                $css,
                'El icono "bi-' . $icono . '" no existe. Saldrá su nombre escrito en vez del dibujo.'
            );
        }
    }

    /**
     * Todas las hojas de estilos compiladas del proyecto, en una sola cadena.
     *
     * Se leen todas y no solo una porque la hoja del tema esta troceada en tres
     *cientas y una clase puede estar en cualquiera. Y se busca en las compiladas, que
     * es donde se defines las clases, y no en los ficheros scss, que es donde estan
     * los nombres de archivo.
     */
    private function cssDelTema(): string
    {
        $css = '';

        $carpetas = [
            self::RAIZ . '/public/build/assets',
            self::RAIZ . '/public/plugins',
        ];

        foreach ($carpetas as $carpeta) {
            if (! is_dir($carpeta)) {
                continue;
            }

            $archivos = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($carpeta, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($archivos as $archivo) {
                if (strtolower($archivo->getExtension()) !== 'css') {
                    continue;
                }

                $css .= file_get_contents($archivo->getPathname());
            }
        }

        $this->assertNotEmpty($css, 'No se encuentra ninguna hoja de estilos del tema.');

        return $css;
    }

    public function test_las_tarjetas_de_color_no_dejan_texto_gris(): void
    {
        $html = $this->panel();

        /*
         * El fallo que el usuario vio: las tarjetas llevan el color del estado como
         * fondo y la letra iba en "text-secondary", que es un gris. Con el azul del
         * tema, ese gris da un contraste de 1,04 sobre 1, y 1 sobre 1 es lo que da un
         * color consigo mismo: invisible.
         *
         * Y no daba error. Una letra que no se ve no falla ningún análisis, no sale
         * nada en la consola y la página devuelve 200. El número grande se veía
         * porque iba en negro y los otros dos textos —el título y el pie, que es
         * donde se explica qué significa el número— no se veían nada.
         *
         * Se busca "text-secondary" y "text-muted" dentro de una tarjeta de color. Y
         * no vale con que no aparezcan: una tarjeta con el color puesto en el html y
         * la letra gris puesta en otro sitio se cuela igual. Por eso se saca cada
         * tarjeta y se mira su contenido entero.
         */
        /*
         * Y el patrón tiene que ser "<a [^>]*class=" y no "<a class=". La tarjeta
         * que lleva enlace pone el href antes de la clase, y la que no lo lleva es
         * un div. Con el patrón corto no se encontraba ninguna tarjeta y el test
         * fallaba por no encontrar lo que buscaba —que es distinto de que no haya
         * nada—.
         */
        preg_match_all(
            '#<[ad] [^>]*class="card br-8 mb-3 bg-\w+[^"]*"[^>]*>(.*?)</[ad]>#s',
            $html,
            $coincidencias
        );

        $this->assertNotEmpty(
            $coincidencias[1],
            'No se ha encontrado ninguna tarjeta de color en el panel.'
        );

        $conGris = [];

        foreach ($coincidencias[1] as $cuerpo) {
            foreach (['text-secondary', 'text-muted'] as $gris) {
                if (str_contains($cuerpo, $gris)) {
                    $conGris[] = $gris;
                }
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($conGris)),
            'Hay tarjetas de color con la letra en gris: ' . implode(', ', array_unique($conGris))
            . '. Sobre un fondo de color, el gris del tema no se lee.'
        );

        /*
         * Y lo de arriba: cada tarjeta de color tiene que llevar el color de la letra
         * escrito en el style. Es lo que hace que la tarjeta se lea, y así no depende
         * de que una clase que no exista haga su trabajo.
         */
        foreach ($coincidencias[1] as $cuerpo) {
            $this->assertMatchesRegularExpression(
                '/style="[^"]*color:\s*#(ffffff|151a26)/',
                $cuerpo,
                'Una tarjeta de color no lleva el color de la letra escrito en el estilo, '
                    . 'así que su texto sale con el color por defecto del tema.'
            );
        }
    }

    // ==================================================================
    // Ayudas
    // ==================================================================

    private function panel(): string
    {
        return $this->get('/')->assertOk()->getContent();
    }

    /**
     * Datos de revision, dentro de la transaccion de la prueba.
     *
     * Las fechas se ponen en el mes que se esta ejecutando, no con "hace X dias",
     * porque el periodo por defecto es este mes y una fecha de hace dos semanas cae
     * fuera en cuanto el dia del mes es mayor que catorce. Una prueba que depende
     * del dia en que se ejecuta no mide el codigo, mide el calendario.
     */
    private function sembrar(): void
    {
        $esteMes = now()->startOfMonth();

        $ordenId = DB::table('ordenes_trabajo')->insertGetId([
            'codigo' => 'PINTADO-1',
            'cliente_id' => DB::table('clientes')->first()->id,
            'fecha' => $esteMes->toDateString(),
            'descripcion' => 'Orden de revision del pintado',
            'peso_mineral' => 12.5,
            'unidad_peso' => 'g',
            'estado' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $etapas = DB::table('etapas')->take(2)->get();

        DB::table('procesos_orden')->insert([
            [
                'orden_trabajo_id' => $ordenId,
                'etapa_id' => $etapas->first()->id,
                // Empezado ayer y acabado ayer: terminado de verdad
                'fecha_inicio' => $esteMes->copy()->addDays(1)->setTime(9, 0)->toDateTimeString(),
                'fecha_fin' => $esteMes->copy()->addDays(1)->setTime(17, 0)->toDateTimeString(),
                'estado' => 1,
                'codigo' => 'P-PINT-1',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'orden_trabajo_id' => $ordenId,
                'etapa_id' => $etapas->last()->id,
                // Empezado y sin acabar: el que sale en la lista de atrasados
                'fecha_inicio' => $esteMes->copy()->setTime(9, 0)->toDateTimeString(),
                'fecha_fin' => null,
                'estado' => 1,
                'codigo' => 'P-PINT-2',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
