<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * El codigo de un alta lo pone el sistema y se va incrementando.
 *
 * Es para las tablas cuyo codigo es solo una etiqueta interna y que el
 * usuario no debería tener que escribir: los materiales y los proveedores.
 * Escribir un codigo a mano es justo la clase de tarea que se hace mal: el
 * mismo numero escrito de cuatro maneras distintas, y el indice unico de la
 * base rechaza uno de ellos con un error que no le dice a nadie de que se
 * trata.
 *
 * El que lo usa tiene que declarar PREFIJO_CODIGO y LARGO_NUMERO_CODIGO, y
 * dejar CODIGO en el $fillable: el codigo lo pone este trait, no el
 * formulario.
 *
 * Vive en un trait y no en cada modelo porque la logica es identica y porque
 * ya se vio lo que cuesta tenerla en dos sitios: cuando se toca una, la otra
 * se queda atras sin que nada lo avise.
 */
trait GeneraCodigo
{
    /**
     * El siguiente codigo libre de este tipo de registro.
     *
     * Se mira tambien entre los borrados logicamente, y a proposito:
     *
     *   - Si solo se mirara entre los vivos, al reutilizar el codigo de uno
     *     borrado habria dos filas con el mismo codigo en el historial, y el
     *     indice unico de la base lo rechazaria.
     *   - Y un registro borrado sigue citado en los movimientos de los
     *     procesos donde se uso: un codigo que vuelve a salir hace imposible
     *     saber de que registro se hablo.
     *
     * Solo cuentan los codigos que son el prefijo seguido de digitos. Con un
     * (int) a secas, un "MAT-1a2b3c" puesto a mano contaria como 1 y un
     * "MAT-12abc" como 12: un codigo raro moveria la cuenta y dejaria huecos
     * sin que nadie supiera por que.
     */
    public static function generarCodigo(): string
    {
        $maximo = 0;

        $codigos = static::withTrashed()
            ->where(static::CODIGO, 'like', static::PREFIJO_CODIGO . '%')
            ->pluck(static::CODIGO);

        foreach ($codigos as $codigo) {
            $numero = substr((string) $codigo, strlen(static::PREFIJO_CODIGO));

            if ($numero !== '' && ctype_digit($numero)) {
                $valor = (int) $numero;

                if ($valor > $maximo) {
                    $maximo = $valor;
                }
            }
        }

        return static::PREFIJO_CODIGO . str_pad(
            (string) ($maximo + 1),
            static::LARGO_NUMERO_CODIGO,
            '0',
            STR_PAD_LEFT
        );
    }

    /**
     * Da de alta el registro con el codigo ya puesto.
     *
     * La cuenta y el alta van en la misma transaccion y se reintentan si
     * chocan con el indice unico. Sin el reintento, dos altas a la vez (dos
     * personas, o la misma en dos pestanas) podrian sacar el mismo codigo: las
     * dos leerian el mismo maximo y la segunda fallaria con un error de la
     * base que no significa nada para quien lo ve.
     *
     * @param  array  $atributos  Lo que viene del formulario. Si trae
     *                             CODIGO, se tira: lo pone generarCodigo.
     *
     * @throws RuntimeException
     */
    public static function crearConCodigo(array $atributos, int $intentos = 3): Model
    {
        unset($atributos[static::CODIGO]);

        $ultimoError = null;

        for ($intento = 1; $intento <= $intentos; $intento++) {
            try {
                return DB::transaction(function () use ($atributos) {
                    $registro = new static($atributos);
                    $registro->codigo = static::generarCodigo();
                    $registro->save();

                    return $registro;
                });
            } catch (UniqueConstraintViolationException $e) {
                $ultimoError = $e;
            }
        }

        /*
         * Tres veces seguidas sin poder colocar un codigo libre. Con el
         * indice unico puesto, eso ya no es una carrera: hay algo mas
         * atascando la tabla, y mas vale que se entere que darle un codigo
         * repetido sin avisar.
         */
        throw new RuntimeException(
            'No se pudo asignar un código libre después de '
            . $intentos . ' intentos. Revise la tabla.',
            0,
            $ultimoError
        );
    }
}
