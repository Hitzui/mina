<?php

namespace App\Services;

/**
 * Lee el Excel del tipo de cambio y lo deja como filas limpias.
 *
 * El Banco Central da un archivo por mes, con el tipo de cambio de cada dia.
 * Ese archivo no se lee aqui tal cual: el usuario lo convierte antes a un
 * Excel de dos columnas, porque el del banco trae encabezados, filas en
 * blanco, notas al pie y el tipo de cambio de otras monedas metido de lado, y
 * adivinar cual de todo eso es la cifra que importa acabaria importando
 * cualquier cosa.
 *
 * El formato que se espera es este, y solo este:
 *
 *     fecha      |  valor
 *     01/09/2026 |  36.5824
 *     02/09/2026 |  36.6100
 *
 * Dos columnas, con la fila de arriba puesta, y cada fila un dia.
 *
 * De leer el archivo no se ocupa esta clase: eso esta en la clase base, que
 * es la misma que usa el precio del oro y que tiene todo el trabajo de
 * entender un Excel armado a mano —buscar la hoja con datos, buscar la
 * cabecera entre las cinco primeras filas, los cuatro formatos de fecha que
 * deja Excel, el punto de miles contra el punto decimal— y que no tiene nada
 * de este tipo de cambio en concreto. Aqui solo se dice que nombres de columna
 * se aceptan.
 *
 * Y queda en una clase aparte, y no en un "si me das la lista de nombres" por
 * el mismo motivo que el de mas abajo: el tipo de cambio y el precio del oro
 * se cargan en pantallas distintas, con permisos distintos, y mezclarlos en
 * un mismo servicio seria una clase que leeria cualquier archivo para las dos
 * pantallas.
 *
 * Lo que este servicio NO hace, a proposito, y por que:
 *
 *  - No adivina la columna. Si no encuentra una de fecha y una de valor, lo
 *    dice y para. Un archivo mal ledo es peor que un archivo no ledo: se
 *    veria en la tabla y creeria que es el tipo de cambio del banco.
 *
 *  - No salta los dias que ya hay. Un dia repetido en el archivo se avisa y
 *    deja el primero, que es el que se leyo antes. Importar en silencio lo de
 *    la segunda vez seria dejar el archivo mandando sobre una correccion del
 *    usuario sin que se entere.
 *
 *  - No adivina la moneda. La pone el usuario, en la pantalla, porque el
 *    archivo no dice de que moneda es y equivocarse aqui equivale a tener
 *    treinta dias de dolares guardados como cordobes.
 */
class TipoCambioImportador extends LectorDeExcelDeFechasYNumeros
{
    /**
     * Nombres que se aceptan en la columna de la fecha.
     *
     * Se comparan sin acentos y sin espacios, para que "Fecha de operacion",
     * "FECHA" y "fecha " sean la misma columna. La lista es la del archivo del
     * banco, que es de donde sale: de las nueve formas en las que se ha
     * visto escrito el encabezado de esa columna.
     *
     * @return array<int, string>
     */
    protected function nombresDeFecha(): array
    {
        return [
            'fecha', 'fechacambio', 'fechadedoperacion', 'fechadeoperacion',
            'fechaoperacion', 'dia', 'fecdia', 'diaoperacion',
        ];
    }

    /**
     * Nombres que se aceptan en la columna del valor.
     *
     * @return array<int, string>
     */
    protected function nombresDeValor(): array
    {
        return ['valor', 'tipocambio', 'tc', 'paridad', 'valorcordoba', 'tipodecambio'];
    }

    protected function nombreDeLoQueSeImporta(): string
    {
        return 'valor';
    }
}
