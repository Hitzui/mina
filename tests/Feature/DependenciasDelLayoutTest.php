<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * El jQuery se carga del proyecto y no de internet.
 *
 * Este test existe porque el síntoma del fallo era invisible desde donde se
 * miraba. El jQuery venía de code.jquery.com, así que en una máquina sin
 * internet no llegaba, y lo que se veía en la consola de una pantalla era un
 * "$ is not a function" con un número de línea que no decía nada: no decía
 * que faltaba el $, ni que faltaba internet, ni que toda la página —tablas,
 * desplegables, modales— se iba a quedar sin hacer nada. Parecía un fallo de
 * un script, y no lo era: era que la aplicación entera dependía de que la
 * máquina tuviera conexión.
 *
 * Las dos cosas que se comprueban son las que hacen que no vuelva: que
 * ninguna etiqueta cargue el $ de una CDN, y que el archivo esté en el
 * proyecto y sea el mismo 3.7.1 que se usaba.
 *
 * Y una tercera, que no es del jQuery pero salió de lo mismo: el orden. Un
 * script normal se ejecuta en cuanto el navegador lo lee, y los del pie de
 * cada pantalla son normales, así que el jQuery tiene que estar antes en el
 * layout. Si se moviera debajo, el $ no existiría todavía cuando corrieran,
 * y pasaría exactamente lo mismo que pasaba.
 */
class DependenciasDelLayoutTest extends TestCase
{
    private function layout(): string
    {
        $ruta = resource_path('views/components/base-layout.blade.php');

        $this->assertFileExists($ruta);

        return file_get_contents($ruta);
    }

    /**
     * De dónde apunta cada etiqueta de script de un archivo.
     *
     * Se leen las etiquetas y no se buscan palabras sueltas, porque un
     * comentario puede nombrar cualquier sitio y lo que importa es solo de
     * dónde se carga algo de verdad. Solo se mira el atributo src, y solo si
     * la etiqueta lo trae completo.
     *
     * @return array<int, string>
     */
    private function etiquetasDeScript(string $contenido): array
    {
        $srcs = [];

        $patron = '/<script\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']/i';

        if (preg_match_all($patron, $contenido, $encontrados)) {
            $srcs = $encontrados[1];
        }

        return $srcs;
    }

    public function test_el_jquery_no_se_carga_de_una_cdn(): void
    {
        /*
         * Se busca la etiqueta entera, no la palabra "code.jquery.com": el
         * layout la menciona en el comentario que explica por qué el jQuery
         * no se carga de ahí, y con una palabra suelta este test fallaría
         * precisamente por estar bien escrito.
         *
         * Un comentario puede nombrar la CDN todo lo que quiera. Lo que no
         * puede es haber un script que la cargue.
         */
        foreach ($this->etiquetasDeScript($this->layout()) as $src) {
            $this->assertStringNotContainsString(
                'code.jquery.com',
                $src,
                'El layout carga el jQuery de una CDN: sin internet no hay $ en '
                . 'ninguna pantalla y se rompe todo lo que lo usa'
            );

            $this->assertStringNotContainsString(
                'ajax.googleapis.com',
                $src,
                'El jQuery no debe venir de googleapis por el mismo motivo'
            );
        }

        $this->assertTrue(true, 'Ninguna etiqueta del layout carga el jQuery de una CDN');
    }

    public function test_el_jquery_se_carga_del_proyecto(): void
    {
        $this->assertStringContainsString(
            "asset('plugins/jquery/jquery.min.js')",
            $this->layout(),
            'El layout deberia cargar el jQuery del proyecto'
        );
    }

    public function test_el_archivo_del_jquery_esta_en_el_proyecto(): void
    {
        $ruta = public_path('plugins/jquery/jquery.min.js');

        $this->assertFileExists(
            $ruta,
            'Falta el jQuery del proyecto: el layout lo apunta, asi que sin el '
            . 'archivo el $ no existe en ninguna pagina'
        );

        $contenido = file_get_contents($ruta);

        $this->assertStringContainsString(
            'jQuery',
            $contenido,
            'El archivo no parece ser jQuery'
        );

        /*
         * Se comprueba que sea el 3.7.1, que es el que usaba la CDN. Cambiar
         * la versión sin querer traería otra, con otros cambios, y eso no es
         * una decisión que deba tomarse al copiar un archivo.
         */
        $this->assertStringContainsString(
            'jQuery v3.7.1',
            $contenido,
            'Se esperaba jquery 3.7.1, el mismo que venia de la CDN'
        );
    }

    public function test_el_jquery_se_carga_antes_que_los_scripts_del_pie(): void
    {
        $layout = $this->layout();

        $jquery = strpos($layout, 'plugins/jquery/jquery.min.js');
        $pie = strpos($layout, '$footerFiles');

        $this->assertIsInt($jquery, 'El layout deberia cargar el jQuery');
        $this->assertIsInt($pie, 'El layout deberia tener el hueco del pie');

        $this->assertLessThan(
            $pie,
            $jquery,
            'El jQuery se carga despues de los scripts del pie, y cuando estos '
            . 'corren el $ todavia no existe'
        );
    }

    public function test_los_scripts_de_la_compra_avisan_si_no_hay_jquery(): void
    {
        /*
         * El arreglo de verdad es que el jQuery se cargue del proyecto. Esto
         * es para que, si algún día vuelve a faltar, la consola diga que
         * falta y no deje un botón muerto con un TypeError sin explicación.
         */
        foreach (['compras.js', 'selector_proveedor.js'] as $script) {
            $ruta = public_path('js/inventario/' . $script);

            $this->assertFileExists($ruta);

            $codigo = file_get_contents($ruta);

            $this->assertStringContainsString(
                "typeof window.jQuery === 'undefined'",
                $codigo,
                "$script deberia avisar cuando no encuentra jQuery, en vez de "
                . 'reventar con un TypeError que no explica nada'
            );

            $this->assertStringContainsString(
                'console.error',
                $codigo,
                "$script deberia dejar escrito en la consola que falta el jQuery"
            );
        }
    }

    public function test_los_scripts_que_usan_el_dolar_lo_reciben_de_verdad(): void
    {
        /*
         * (function ($) { ... })(); parece correcto y no lo es: con el
         * parentesis vacio, $ vale undefined dentro aunque el jQuery este
         * cargado, y el primer $ que se use revienta con un "$ is not a
         * function".
         *
         * El fallo no tiene nada que ver con de donde venga el jQuery. Se
         * puede tener el archivo en el proyecto, servido por el layout y en
         * el orden correcto, y seguir sin funcionar por no haberselo pasado a
         * la funcion. Por eso se comprueba el final del archivo y no solo que
         * el jQuery se cargue.
         */
        $scripts = [
            'js/inventario/compras.js',
            'js/inventario/selector_proveedor.js',
            'js/inventario/productos.js',
            'js/inventario/proveedores.js',
            'js/ordenes_trabajo/costos.js',
            'js/ordenes_trabajo/materiales.js',
        ];

        $sinRecibir = [];

        foreach ($scripts as $script) {
            $ruta = public_path($script);

            $this->assertFileExists($ruta);

            $codigo = file_get_contents($ruta);

            // Solo si el archivo usa el $ de verdad
            if (! preg_match('/\$\(/', $codigo)) {
                continue;
            }

            $cierre = trim((string) substr($codigo, strrpos($codigo, '})')));

            if (! str_contains($cierre, 'jQuery')) {
                $sinRecibir[] = $script;
            }
        }

        $this->assertSame(
            [],
            $sinRecibir,
            'Estos scripts usan el $ pero no lo reciben al final del archivo. '
            . 'Tienen que cerrar con (function ($) { ... })(window.jQuery); y no '
            . 'con los parentesis vacios, que dejan el $ sin valor dentro'
        );
    }

    public function test_ninguna_plantilla_usa_el_jquery_de_una_cdn(): void
    {
        $vistas = glob(resource_path('views/**/*.blade.php'));

        $this->assertNotEmpty($vistas);

        foreach ($vistas as $vista) {
            foreach ($this->etiquetasDeScript(file_get_contents($vista)) as $src) {
                if (
                    str_contains($src, 'code.jquery.com')
                    || str_contains($src, 'ajax.googleapis.com')
                ) {
                    $this->fail(
                        basename($vista) . ' carga el jQuery de una CDN: sin internet '
                        . 'no habria $ en esa pantalla. La etiqueta es: ' . $src
                    );
                }
            }
        }

        $this->assertTrue(true, 'Ninguna plantilla carga el jQuery de una CDN');
    }
}
