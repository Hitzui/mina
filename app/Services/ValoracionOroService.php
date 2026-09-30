<?php

namespace App\Services;

use App\Models\Moneda;
use App\Models\PreciosOro;
use App\Models\Recuperaciones;
use App\Models\TiposCambio;

/**
 * Cuanto vale lo que salio del taller, y de donde sale ese numero.
 *
 * Esta clase existe por una razon que esta en su propia firma: no hay forma de
 * valorar sin decirle de que moneda es el precio, ni de que moneda es el valor.
 * Son dos columnas distintas de la tabla, y pueden no ser la misma.
 *
 * El caso normal de un taller es: el precio del gramo se carga en dolares —
 * asi lo cotiza el banco— y el valor se guarda en cordoba, que es donde esta
 * la contabilidad. O al reves, y con el banco cotizando en cordoba y el valor
 * en dolares para un cliente que paga en dolares. Por eso son dos entradas y no
 * una, y por eso la cuenta se hace aqui y no dentro de un campo.
 *
 * LA CUENTA, Y CADA PARTE POR QUE ESTA COMO ESTA.
 *
 *  - Se valoran los gramos FINOS, no los que salieron, y solo cuando la
 *    pureza se sabe. Si una partida dio 2 gramos al 75 %, lo que hay 1,5
 *    gramos de oro fino, y valorar los 2 es pagar de mas por material que no
 *    era oro. Y cuando no se midio la pureza se valoran los gramos tal cual,
 *    que es lo que siempre se ha hecho, porque no hay ningun otro numero
 *    sobre el que multiplicar. La regla esta escrita asi porque las dos cosas
 *    son verdad a la vez y no se pueden aplicar la misma a las dos: "no se
 *    sabe" y "vale cero" no son lo mismo en ninguna de las dos tablas del oro.
 *
 *  - El precio se busca con la misma regla de la serie: el ultimo conocido
 *    que no sea cero y que no sea posterior al dia. Un dia sin cotizacion se
 *    tasa con el anterior, que es lo que se ha hecho siempre en el taller: el
 *    metal no cambia de valor de un dia a otro porque el banco no publicara
 *    ese dia.
 *
 *  - Y si no hay ningun precio anterior, no se devuelve cero: se devuelve que
 *    no hay con que. Un documento con valor cero es lo peor de los dos mundos,
 *    porque sale un numero y no avisa de nada.
 *
 * EL REDONDEO A DOS DECIMALES, que no es cosmetico.
 *
 * La columna valor es DECIMAL(14,2): dos decimales, porque es dinero. Y el
 * numero que se enseña en la pantalla es el mismo que se va a guardar, ya
 * redondeado. Enseñar uno y guardar otro es la forma de que el usuario mire un
 * 7.050,63 y al buscarlo un dia encuentre 7.050,63 y no vea por que el
 * documento dice 7.050,64.
 */
class ValoracionOroService
{
    /**
     * Quanto vale una recuperacion en una fecha y una moneda.
     *
     * @return array{
     *     ok: bool,
*     fecha: string,
     *     gramos: float,
     *     pureza: ?float,
     *     gramos_valorados: float,
     *     usa_pureza: bool,
     *     precio_oro_id: ?int,
     *     precio_del_dia: ?string,
     *     unidad_precio: ?string,
     *     precio_gramo: ?float,
     *     valor_en_moneda_precio: ?float,
     *     tipo_cambio: ?float,
     *     valor: ?float
     * }
     */
    public function calcular(
        Recuperaciones $recuperacion,
        string $fecha,
        int $precioMonedaId,
        int $valorMonedaId
    ): array {
        $gramos = (float) $recuperacion->gramos;
        $pureza = $recuperacion->pureza === null ? null : (float) $recuperacion->pureza;

        /*
         * Los gramos que se valoran. Con pureza, los finos; sin ella, los que
         * salieron. Y se guarda si se ha usado la pureza, porque la pantalla lo
         * dice: si el valor sale de 1,5 gramos y la fila decia 2, sin ese
         * aviso alguien pensaria que la cuenta esta mal.
         */
        $usaPureza = $pureza !== null && $pureza > 0;
        $gramosValorados = $usaPureza ? $gramos * $pureza : $gramos;

        $base = [
            'gramos' => $gramos,
            'pureza' => $pureza,
            'fecha' => $fecha,
            'gramos_valorados' => $gramosValorados,
            'usa_pureza' => $usaPureza,
            'precio_oro_id' => null,
            'precio_del_dia' => null,
            'unidad_precio' => null,
            'precio_gramo' => null,
            'valor_en_moneda_precio' => null,
            'tipo_cambio' => null,
            'valor' => null,
        ];

        $precio = PreciosOro::precioDelGramo($fecha, $precioMonedaId);

        if ($precio === null) {
            return array_merge($base, [
                'ok' => false,
                'motivo' => 'No hay ningún precio del oro anterior al ' . $fecha
                    . '. Carga el precio de ese día en Precios del Oro y vuelve a intentarlo. '
                    . 'Un cero en la serie no vale para valorar: significa que de ese día no se '
                    . 'sabía el precio, y usarlo daría una valoración de cero.',
            ]);
        }

        $base['precio_oro_id'] = $precio['id'];
        $base['precio_del_dia'] = $precio['de_que_dia'];
        $base['unidad_precio'] = $precio['unidad'];
        $base['precio_gramo'] = $precio['gramo'];

        $enMonedaPrecio = round($gramosValorados * $precio['gramo'], 2);

        $base['valor_en_moneda_precio'] = $enMonedaPrecio;

        /*
         * Si el valor se guarda en la misma moneda en la que esta el precio,
         * no hace falta convertir nada. Es el caso de tener el precio del banco
         * en cordoba y valorar en cordoba, que no necesita tipo de cambio para
         * nada.
         */
        if ($precioMonedaId === $valorMonedaId) {
            return array_merge($base, [
                'ok' => true,
                'motivo' => '',
                'valor' => $enMonedaPrecio,
            ]);
        }

        $alCordoba = TiposCambio::vigentePara($precioMonedaId, $fecha);

        if ($alCordoba === null) {
            return array_merge($base, [
                'ok' => false,
                'motivo' => 'Hay precio del oro, pero no hay tipo de cambio de esa moneda para el '
                    . $fecha . ', y hace falta para pasar el valor a la moneda en la que se va a '
                    . 'guardar. Carga el tipo de cambio de ese día.',
            ]);
        }

        /*
         * ¿Se quiere el valor ya en cordoba?
         *
         * El tipo de cambio que guarda el sistema es siempre "cuántos córdobas
         * vale una unidad de esa moneda", así que la moneda base es el paso
         * natural y no hace falta convertir de más. Y es lo normal: la
         * contabilidad del taller está en córdobas.
         *
         * Se pregunta a la tabla de monedas y no se comprueba si el id es el
         * primero, porque la base es un dato del catálogo y puede ser
         * cualquiera de las que haya.
         */
        $monedaBaseId = (int) (Moneda::where('es_moneda_base', true)->value('id') ?? 0);

        $enCordoba = $valorMonedaId === $monedaBaseId && $monedaBaseId > 0;

        $valor = $enMonedaPrecio * $alCordoba;

        /*
         * Y si el valor no se quiere en cordoba sino en una tercera moneda, se
         * pasa por el cordoba. Que es el unico camino que hay: el tipo de
         * cambio que se guarda es "cuantos cordobas vale una unidad", y para ir
         * de cordoba a otra moneda hay que dividir, no multiplicar.
         *
         * Es el caso raro —el taller que factura en dolares con el precio en
         * cordoba— y por eso va al final y no en medio: si se pone aqui, el
         * caso normal, que es un salto, se lleva por delante la cuenta del
         * segundo.
         */
        if (! $enCordoba) {
            $desdeCordoba = TiposCambio::vigentePara($valorMonedaId, $fecha);

            if ($desdeCordoba === null || $desdeCordoba <= 0) {
                return array_merge($base, [
                    'ok' => false,
                    'motivo' => 'Hay precio del oro y hay tipo de cambio, pero no el de la moneda '
                        . 'en la que se quiere guardar el valor. Carga ese tipo de cambio.',
                ]);
            }

            $valor = $valor / $desdeCordoba;
        }

        /*
         * El tipo de cambio que se devuelve es el que se aplico DE VERDAD.
         *
         * Y no el primero que se busca, que es lo que estaba puesto antes: en
         * el camino de tres monedas se aplican dos cambios —el de la moneda del
         * precio al cordoba y el del cordoba a la del valor— y enseñar el
         * segundo, que es una division por menos de uno, dejaria en el aviso un
         * numero que no es por el que se multiplico. Enseñar el del primer
         * salto, que es el unico que multiplica, es lo que permite
         * comprobar la cuenta a mano con un papel.
         */
        $base['tipo_cambio'] = $alCordoba;

        return array_merge($base, [
            'ok' => true,
            'motivo' => '',
            'valor' => round($valor, 2),
        ]);
    }
}
