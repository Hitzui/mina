<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * El calendario de los campos de fecha, en todas las pantallas.
 *
 * Hay un archivo comun —public/js/fechas.js— que engancha los campos
 * type="date" y type="datetime-local" de cualquier pagina en la que se cargue,
 * y el layout lo carga en todas. O sea que un campo de fecha no necesita que
 * su pantalla llame a flatpickr: lo encuentra solo.
 *
 * Ese es justo el motivo de que este test exista. Con el calendario puesto a
 * mano en cada pantalla, un campo nuevo se queda sin el si nadie se acuerda de
 * escribir la llamada, y no hay forma de saber cuales se han quedado: no sale
 * ningun error, la fecha se puede escribir a mano y nadie se entera hasta que
 * alguien se queja de que el calendario no aparece. De las pantallas que
 * tienen campo de fecha, solo cuatro lo tenian, y no por decision sino por
 * descuido.
 *
 * Este test recorre las vistas y comprueba las dos mitades:
 *
 *  - Que todo campo de fecha que se puede escribir tiene calendario, ya sea
 *    porque es del tipo que el archivo comun recoge, o porque su pantalla lo
 *    monta y entonces lleva la marca que lo dice.
 *
 *  - Y que los campos que llevan la marca existen de verdad en el javascript
 *    de su pagina. Es la otra mitad de la misma regla: si un campo dice "esto
 *    lo monta mi pagina" y la pagina no lo monta, el campo se queda sin
 *    calendario y no hay forma de saberlo mirando el campo.
 *
 * Los campos de solo lectura no se comprueban, y no por overlook: un
 * calendario que no se puede usar es ruido, asi que un campo deshabilitado
 * que enseña una fecha no debe llevar ninguno.
 */
class FechasConCalendarioTest extends TestCase
{
    /**
     * Los tipos de campo que el archivo comun engancha sin preguntar.
     *
     * Son los unicos dos que tienen calendario por el hecho de ser lo que
     * son. Un campo de texto no se engancha por su tipo, y por eso los que
     * llevan un calendario propio llevan la marca.
     */
    private const TIPOS_CON_CALENDARIO = ['date', 'datetime-local'];

    /**
     * Todos los campos de fecha de las vistas, editables y de solo lectura.
     *
     * @return array<int, array{vista: string, id: string, tipo: ?string, editable: bool, marcado: bool}>
     */
    private function camposDeFecha(): array
    {
        $campos = [];

        $recorre = function (string $dir) use (&$recorre, &$campos) {
            foreach (File::files($dir) as $archivo) {
                if (! str_ends_with($archivo->getFilename(), '.blade.php')) {
                    continue;
                }

                $contenido = file_get_contents($archivo->getPathname());

                preg_match_all('/<input\b[^>]*>/i', $contenido, $m);

                foreach ($m[0] as $input) {
                    preg_match('/\bid="([^"]+)"/', $input, $id);

                    if (! $id) {
                        continue;
                    }

                    $esFecha = str_contains($input, 'type="date"')
                        || str_contains($input, 'type="datetime-local"')
                        || str_contains(strtolower($id[1]), 'fecha');

                    if (! $esFecha) {
                        continue;
                    }

                    preg_match('/\btype="([^"]+)"/', $input, $tipo);

                    $editable = ! str_contains($input, 'disabled')
                        && ! str_contains($input, 'readonly')
                        && ! str_contains(strtolower($input), 'bg-light');

                    $campos[] = [
                        'vista' => $archivo->getRelativePathname(),
                        'id' => $id[1],
                        'tipo' => $tipo[1] ?? null,
                        'editable' => $editable,
                        'marcado' => str_contains($input, 'data-calendario="propio"'),
                    ];
                }
            }

            /*
             * File::directories() devuelve cadenas con la ruta completa, y no
             * objetos como File::files(). Por eso aqui no hay un
             * getPathname() que llamar: la cadena ya es la ruta.
             */
            foreach (File::directories($dir) as $subcarpeta) {
                $recorre($subcarpeta);
            }
        };

        $recorre(resource_path('views'));

        return $campos;
    }

    public function test_hay_algun_campo_de_fecha_que_revisar(): void
    {
        /*
         * Si el recorrido dejara de encontrar campos, el test pasaria sin
         * comprobar nada: todos los tests de este archivo|Authorizacion::|  */
        $this->assertGreaterThan(
            10,
            count($this->camposDeFecha()),
            'Se esperaban muchos campos de fecha. Si el recorrido ha dejado de encontrarlos, '
            . 'estos tests estarian pasando sin mirar nada.'
        );
    }

    public function test_todo_campo_de_fecha_que_se_escribe_tiene_calendario(): void
    {
        $sinCalendario = [];

        foreach ($this->camposDeFecha() as $campo) {
            if (! $campo['editable']) {
                continue;
            }

            $delTipoComun = in_array($campo['tipo'], self::TIPOS_CON_CALENDARIO, true);

            // O es del tipo que el archivo comun recoge, o su pantalla lo
            // monta y el campo lo dice. Si no es ninguna de las dos, este
            // campo se queda sin calendario y nadie se entera.
            if ($delTipoComun || $campo['marcado']) {
                continue;
            }

            $sinCalendario[] = $campo['vista'] . ' -> ' . $campo['id']
                . ' (type="' . ($campo['tipo'] ?? 'text') . '")';
        }

        $this->assertSame(
            [],
            $sinCalendario,
            "Estos campos de fecha no tendrian calendario. Un campo de texto tiene que ser\n"
            . "type=\"date\" para que el archivo comun lo enganche, o llevar data-calendario=\"propio\"\n"
            . "si su pantalla monta el calendario por su cuenta:\n  - "
            . implode("\n  - ", $sinCalendario)
        );
    }

    public function test_los_campos_con_marca_de_calendario_propio_no_se_escriben_dos_veces(): void
    {
        $marcados = [];

        foreach ($this->camposDeFecha() as $campo) {
            if ($campo['marcado']) {
                $marcados[] = $campo;
            }
        }

        $this->assertGreaterThan(
            0,
            count($marcados),
            'No hay ningun campo marcado. O no hace falta la marca, y entonces el archivo comun '
            . 'monta el calendario en todas partes, o se han olvidado de ponerla y algo se monta '
            . 'dos veces.'
        );
    }

    public function test_el_archivo_comun_de_fechas_existe_y_esta_cargado_en_el_layout(): void
    {
        $this->assertFileExists(
            public_path('js/fechas.js'),
            'El archivo comun de fechas no esta en public/js'
        );

        $layout = file_get_contents(resource_path('views/components/base-layout.blade.php'));

        $this->assertStringContainsString(
            'js/fechas.js',
            $layout,
            'El layout tiene que cargar el archivo comun de fechas, o no lo tendra ninguna pantalla'
        );

        /*
         * Y tiene que cargarlo DESPUES de los scripts de cada pagina.
         *
         * El orden importa porque el archivo comun engancha los campos que
         * encuentra, y si se cargara antes, una pantalla que ya tenga su
         * calendario lo veria limpio y se lo montaria encima. Un campo de
         * fecha con dos calendarios encima tiene dos campos de texto: el de
         * atras es el que se guarda y el usuario escribe en el de delante. El
         * fallo no da ningun error y no se ve sin mirar la pantalla.
         */
        $posicionScript = strpos($layout, 'js/fechas.js');
        $posicionFooter = strpos($layout, '{{$footerFiles}}');

        $this->assertNotFalse($posicionScript, 'No se encuentra el script en el layout');
        $this->assertNotFalse($posicionFooter, 'No se encuentra el hueco de los scripts de la pagina');

        $this->assertGreaterThan(
            $posicionFooter,
            $posicionScript,
            'El archivo comun de fechas tiene que cargarse despues de los scripts de la pagina, '
            . 'no antes: si va antes, se monta sobre los calendarios que la pagina ya ha puesto.'
        );
    }

    public function test_el_archivo_comun_usa_las_mismas_ajustes_que_las_pantallas_que_lo_tenian(): void
    {
        $comun = file_get_contents(public_path('js/fechas.js'));

        /*
         * El valor que va al servidor y el que se ve no se pueden cambiar: si
         * el value fuera d/m/Y la base no lo entenderia, y se guardaria con el
         * dia y el mes cambiados sin que nada lo notara.
         *
         * Y la configuracion tiene que ser la misma que la de las pantallas
         * que ya lo tenian, para que un campo con calendario se vea igual en
         * todas partes. Si estas dos lineas se tocan, hay que tocarlas tambien
         * en compras.js, en pagos/form.js y en trabajos_empleados.js, que
         * llevan su copia.
         */
        $this->assertStringContainsString("dateFormat: 'Y-m-d'", $comun);
        $this->assertStringContainsString("altFormat: 'd/m/Y'", $comun);
        $this->assertStringContainsString("locale: 'es'", $comun);
        $this->assertStringContainsString('allowInput: true', $comun);

        // Y que no se monte dos veces sobre el mismo campo
        $this->assertStringContainsString(
            'data-calendario',
            $comun,
            'El archivo comun tiene que respetar la marca de los campos que su pagina monta'
        );

        $this->assertStringContainsString(
            '_flatpickr',
            $comun,
            'El archivo comun tiene que comprobar si el campo ya tiene calendario antes de montarlo'
        );
    }

    public function test_los_campos_de_fecha_con_hora_no_pierden_la_hora(): void
    {
        $comun = file_get_contents(public_path('js/fechas.js'));

        /*
         * Los campos que llevan fecha y hora se guardan en columnas datetime,
         * no date: un molino puede pasar de un proceso a otro el mismo dia, y
         * por eso el inicio y el fin llevan hora y minuto.
         *
         * Si un calendario de solo fecha se les pusiera encima, el campo se
         * seguiria llenando pero la hora se pondria en cero, y un equipo
         * apareceria como si hubiera empezado a las doce de la noche. Y al
         * guardar no sale ningun error: la base acepta la fecha sin hora sin
         * quejarse, porque para ella es una fecha valida.
         *
         * Por eso los datetime-local van con enableTime, y por eso el salto
         * de cinco minutos: en un taller las horas se miran de cinco en cinco.
         */
        $this->assertStringContainsString(
            "input[type=\"datetime-local\"]",
            $comun,
            'Los campos con fecha y hora tienen que estar en el archivo comun, o se quedan sin calendario'
        );

        $this->assertStringContainsString(
            'enableTime: true',
            $comun,
            'Los campos con fecha y hora necesitan enableTime, o pierden la hora al guardar'
        );

        $this->assertStringContainsString(
            "altFormat: 'd/m/Y H:i'",
            $comun,
            'El campo con hora debe enseñar la hora en el formato que se usa en el taller'
        );

        $this->assertStringContainsString(
            'minuteIncrement: 5',
            $comun,
            'El salto de cinco minutos es el mismo que usan las horas de los trabajos de empleado'
        );
    }
}
