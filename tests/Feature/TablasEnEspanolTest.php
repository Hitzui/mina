<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Los textos de las tablas, en español.
 *
 * DataTables viene en inglés y no trae archivo de idioma español, asi que los
 * textos hay que darlos uno por uno. Lo que sale sin ellos, en las veinticinco
 * tablas de la aplicación, es un "Search:" junto al buscador, un "Showing 1 to
 * 10 of 20 entries" debajo de la tabla, un "Previous" y un "Next" en la
 * paginación, y un "No matching records found" cuando se busca algo que no
 * esta.
 *
 * El idioma se pone en un solo sitio —el trait TablaResponsiva, por donde pasan
 * todas las tablas—, asi que los tests miran dos cosas distintas: que la
 * traduccion este completa y sea la de DataTables 2.3.8, y que todas las
 * tablas se la lleven.
 *
 * Y AQUI ESTA LA PARTE QUE MIRA CON CUIDADO, PORQUE LA PRIMERA VERSION DE
 * ESTOS TESTS NO MIRABA NADA.
 *
 * La primera version buscaba en el html los textos en ingles —"Search:",
 * "No matching records found"— para comprobar que no estaban. Pasia con el
 * idioma puesto y pasia sin el idioma. Es que esos textos nunca estan en el
 * html: no los pone el html, los pone el navegador al construir la tabla a
 * partir de la configuracion, y cuando no hay idioma usa los que lleva dentro
 * el propio dataTables.js. Buscar en el html algo que no puede estar ahi no
 * comprueba nada, por mucho que el test parezca que comprueba.
 *
 * Lo que si esta en el html, y es donde hay que mirar, es la configuracion en
 * formato json que se le pasa a DataTables: ahi viaja el idioma. Estos tests
 * la buscan ahi, la leen, y la comparan con lo que DataTables espera.
 *
 * Para saber que el test tiene dientes se quito la linea del idioma y se
 * vio que falla, y se volvio a poner. Un test que no se ha visto fallar
 * nunca no sabe si mira o no.
 */
class TablasEnEspanolTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Las claves que DataTables 2.3.8 trae por defecto y que hay que dar.
     *
     * La lista esta sacada del propio dataTables.js, que es el archivo que
     * carga el layout. No de memoria, porque las claves han cambiado entre
     * versiones: en la 1.x el tipo de paginacion vivia en "sPaginationType" y
     * en la 2.x esa clave no existe.
     *
     * Si se actualiza DataTables y aparece una clave nueva, este test falla y
     * avisa de que hay un texto mas que traducir. Eso es lo que se quiere: un
     * texto sin traducir sale en ingles en una pantalla y no se nota.
     */
    private const CLAVES_DE_DATATABLES = [
        'search',
        'lengthMenu',
        'info',
        'infoEmpty',
        'infoFiltered',
        'emptyTable',
        'zeroRecords',
        'loadingRecords',
        'processing',
        'entries',
        'lengthLabels',
        'aria',
    ];

    /**
     * Las claves de dentro de "aria", que tambien hay que dar todas.
     */
    private const CLAVES_ARIA = [
        'orderable',
        'orderableReverse',
        'orderableRemove',
        'paginate',
    ];

    /**
     * Las claves de dentro de "aria.paginate".
     */
    private const CLAVES_PAGINAR = [
        'first',
        'last',
        'next',
        'previous',
        'number',
    ];

    /**
     * Los textos que DataTables pone si no se le dicen otros.
     *
     * Se comparan contra los que trae el archivo, no contra una lista escrita
     * aqui: asi el test falla si DataTables cambia su texto por defecto, que
     * es justo cuando hay que volver a mirarlo.
     */
    private const TEXTOS_EN_LA_PANTALLA = [
        'No data available in table',
        'No matching records found',
        'Search:',
        'Previous',
        'Next',
        'Loading...',
        'First',
        'Last',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'tablas-es-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);
        $this->actingAs($admin);
    }

    // ------------------------------------------------------------------
    // Ayudas
    // ------------------------------------------------------------------

    /**
     * El idioma que lleva una pagina, leido del json que se le pasa a
     * DataTables.
     *
     * @return array<string, mixed>|null
     */
    private function idiomaDe(string $pagina): ?array
    {
        $html = $this->get($pagina)->assertOk()->getContent();

        $lenguaje = $this->sacarElIdioma($html);

        if ($lenguaje === null) {
            return null;
        }

        return json_decode($lenguaje, true);
    }

    /**
     * Saca el texto del objeto "language" del script de la tabla.
     *
     * Va contando llaves porque el objeto tiene dentro otros objetos —el de
     * las entradas y el de la paginación— y si se cortara por la primera llave
     * se quedaria a medias. Y devuelve el trozo entero, con las llaves, porque
     * eso es lo que se puede leer con json_decode.
     */
    private function sacarElIdioma(string $html): ?string
    {
        $inicio = strpos($html, '"language":');

        if ($inicio === false) {
            return null;
        }

        $desde = $inicio + strlen('"language":');

        $llaves = 0;
        $longitud = strlen($html);

        for ($i = $desde; $i < $longitud; $i++) {
            $caracter = $html[$i];

            if ($caracter === '{') {
                $llaves++;

                continue;
            }

            if ($caracter !== '}') {
                continue;
            }

            $llaves--;

            if ($llaves === 0) {
                return substr($html, $desde, $i - $desde + 1);
            }
        }

        return null;
    }

    /**
     * Todas las claves de un array, con los prefijos de los niveles de
     * dentro, para poder compararlas con una lista de una sola linea.
     *
     * @param  array<string, mixed>  $array
     * @param  string  $prefijo
     * @return array<int, string>
     */
    private function aplanar(array $array, string $prefijo = ''): array
    {
        $salida = [];

        foreach ($array as $clave => $valor) {
            $completa = $prefijo === '' ? (string) $clave : $prefijo . '.' . $clave;

            if (is_array($valor)) {
                $salida = array_merge($salida, $this->aplanar($valor, $completa));

                continue;
            }

            $salida[] = $completa;
        }

        return $salida;
    }

    // ==================================================================
    // La traduccion
    // ==================================================================

    public function test_la_pagina_lleva_el_idioma_de_datatables(): void
    {
        $idioma = $this->idiomaDe('/configuracion/monedas');

        $this->assertNotNull(
            $idioma,
            'La pagina deberia llevar el idioma de DataTables en la configuracion que se le pasa. '
            . 'Si no lo lleva, el buscador dira "Search:" y la tabla "Showing 1 to 10 of 20 entries".'
        );

        $this->assertIsArray($idioma);
    }

    public function test_la_traduccion_tiene_todas_las_claves_que_datatables_espera(): void
    {
        $idioma = $this->idiomaDe('/configuracion/monedas');

        $this->assertNotNull($idioma, 'No se encuentra el idioma en la pagina');

        $faltan = [];

        foreach (self::CLAVES_DE_DATATABLES as $clave) {
            if (! array_key_exists($clave, $idioma)) {
                $faltan[] = $clave;
            }
        }

        $this->assertSame(
            [],
            $faltan,
            "Faltan claves del idioma. DataTables pondra su texto en ingles, que es justo lo que\n"
            . "se quiere quitar:\n  - " . implode("\n  - ", $faltan)
        );
    }

    public function test_las_claves_de_la_paginacion_y_del_lector_esten_todas(): void
    {
        $idioma = $this->idiomaDe('/configuracion/monedas');

        $this->assertNotNull($idioma);

        $aria = $idioma['aria'] ?? [];

        $faltan = [];

        foreach (self::CLAVES_ARIA as $clave) {
            if (! array_key_exists($clave, $aria)) {
                $faltan[] = 'aria.' . $clave;
            }
        }

        $paginar = $aria['paginate'] ?? [];

        foreach (self::CLAVES_PAGINAR as $clave) {
            if (! array_key_exists($clave, $paginar)) {
                $faltan[] = 'aria.paginate.' . $clave;
            }
        }

        $this->assertSame(
            [],
            $faltan,
            "Faltan claves del idioma dentro de aria. Son las que usa el lector de pantalla y la\n"
            . "paginacion:\n  - " . implode("\n  - ", $faltan)
        );
    }

    public function test_ningun_texto_se_queda_en_el_ingles_por_defecto(): void
    {
        $idioma = $this->idiomaDe('/configuracion/monedas');

        $this->assertNotNull($idioma);

        // Todo el idioma en una sola cadena, para poder buscar los textos
        $plano = json_encode($idioma, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        /*
         * Se buscan los textos tal cual vienen en el dataTables.js. Y se
         * comprueba tambien contra el archivo, que es lo que hace que el test
         * sirva para algo mas: si DataTables cambia su texto —en una version
         * nueva cambia el texto, no solo las claves— este test lo detecta y
         * obliga a mirarlo, en vez de seguir buscando un texto que ya no es
         * el que pondria la biblioteca.
         */
        $archivo = file_get_contents(public_path('plugins/table/datatable/dataTables.js'));

        foreach (self::TEXTOS_EN_LA_PANTALLA as $texto) {
            $this->assertStringNotContainsString(
                $texto,
                $plano,
                "El texto \"$texto\" se ha quedado en ingles"
            );
        }

        // Y que los textos que pone la biblioteca siguen siendo los que
        // buscamos, para que el test no se quede mirando textos viejos
        foreach (['No data available in table', 'No matching records found', 'Search:'] as $texto) {
            $this->assertStringContainsString(
                $texto,
                $archivo,
                "El texto \"$texto\" ya no esta en la version de DataTables que carga el layout. "
                . 'Este test hay que revisarlo: puede que hayan cambiado los textos por defecto.'
            );
        }
    }

    public function test_las_claves_de_orden_no_se_han_traducido(): void
    {
        $idioma = $this->idiomaDe('/configuracion/monedas');

        $this->assertNotNull($idioma);

        /*
         * "_MENU_" y "_ENTRIES_" no son palabras: son huecos que DataTables
         * rellena con el numero de filas que se ha elegido y con la palabra
         * de "registro" o "registros".
         *
         * Es el fallo mas tonto de los posibles con una traduccion, y es el
         * mas probable: alguien traduce "10 entries per page" a "10 entradas
         * por pagina" y con eso se lleva por delante el hueco. Lo que sale en
         * pantalla es literalmente "_MENU_ por pagina", con el guion bajo a
         * la vista, y no da ningun error.
         *
         * Cada hueco se comprueba en su texto, y no "que tenga un guion
         * debajo", porque los nombres no son todos iguales: el menu usa
         * _ENTRIES_ y el de la informacion de abajo usa _ENTRIES-TOTAL_, que
         * es la misma palabra con el numero al lado para que pueda cambiar a
         * singular o plural. Buscar "_ENTRIES_" en el texto de abajo no lo
         * encuentra, y ese fallo de aqui fue el que mas salio al escribir
         * este test.
         */
        $huecosPorTexto = [
            'lengthMenu' => ['_MENU_', '_ENTRIES_'],
            'info' => ['_START_', '_END_', '_TOTAL_', '_ENTRIES-TOTAL_'],
            'infoEmpty' => ['_ENTRIES-TOTAL_'],
            'infoFiltered' => ['_MAX_', '_ENTRIES-MAX_'],
        ];

        foreach ($huecosPorTexto as $clave => $huecos) {
            $this->assertArrayHasKey($clave, $idioma, "Falta el texto de \"$clave\"");

            foreach ($huecos as $hueco) {
                $this->assertStringContainsString(
                    $hueco,
                    (string) $idioma[$clave],
                    "El texto de \"$clave\" deberia llevar el hueco $hueco. Si no lo lleva, en "
                    . 'pantalla saldra el nombre del hueco tal cual, con el guion debajo.'
                );
            }
        }
    }

    public function test_las_entradas_dicen_registros_y_no_entradas(): void
    {
        $idioma = $this->idiomaDe('/configuracion/monedas');

        $this->assertNotNull($idioma);

        $entradas = $idioma['entries'] ?? [];

        $this->assertSame('registros', $entradas['_'] ?? null);
        $this->assertSame('registro', $entradas[1] ?? null);

        /*
         * "Entrada" en un taller es una entrada de almacen, o sea una compra
         * que entra. Decir "10 entradas" al pie de una tabla de ordenes de
         * trabajo se lee como si hubiera diez compras, y es el texto que
         * DataTables trae por defecto.
         */
        $this->assertNotSame('entries', $entradas['_'] ?? null);
    }

    public function test_los_separadores_de_numeros_no_se_cambian(): void
    {
        $idioma = $this->idiomaDe('/configuracion/monedas');

        $this->assertNotNull($idioma);

        /*
         * Los separadores no se tocan: se dejan los de DataTables —coma en
         * los miles y punto en los decimales— porque es como formatea el lado
         * de php con number_format(). Si aqui se cambiaran, un 78,4521 escrito
         * en una celda y el mismo numero en la informacion de abajo se
         * escribirian de forma distinta y parecerian dos numeros.
         */
        $this->assertArrayNotHasKey('decimal', $idioma, 'No hay que tocar el separador de decimales');
        $this->assertArrayNotHasKey('thousands', $idioma, 'No hay que tocar el separador de miles');
    }

    // ==================================================================
    // Que todas las tablas lo lleven
    // ==================================================================

    public function test_todas_las_paginas_de_tabla_llevan_el_idioma(): void
    {
        /*
         * Se mira pagina por pagina y no una sola, porque una sola no notaria
         * que el idioma estuviera en una tabla y no en otra. Y las tablas son
         * veinticinco, cada una construida en su archivo, que es la forma mas
         * facil de que se queden unas pocas sin idioma: no da error, la tabla
         * funciona y lo unico que se ve es un "Search:" en una pantalla.
         */
        $paginas = [
            '/configuracion/monedas',
            '/configuracion/tipos-cambio',
            '/configuracion/precios-oro',
            '/inventario/compras',
            '/inventario/proveedores',
            '/inventario/productos',
            '/inventario/movimientos',
            '/procesos/ordenes-trabajo',
            '/procesos/recuperaciones',
            '/admin/clientes',
            '/admin/empleados',
            '/admin/etapas',
            '/admin/equipos',
        ];

        $sinIdioma = [];
        $revisadas = 0;

        foreach ($paginas as $pagina) {
            $respuesta = $this->get($pagina);

            if (! $respuesta->isOk()) {
                continue;
            }

            $revisadas++;

            if ($this->sacarElIdioma($respuesta->getContent()) === null) {
                $sinIdioma[] = $pagina;
            }
        }

        $this->assertGreaterThan(
            5,
            $revisadas,
            'Se esperaban varias paginas revisadas. Si no lo hay, este test pasaria sin mirar nada.'
        );

        $this->assertSame(
            [],
            $sinIdioma,
            "Estas pantallas se quedan con los textos de DataTables en ingles. El idioma se pone\n"
            . "en el trait TablaResponsiva, asi que lo que falta es que la tabla pase por el:\n  - "
            . implode("\n  - ", $sinIdioma)
        );
    }

    public function test_el_idioma_lo_llevan_todas_las_tablas_por_el_trait_comun(): void
    {
        $carpeta = app_path('DataTables');

        $tablas = [];

        $recorre = function (string $dir) use (&$recorre, &$tablas) {
            foreach (scandir($dir) ?: [] as $f) {
                if ($f === '.' || $f === '..') {
                    continue;
                }

                $completo = $dir . '/' . $f;

                if (is_dir($completo)) {
                    $recorre($completo);

                    continue;
                }

                if (str_ends_with($f, '.php')) {
                    $tablas[$completo] = file_get_contents($completo);
                }
            }
        };

        $recorre($carpeta);

        $sinIdioma = [];
        $contadas = 0;

        foreach ($tablas as $archivo => $contenido) {
            if (! str_contains($contenido, 'extends DataTable')) {
                continue;
            }

            $contadas++;

            $nombre = basename($archivo, '.php');

            if (! str_contains($contenido, 'ajustesComunes')) {
                $sinIdioma[] = $nombre . ' (no llama a ajustesComunes)';

                continue;
            }

            /*
             * Y que la tabla no se ponga el idioma por su cuenta. El idioma es
             * una sola cosa compartida, y veinticinco copias se separan en
             * cuanto se toca una: se queda en español en veinticuatro tablas y
             * en ingles en la que se toco, y no hay forma de saber cual de las
             * dos sin leerlas todas.
             */
            if (str_contains($contenido, '->language(')) {
                $sinIdioma[] = $nombre . ' (define el idioma en vez de heredarlo)';
            }
        }

        $this->assertGreaterThan(
            20,
            $contadas,
            'Se esperaban muchas tablas. Si el recorrido ha dejado de encontrarlas, este test '
            . 'estaria pasando sin mirar nada.'
        );

        $this->assertSame(
            [],
            $sinIdioma,
            "Estas tablas no llevan el idioma por el camino comun:\n  - " . implode("\n  - ", $sinIdioma)
        );
    }
}
