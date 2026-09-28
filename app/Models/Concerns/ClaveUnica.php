<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Alta de una fila cuya clave la hace un indice unico, en tablas con
 * borrado logico.
 *
 * El problema que resuelve es el choque entre esas dos cosas, que por
 * separado estan bien y juntas fallan:
 *
 *   - La tabla tiene un indice unico sobre la combinacion que identifica la
 *     fila: en el tipo de cambio, el dia y la moneda. Es lo que garantiza que
 *     un dia no tenga dos valores, y no se puede quitar: es la regla.
 *
 *   - Y la tabla borra de forma logica, de modo que la fila sigue estando en
 *     la base con su deleted_at puesto.
 *
 * Juntas, la fila borrada sigue contando para el indice unico, pero Eloquent
 * no la ve, porque SoftDeletes le anade "and deleted_at is null" a todas las
 * consultas. De ahi sale un fallo que no explica de donde viene:
 *
 *   1. Se consulta si ya existe la fila: exists() devuelve false, porque la
 *      unica que hay esta borrada y no se ve.
 *   2. Se inserta.
 *   3. MySQL responde con "Duplicate entry" en el indice unico, que en
 *      pantalla sale como un fallo entero de la pagina.
 *
 * Y lo que es peor en la importacion, que escribe treinta filas en una
 * transaccion: si el mes se importo, se borro y se vuelve a subir el mismo
 * archivo, las treinta filas chocan y la transaccion entera se deshace. El
 * mensaje dice que se importaron treinta dias y no entra ninguno, y el
 * reintento vuelve a fallar igual.
 *
 * La salida no es quitar el indice —eso seria quitar la regla— sino dejar de
 * crear una fila nueva cuando lo que hace falta es revivir la que ya hay.
 * Restore() en vez de create(), y el indice sigue haciendo lo que tiene que
 * impulsar.
 *
 * Se deja en un trait y no en el modelo porque son varias las tablas con
 * esta forma —el tipo de cambio y el precio del oro son las dos de ahora—,
 * y ya se vio lo que cuesta tener la logica en dos sitios: cuando se toca
 * una, la otra se queda atras sin que nada lo avise.
 */
trait ClaveUnica
{
    /**
     * Crea la fila, o revive la que habia con la misma clave.
     *
     * @param  array  $clave  Las columnas del indice unico, con su valor.
     *                        Se buscan tambien entre las borradas: si esta,
     *                        se restaura en vez de crear otra.
     * @param  array  $atributos  Lo que se escribe. Si el registro ya
     *                            existia —este o borrado— se le aplica
     *                            encima, para que la fila revivida salga con
     *                            los datos nuevos y no con los que tenia
     *                            antes de borrarse.
     * @param  array  $soloAlCrear  Atributos que unicamente se ponen si la
     *                              fila no existia. Sirve para lo que la
     *                              aplicacion no debe pisar nunca en una fila
     *                              que ya estaba: la fuente de un tipo de
     *                              cambio, por ejemplo, que el usuario puede
     *                              haber escrito a mano apuntando de donde
     *                              saco el numero.
     */
    public static function crearORestaurar(array $clave, array $atributos, array $soloAlCrear = []): Model
    {
        $existente = static::withTrashed()
            ->where($clave)
            ->first();

        if (! $existente) {
            return static::create($atributos + $soloAlCrear);
        }

        $existente->restore();

        /*
         * Se escribe siempre, y no solo si estaba borrado.
         *
         * La fila devuelta por withTrashed() puede estar viva: es el caso de
         * una importacion que vuelve a pasar por un dia que ya tiene, y ahi
         * lo que se quiere es el valor nuevo. Y si estaba borrada, aplicarle
         * los atributos nuevos hace falta igualmente: si no, se revive con
         * el valor que tenia antes de borrarse, que puede ser el que el
         * usuario quito precisamente por estar mal.
         */
        $existente->fill($atributos)->save();

        return $existente;
    }

    /**
     * Si hay una fila con esa clave, esta o borrada.
     *
     * Va aparte del crearORestaurar() porque la pantalla la necesita antes de
     * escribir, para poder dar un mensaje. Y tiene que mirar tambien entre
     * las borradas, por el mismo motivo del principio del trait: si no, la
     * pantalla dejaria pasar un alta que despues revienta con un error de
     * MySQL.
     *
     * El segundo valor que devuelve es para que el mensaje diga la verdad:
     * una fila viva se corrige en su sitio, y una borrada hay que restaurarla
     * o crearla con otro dia.
     *
     * @param  int|null  $ignorarId  La fila que se esta editando, para que no
     *                                se compare consigo misma al cambiarle la
     *                                clave.
     * @return array{0: bool, 1: bool}  [existe, esta borrada]
     */
    public static function existeClave(array $clave, ?int $ignorarId = null): array
    {
        $consulta = static::withTrashed()->where($clave);

        if ($ignorarId !== null) {
            $consulta->where(static::ID, '!=', $ignorarId);
        }

        $fila = $consulta->first();

        if (! $fila) {
            return [false, false];
        }

        return [true, $fila->trashed()];
    }
}
