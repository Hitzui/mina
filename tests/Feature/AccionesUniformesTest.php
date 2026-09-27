<?php

namespace Tests\Feature;

use FilesystemIterator;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Los botones de accion de los listados deben verse todos igual: mismo
 * contenedor, mismos colores, mismos iconos.
 *
 * El proyecto venia mezclando Font Awesome con Bootstrap Icons, asi que
 * cada listado se veia distinto. Bootstrap Icons es el que se quedo: es
 * el que usa el tema y el que ya traia el resto.
 */
class AccionesUniformesTest extends TestCase
{
    /**
     * Rutas de los partials de acciones, relativas a resources/views.
     *
     * El data provider corre antes de que arranque Laravel, asi que aqui
     * no se puede usar resource_path() ni los facades: solo PHP plano.
     *
     * @return array<string, array{0: string}>
     */
    public static function partialsDeAcciones(): array
    {
        $raizViews = dirname(__DIR__, 2) . '/resources/views';
        $casos = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($raizViews, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $archivo) {
            if (!$archivo->isFile() || !str_ends_with($archivo->getFilename(), '.blade.php')) {
                continue;
            }

            $nombre = $archivo->getFilename();

            if (!str_contains($nombre, 'action')) {
                continue;
            }

            $relativo = ltrim(
                str_replace('\\', '/', substr($archivo->getPathname(), strlen($raizViews))),
                '/'
            );

            $casos[$relativo] = [$relativo];
        }

        ksort($casos);

        return $casos;
    }

    private function leerPartial(string $relativo): string
    {
        return File::get(resource_path('views/' . $relativo));
    }

    public function test_hay_partials_de_acciones_que_revisar(): void
    {
        $this->assertGreaterThanOrEqual(
            8,
            count(static::partialsDeAcciones()),
            'Se esperaban al menos 8 partials de acciones'
        );
    }

    #[DataProvider('partialsDeAcciones')]
    public function test_las_acciones_no_usan_font_awesome(string $relativo): void
    {
        $html = $this->leerPartial($relativo);

        $this->assertDoesNotMatchRegularExpression(
            '/class="[^"]*\b(fas|far|fal|fa-solid|fa-regular)\b/',
            $html,
            "$relativo todavia usa iconos de Font Awesome"
        );
    }

    #[DataProvider('partialsDeAcciones')]
    public function test_las_acciones_usan_bootstrap_icons(string $relativo): void
    {
        $html = $this->leerPartial($relativo);

        $this->assertMatchesRegularExpression(
            '/<i class="bi bi-/',
            $html,
            "$relativo deberia usar iconos de Bootstrap Icons"
        );
    }

    #[DataProvider('partialsDeAcciones')]
    public function test_los_botones_de_accion_tienen_titulo(string $relativo): void
    {
        $html = $this->leerPartial($relativo);

        // Antes de buscar etiquetas se quitan dos cosas que rompen el
        // barrido de "<a ...>" y "<button ...>":
        //
        // - los bloques @php, que no son HTML;
        // - las expresiones {{ ... }}, porque dentro hay operadores como
        //   $trabajo->id cuyo ">" cerraba la etiqueta a media lectura y
        //   hacia creer que al boton le faltaba el title.
        $html = preg_replace('/@php.*?@endphp/s', '', $html);
        $html = preg_replace('/\{\{.*?\}\}/s', '{{ }}', $html);

        preg_match_all('/<(a|button)\b[^>]*class="[^"]*btn btn-sm[^"]*"[^>]*>/', $html, $botones);

        $this->assertNotEmpty($botones[0], "$relativo no tiene botones de accion");

        foreach ($botones[0] as $boton) {
            $this->assertStringContainsString(
                'title=',
                $boton,
                "Un boton de $relativo no tiene title: $boton"
            );
        }
    }

    #[DataProvider('partialsDeAcciones')]
    public function test_los_botones_de_accion_confirman_el_borrado(string $relativo): void
    {
        $html = $this->leerPartial($relativo);

        // Todo partial de acciones tiene un boton de eliminar, y ese tiene
        // que pasar por la confirmacion de sweet-alert, no por un onclick
        // hecho a mano.
        $this->assertStringContainsString(
            'data-confirm-delete',
            $html,
            "$relativo no pide confirmacion antes de eliminar"
        );
    }

    /**
     * Los hooks .btn-show-trabajo y .btn-edit-trabajo los escucha
     * trabajos_empleados.js para abrir el modal del trabajo de un empleado.
     * La orden de trabajo no tiene ese modal, asi que si sus botones
     * usaran esos nombres, un data-url de mas abriria el modal equivocado.
     */
    public function test_las_acciones_de_la_orden_no_usan_los_hooks_del_modal(): void
    {
        $html = $this->leerPartial('procesos/ordenes_trabajo/_action.blade.php');

        // Solo se revisan los atributos class, no los comentarios
        preg_match_all('/class="([^"]*)"/', $html, $atributos);

        foreach ($atributos[1] as $clases) {
            $this->assertDoesNotMatchRegularExpression(
                '/\bbtn-(show|edit)-trabajo\b/',
                $clases,
                "La orden de trabajo no debe usar los hooks del modal: $clases"
            );
        }

        // Y el partial de trabajos si los conserva, que son suyos
        $trabajos = $this->leerPartial(
            'procesos/ordenes_trabajo/trabajos_empleados/_action.blade.php'
        );

        $this->assertStringContainsString('btn-show-trabajo', $trabajos);
        $this->assertStringContainsString('btn-edit-trabajo', $trabajos);
    }

    /**
     * Los datatables que arman su columna de acciones en PHP tienen el
     * mismo problema que los partials.
     */
    public function test_los_datatables_no_usan_font_awesome(): void
    {
        $revisados = 0;

        foreach (File::files(app_path('DataTables')) as $archivo) {
            $codigo = $archivo->getContents();

            $this->assertDoesNotMatchRegularExpression(
                '/\b(fas|far|fal|fa-solid|fa-regular) fa-/',
                $codigo,
                $archivo->getFilename() . ' todavia usa iconos de Font Awesome'
            );

            $revisados++;
        }

        $this->assertGreaterThan(0, $revisados);
    }
}
