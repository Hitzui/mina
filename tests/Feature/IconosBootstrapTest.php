<?php

namespace Tests\Feature;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Un solo juego de iconos en toda la aplicacion: Bootstrap Icons.
 *
 * El proyecto venia mezclando Font Awesome con Bootstrap Icons, y ademas
 * tenia cinco iconos rotos: nombres de Font Awesome a los que solo se les
 * habia cambiado el prefijo a "bi-", asi que no existian y se veian como
 * un recuadro vacio.
 *
 * Este test comprueba las dos cosas: que no vuelva Font Awesome, y que
 * todo icono que se use exista de verdad en la libreria instalada.
 */
class IconosBootstrapTest extends TestCase
{
    /**
     * Galerias de iconos del tema. Enseñan Font Awesome a proposito y no
     * son parte de la aplicacion, asi que se dejan como estan.
     */
    private const EXCLUIDOS = ['pages/component/fonticons', 'pages-rtl'];

    /**
     * @return string[] rutas relativas a resources/views
     */
    private function vistasDeLaApp(): array
    {
        $raiz = dirname(__DIR__, 2) . '/resources/views';
        $vistas = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $archivo) {
            if (!$archivo->isFile() || !str_ends_with($archivo->getFilename(), '.blade.php')) {
                continue;
            }

            $relativo = ltrim(
                str_replace('\\', '/', substr($archivo->getPathname(), strlen($raiz))),
                '/'
            );

            foreach (self::EXCLUIDOS as $excluido) {
                if (str_starts_with($relativo, $excluido)) {
                    continue 2;
                }
            }

            $vistas[] = $relativo;
        }

        sort($vistas);

        return $vistas;
    }

    private function leer(string $relativo): string
    {
        return (string) file_get_contents(
            dirname(__DIR__, 2) . '/resources/views/' . $relativo
        );
    }

    /**
     * Nombres de icono que define bootstrap-icons, sin el prefijo "bi-".
     *
     * @return string[]
     */
    private function iconosDeLaLiberia(): array
    {
        $css = (string) file_get_contents(
            public_path('plugins/bootstrap-icon/bootstrap-icons.min.css')
        );

        preg_match_all('/\.bi-([a-z0-9-]+)::before/', $css, $m);

        return array_values(array_unique($m[1]));
    }

    public function test_hay_vistas_que_revisar(): void
    {
        $this->assertGreaterThan(100, count($this->vistasDeLaApp()));
    }

    public function test_ninguna_vista_usa_font_awesome(): void
    {
        $infractores = [];

        foreach ($this->vistasDeLaApp() as $vista) {
            $html = $this->leer($vista);

            /*
             * Font Awesome se escribe como prefijo + nombre: "fa-solid fa-x",
             * "fas fa-x", "far fa-x", "fal fa-x". El "fa-" del nombre es
             * obligatorio en el patron, porque si no la palabra inglesa
             * "far" de un texto de ejemplo ("so far") cuenta como icono.
             */
            $usaIcono = '/\b(fas|far|fal|fa-solid|fa-regular|fa-light)\s+fa-[a-z]/';

            if (preg_match($usaIcono, $html)) {
                $infractores[] = $vista;
                continue;
            }

            // Y un fa- suelto dentro de una clase tambien cuenta
            if (preg_match('/class="[^"]*\bfa-[a-z]/', $html)) {
                $infractores[] = $vista;
            }
        }

        $this->assertSame(
            [],
            $infractores,
            "Estas vistas usan Font Awesome: " . implode(', ', $infractores)
        );
    }

    /**
     * Este es el que habria atrapado los cinco iconos rotos: un nombre de
     * Font Awesome con el prefijo "bi-" parece correcto pero no existe.
     */
    public function test_todos_los_iconos_existen_en_la_libreria(): void
    {
        $disponibles = $this->iconosDeLaLiberia();

        $this->assertNotEmpty($disponibles, 'No se pudo leer el CSS de bootstrap-icons');

        $rotos = [];

        foreach ($this->vistasDeLaApp() as $vista) {
            $html = $this->leer($vista);

            // Solo dentro de clases: <i class="bi bi-algo">
            preg_match_all('/class="([^"]*\bbi\b[^"]*)"/', $html, $clases);

            foreach ($clases[1] as $clase) {
                preg_match_all('/\bbi-([a-z0-9-]+)\b/', $clase, $iconos);

                foreach ($iconos[1] as $icono) {
                    if (!in_array($icono, $disponibles, true)) {
                        $rotos[] = "bi-$icono en $vista";
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $rotos,
            "Iconos que no existen en bootstrap-icons: " . implode(', ', $rotos)
        );
    }

    /**
     * Bootstrap Icons necesita dos clases: la base "bi" (que aporta la
     * fuente) y el icono "bi-algo". Se comprueban los dos errores reales:
     *
     * - "bi-algo" sin la clase base "bi": no se dibuja nada.
     * - "bi bi-" con el nombre vacio.
     */
    public function test_las_clases_de_icono_tienen_la_sintaxis_correcta(): void
    {
        $malas = [];

        foreach ($this->vistasDeLaApp() as $vista) {
            $html = $this->leer($vista);

            preg_match_all('/class="([^"]*)"/', $html, $clases);

            foreach ($clases[1] as $clase) {
                // Se parte en tokens: buscar "bi" dentro de una palabra
                // como "add-billing-address" daria un falso positivo
                $tokens = preg_split('/\s+/', trim($clase), -1, PREG_SPLIT_NO_EMPTY);

                $iconos = array_values(array_filter(
                    $tokens,
                    fn($t) => str_starts_with($t, 'bi-')
                ));

                if ($iconos === []) {
                    continue;
                }

                $tieneBase = in_array('bi', $tokens, true);

                foreach ($iconos as $icono) {
                    if (!$tieneBase) {
                        $malas[] = "$vista: sin la clase base \"bi\" -> \"$clase\"";
                        break;
                    }

                    if ($icono === 'bi-') {
                        $malas[] = "$vista: icono sin nombre -> \"$clase\"";
                    } elseif (!preg_match('/^bi-[a-z0-9-]+$/', $icono)) {
                        $malas[] = "$vista: nombre de icono raro -> \"$icono\"";
                    }
                }
            }
        }

        $this->assertSame([], $malas, implode('; ', $malas));
    }

    /**
     * Las galerias del tema no se tocan: son una demo de Font Awesome.
     */
    public function test_las_galerias_del_tema_no_se_tocaron(): void
    {
        $raiz = dirname(__DIR__, 2) . '/resources/views/pages/component/fonticons.blade.php';

        $this->assertFileExists($raiz);

        $html = (string) file_get_contents($raiz);

        $this->assertGreaterThan(
            10,
            preg_match_all('/fa-solid|fa-regular|fas fa-|far fa-/', $html),
            'La galeria del tema deberia seguir con Font Awesome'
        );
    }
}
