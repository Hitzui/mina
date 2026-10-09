<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\TextoSobreFondo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Que se lea lo que se pinta en las tarjetas del panel.
 *
 * ---------------------------------------------------------------------------
 * EL FALLO QUE ESTE ARCHIVO TAPA
 * ---------------------------------------------------------------------------
 *
 * Las tarjetas llevan el color del estado como fondo y la letra iba en gris. El
 * gris del tema, #6b7280, sobre el azul del tema, #4361ee, da un contraste de 1,04
 * sobre 1 — y 1 sobre 1 es lo que da un color consigo mismo, que es decir invisible.
 *
 * Lo grave no es que se viera mal: es que NO DABA ERROR. Una letra que no se ve no
 * falla ningún análisis, no sale nada en la consola, la página devuelve 200 y el
 * número grande se ve porque el número iba en negro. El título y el pie de cada
 * tarjeta, que es donde está la explicación de qué significa el número, no se veían
 * nada. Y el usuario lo vio a la primera, que es lo que hacía falta: un test que
 * lo mirara.
 *
 * ---------------------------------------------------------------------------
 * POR QUÉ MEDIR Y NO FIJAR UNA LISTA DE COLORES
 * ---------------------------------------------------------------------------
 *
 * Porque la solución de ponerlo todo en blanco tampoco vale en todas. Con los seis
 * colores del tema el blanco llega al 4,5 en dos —el azul y el morado— y se queda en
 * 2,25 en el ámbar. Un texto blanco sobre ámbar es un texto que no se lee, y un panel
 * con seis tarjetas de las que dos no se leen es un panel con dos cartas.
 *
 * Y una lista de "este color lleva blanco y este otro oscuro" hay que volver a
 * tomarla cada vez que el tema cambia un color, que es una de las cosas que pasa más
 * y de las que nadie se acuerda. Con la regla de "el que más contraste dé" no hay
 * lista que mantener: si el tema aclara el azul, la tarjeta se ajusta sola.
 *
 * ---------------------------------------------------------------------------
 * EL 4,5 Y POR QUÉ
 * ---------------------------------------------------------------------------
 *
 * Es el mínimo de la WCAG para texto pequeño. El número grande de la tarjeta
 * aguantaría con 3, porque es letra grande, y eso haría que seis colores pasaran la
 * prueba con letra blanca y el texto de debajo se quedara sin leerse. Por eso el
 * mínimo es 4,5 para los tres: título, número y pie.
 */
class TextoSobreFondoTest extends TestCase
{
    /**
     * Los seis colores que el tema usa para los fondos de tarjeta.
     *
     * Se copian del css compilado del tema y no del modelo porque lo que se
     * comprueba aquí es la legibilidad sobre los colores del tema. Que el hex que
     * pasa la vista sea el del tema lo comprueba otro test de este archivo.
     */
    private const COLORES_DEL_TEMA = [
        'bg-primary' => '#4361ee',
        'bg-secondary' => '#805dca',
        'bg-success' => '#00ab55',
        'bg-warning' => '#e2a03f',
        'bg-danger' => '#e7515a',
        'bg-info' => '#2196f3',
    ];

    private const MINIMO = 4.5;

    // ==================================================================
    // Que se lee
    // ==================================================================

    public function test_en_los_seis_colores_del_tema_se_elige_una_letra_que_se_lee(): void
    {
        foreach (self::COLORES_DEL_TEMA as $clase => $hex) {
            $texto = new TextoSobreFondo($hex);

            $this->assertGreaterThanOrEqual(
                self::MINIMO,
                $texto->contrasteElegido(),
                'Sobre ' . $clase . ' (' . $hex . ') la letra sale con un contraste de '
                    . round($texto->contrasteElegido(), 2) . ':1 y el mínimo es ' . self::MINIMO . ':1. '
                    . 'Blanco daría ' . round($texto->contrasteConBlanco(), 2)
                    . ':1 y el oscuro ' . round($texto->contrasteConOscuro(), 2) . ':1.'
            );
        }
    }

    public function test_el_blanco_solo_se_elige_donde_de_verdad_se_lee(): void
    {
        $claros = [];
        $oscuros = [];

        foreach (self::COLORES_DEL_TEMA as $clase => $hex) {
            if ((new TextoSobreFondo($hex))->esClaro()) {
                $claros[] = $clase;
            } else {
                $oscuros[] = $clase;
            }
        }

        /*
         * El blanco tiene que estar en el azul y el morado, que son los dos únicos
         * donde llega al 4,5, y tiene que estar FUERA del ámbar, que es el peor de
         * todos con 2,25. Si alguien cambia la regla y pone blanco en todas, este
         * test lo dice; y si alguien la deja siempre en oscuro, también, porque
         * entonces el azul se queda en 3,46.
         */
        $this->assertSame(['bg-primary', 'bg-secondary'], $claros);

        $this->assertContains('bg-warning', $oscuros, 'El ámbar es el peor de los seis: el blanco no se lee.');
        $this->assertNotContains('bg-primary', $oscuros, 'El azul con letra oscura se queda en 3,46.');
    }

    public function test_el_gris_que_se_usia_antes_no_se_lee_sobre_ninguno(): void
    {
        /*
         * El fallo concreto, medido. Está aquí para que quede escrito qué pasó y no
         * solo en el mensaje de un commit: si algún día alguien pone el gris otra
         * vez, el número que sale en el fallo es el que lo explica.
         */
        $gris = '#6b7280';

        /*
         * El mínimo es 3, y no 4,5, porque el objetivo del test es otro: que el gris
         * no llega ni al contraste que la propia norma da para letra GRANDE, que es
         * 3. El gris sobre el azul da 1,04 y sobre el ámbar 2,15, así que ninguno de
         * los seis llega. Con un mínimo de 4,5 el ámbar, que es el mejor de los
         * seis, pasaba el filtro y el test no habría dicho nada del peor caso.
         */
        foreach (self::COLORES_DEL_TEMA as $clase => $hex) {
            $contraste = TextoSobreFondo::contraste($gris, $hex);

            $this->assertLessThan(
                3.0,
                $contraste,
                'El gris ' . $gris . ' sobre ' . $clase . ' da un contraste de '
                    . round($contraste, 2) . ':1, que no llega ni al 3:1 que la norma da '
                    . 'para letra grande. Por eso las tarjetas del panel no llevan letra gris.'
            );
        }
    }

    // ==================================================================
    // Que el cálculo sea el correcto
    // ==================================================================

    public function test_el_calculo_del_contraste_es_el_de_la_wcag(): void
    {
        /*
         * Tres casos con respuesta conocida. Un cálculo de contraste mal hecho da
         * números que parecen razonables y están desviados, así que se comprueba
         * contra casos de los que se sabe el resultado.
         *
         *  - Negro sobre blanco: 21 sobre 1, que es el máximo que existe.
         *  - Blanco sobre blanco: 1 sobre 1, que es el mínimo, y es lo que daba el
         *    ícono del círculo, que iba del mismo color que la tarjeta.
         *  - Y un gris medio sobre blanco, que tiene que estar entre 4 y 6.
         */
        $this->assertEqualsWithDelta(
            21.0,
            TextoSobreFondo::contraste('#000000', '#ffffff'),
            0.01,
            'Negro sobre blanco tiene que ser 21:1, que es el máximo de la escala.'
        );

        $this->assertEqualsWithDelta(
            1.0,
            TextoSobreFondo::contraste('#ffffff', '#ffffff'),
            0.01,
            'Un color consigo mismo tiene que dar 1:1. Es lo que pasaba con el círculo del icono.'
        );

        $medio = TextoSobreFondo::contraste('#767676', '#ffffff');

        $this->assertGreaterThanOrEqual(4.0, $medio);
        $this->assertLessThanOrEqual(6.0, $medio);
    }

    public function test_acepta_los_hexadecimales_de_las_tres_formas(): void
    {
        /*
         * Los colores llegan de sitios distintos: el modelo los tiene en seis
         * caracteres, las clases de bootstrap pueden traer el canal alfa —ocho
         * caracteres— y hay quien los escribe sin la almohadilla. Si solo se
         * aceptara una forma, el que llegue de cualquiera de las otras dos daría un
         * color equivocado y la letra se calcularía sobre un fondo que no es el de
         * la tarjeta.
         */
        foreach (['#4361ee', '#4361eeff', '4361ee', '#4361'] as $forma) {
            $texto = new TextoSobreFondo($forma);

            $this->assertGreaterThanOrEqual(
                self::MINIMO,
                $texto->contrasteElegido(),
                'Con el color escrito como "' . $forma . '" tiene que salir el mismo resultado que con "#4361ee".'
            );
        }

        $this->assertSame(
            (new TextoSobreFondo('#4361ee'))->color(),
            (new TextoSobreFondo('4361eeff'))->color(),
            'Las cuatro formas del mismo color tienen que dar la misma letra.'
        );
    }

    // ==================================================================
    // Que las tarjetas del panel lleven el color del tema
    // ==================================================================

    public function test_cada_tarjeta_del_panel_lleva_la_letra_que_pide_su_fondo(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'texto-sobre-fondo-' . uniqid() . '@test.local',
            'password' => Hash::make('prueba-de-prueba'),
        ]);
        $admin->syncRoles(['admin']);

        $this->actingAs($admin);

        $html = $this->get('/')->assertOk()->getContent();

        /*
         * POR QUÉ ESTE TEST NO COMPARA EL HEXADECIMAL
         *
         * Porque el hexadecimal del fondo no llega nunca al html. El fondo lo pinta la
         * clase de bootstrap —bg-danger— y el hexadecimal solo se usa en el servidor
         * para decidir el color de la letra. No hay nada en la pagina que compararle
         * al hexadecimal del tema.
         *
         * Lo que sí se puede comprobar, y es lo que importa, es que la letra que
         * lleva cada tarjeta sea la que corresponde a ese fondo. Y se comprueba
         * contra el css del tema, no contra una lista escrita aquí: si el tema cambiara
         * el rojo, este test leería el nuevo rojo de la hoja y compararía contra ese,
         * sin que hubiera que acordarse de actualizar nada.
         *
         * ---------------------------------------------------------------------------
         * Y POR QUÉ NO VALE CON COMPROBAR QUE SALGA "#ffffff" O "#151a26"
         * ---------------------------------------------------------------------------
         *
         * Que salga una de las dos no dice nada: saldría lo mismo si estuvieran
         * cambiadas. Lo que se mira es que sea la correcta para ESE fondo. Y se mide
         * el html que sale, no se recalcula a mano, porque el fallo que se está
         * tapando era de que la vista y el cálculo no iban de acuerdo.
         */
        $coloresDelTema = $this->coloresDelTema();

        preg_match_all(
            '#<[ad] [^>]*class="card br-8 mb-3 (bg-\w+)[^"]*"[^>]*>(.*?)</[ad]>#s',
            $html,
            $tarjetas,
            PREG_SET_ORDER
        );

        $this->assertNotEmpty($tarjetas, 'No se ha encontrado ninguna tarjeta de color en el panel.');

        foreach ($tarjetas as $tarjeta) {
            [, $clase, $cuerpo] = $tarjeta;

            $this->assertArrayHasKey(
                $clase,
                $coloresDelTema,
                'La tarjeta usa ' . $clase . ', y el tema no da ese color en ninguna de sus hojas. '
                    . 'La letra se ha calculado sobre un color inventado.'
            );

            $esperada = (new TextoSobreFondo($coloresDelTema[$clase]))->color();

            $this->assertStringContainsString(
                'color: ' . $esperada . ';',
                $cuerpo,
                'La tarjeta de ' . $clase . ' ('
                    . $coloresDelTema[$clase] . ') debería llevar la letra ' . $esperada
                    . ' y no la lleva. Con la otra de las dos candidatas no se llega al contraste mínimo.'
            );
        }
    }

    /**
     * Los colores que el tema da a cada clase de fondo, leídos de su css.
     *
     * Se leen de las hojas compiladas y no de una lista escrita en el test, porque
     * una lista hay que actualizarla cada vez que el tema cambia un color, y ese es
     * justo el cambio que nadie recuerda. Leyéndolo del css, el test se ajusta solo.
     */
    private function coloresDelTema(): array
    {
        $css = '';
        $carpeta = 'C:/xampp/htdocs/mina/public/build/assets';

        foreach (glob($carpeta . '/*.css') as $archivo) {
            $css .= file_get_contents($archivo);
        }

        $this->assertNotEmpty($css, 'No se encuentra el css del tema.');

        preg_match_all(
            '/\.bg-(primary|secondary|success|warning|danger|info)\{[^}]*background-color:(#[0-9a-f]{6})/i',
            $css,
            $m,
            PREG_SET_ORDER
        );

        $colores = [];

        foreach ($m as $uno) {
            // Se guarda el último, que es el que gana si el tema repite la clase
            $colores['bg-' . strtolower($uno[1])] = $uno[2];
        }

        $this->assertNotEmpty($colores, 'No se han encontrado los colores de fondo en el css del tema.');

        return $colores;
    }
}
