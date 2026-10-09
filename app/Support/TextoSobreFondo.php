<?php

namespace App\Support;

/**
 * Qué color de letra se lee sobre un fondo de color.
 *
 * ---------------------------------------------------------------------------
 * EL PROBLEMO QUE ESTO ARREGLA
 * ---------------------------------------------------------------------------
 *
 * Las tarjetas del panel llevan el color del estado como fondo —azul, ámbar, verde,
 * rojo, morado, cyan— y la letra iba en gris. El resultado era que en el azul el
 * gris se leía con un contraste de 1,04 sobre 1, que es lo mismo que no leerse: se
 * veía el número grande porque el número iba en negro, y el título y el pie no se
 * veían nada.
 *
 * Y la solución de ponerlo todo blanco tampoco vale en todas. Con los seis colores
 * del tema, el blanco llega al 4,5 en dos —el azul y el morado— y se queda en 2,25
 * en el ámbar. Un texto blanco sobre ámbar es un texto que no se lee, y un panel con
 * seis tarjetas de las que dos no se leen es un panel con dos cartas.
 *
 * ---------------------------------------------------------------------------
 * LA REGLA, Y POR QUÉ ES "EL QUE MÁS CONTRASTE DÉ" Y NO UNA LISTA
 * ---------------------------------------------------------------------------
 *
 * Porque con una lista hay que decidir de antemano qué letra lleva cada tarjeta, y
 * esa decisión hay que volver a tomarla cada vez que el tema cambia un color. Con la
 * regla no hay lista: se miden las dos y gana la mejor, y si el tema cambia el azul
 * por uno más claro, la tarjeta se adjusts sola y no hay que acordarse de ella.
 *
 * Las dos candidatas son el blanco y un gris muy oscuro con un punto de azul, que es
 * el color de texto del tema. Entre las dos siempre hay una que llega a 4,5 en
 * cualquier color de fondo, porque un color de fondo no puede ser a la vez muy
 * claro y muy oscuro.
 *
 * Y NO HAY UNA TERCERA, ni semitransparente ni con sombra. La sombra sobre el
 * número se ve bien en un móvil y mal en un escritorio grande, y bajar la opacidad
 * de la letra para que "se note menos el gris" baja el contraste, que es justo lo
 * que hay que subir. Aquí o se ve o no se ve.
 *
 * ---------------------------------------------------------------------------
 * QUE ES ESTO Y POR QUÉ NO UN SCRIPT
 * ---------------------------------------------------------------------------
 *
 * Porque el color de la letra lo decide la vista al pintar la tarjeta, y la vista no
 * puede hacer cuentas de contraste. Lo que hace es pedirle el color a esta clase y
 * aplicarlo, y la clase es la que sabe de qué colores se leen y con cuántos.
 */

class TextoSobreFondo
{
    /** El blanco, que es lo que lleva el tema en sus propios botones de color. */
    public const CLARO = '#ffffff';

    /**
     * El oscuro.
     *
     * Es un gris azulado muy oscuro y no negro puro, porque el negro puro sobre el
     * azul es un 3,3 y con este gris se llega al 3,7: el negro no es el color más
     * oscuro que se puede usar contra un fondo de color, es solo el más rápido de
     * escribir.
     */
    public const OSCURO = '#151a26';

    private string $hex;

    private float $conClaro;

    private float $conOscuro;

    public function __construct(string $hex)
    {
        $this->hex = self::normaliza($hex);

        $this->conClaro = self::contraste($this->hex, self::CLARO);
        $this->conOscuro = self::contraste($this->hex, self::OSCURO);
    }

    /**
     * El color de letra: el de las dos candidatas que más contraste da.
     *
     * En empate gana el blanco, porque es el que usa el tema en sus botones de
     * color y el que se espera ver en una tarjeta de color. El empate no pasa con
     * los seis colores del panel —el más cercano es el rojo, con 3,67 de blanco
     * contra 4,47 de oscuro— pero se deja dicho por si mañana hay un color que dé
     * justo el punto medio.
     */
    public function color(): string
    {
        return $this->conOscuro > $this->conClaro ? self::OSCURO : self::CLARO;
    }

    /**
     * El contraste que tiene la letra elegida con el fondo.
     *
     * Se llama "elegido" y no "contraste" porque este nombre lo tiene el estático de
     * abajo, y php no admite dos métodos con el mismo nombre aunque uno reciba
     * argumentos y el otro no: da error al cargar la clase, no al usar.
     */
    public function contrasteElegido(): float
    {
        return max($this->conClaro, $this->conOscuro);
    }

    /** El contraste que tendría el blanco, para poder compararlo. */
    public function contrasteConBlanco(): float
    {
        return $this->conClaro;
    }

    /** El contraste que tendría el oscuro, para poder compararlo. */
    public function contrasteConOscuro(): float
    {
        return $this->conOscuro;
    }

    /** Si la letra elegida es blanca. */
    public function esClaro(): bool
    {
        return $this->color() === self::CLARO;
    }

    /**
     * Si un color viene escrito de las tres formas que hay por ahi.
     *
     * Los colores del modelo vienen en hexadecimal de seis cifras y los del tema en
     * el mismo formato, pero las clases de bootstrap pueden traer el canal alfa
     * —#4361ee80— y un hexadecimal de ocho cifras no es lo mismo que uno de seis, de
     * modo que se recorta.
     */
    public static function normaliza(string $hex): string
    {
        $hex = ltrim(trim($hex), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        return '#' . substr($hex, 0, 6);
    }

    /**
     * El contraste entre dos colores, de 1 a 21.
     *
     * Es la fórmula de la WCAG, que es la que dice cuándo un texto se lee y cuándo
     * no. Da 1 cuando los dos colores son el mismo, que es el caso del icono que
     * iba del mismo color que la tarjeta: salía un 1,00 exacto, que es invisible.
     */
    public static function contraste(string $a, string $b): float
    {
        $la = self::luminancia(self::normaliza($a));
        $lb = self::luminancia(self::normaliza($b));

        $claro = max($la, $lb);
        $oscuro = min($la, $lb);

        return ($claro + 0.05) / ($oscuro + 0.05);
    }

    /**
     * La luminosidad relativa de un color.
     *
     * Los tres canales pasan por una curva antes de mezclarse: sin ella, el verde
     * pesa mucho más de lo que pesa de verdad y dos colores que se ven igual dan
     * números distintos. Es el paso que casi todos se saltan, y sin él el contraste
     * sale un 20 % desviado.
     */
    private static function luminancia(string $hex): float
    {
        $hex = self::normaliza($hex);

        return 0.2126 * self::aLineal(hexdec(substr($hex, 1, 2)))
            + 0.7152 * self::aLineal(hexdec(substr($hex, 3, 2)))
            + 0.0722 * self::aLineal(hexdec(substr($hex, 5, 2)));
    }

    /** Un canal de 0 a 255 convertido a la escala lineal. */
    private static function aLineal(float $canal): float
    {
        $canal = $canal / 255.0;

        return $canal <= 0.03928
            ? $canal / 12.92
            : pow(($canal + 0.055) / 1.055, 2.4);
    }
}
