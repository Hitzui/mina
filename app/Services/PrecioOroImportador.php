<?php

namespace App\Services;

/**
 * Lee el Excel del precio del oro y lo deja como filas limpias.
 *
 * El precio del oro es una serie por dias, igual que el tipo de cambio, y se
 * carga igual: un archivo de Excel con una fila por dia. De leer el archivo no
 * se ocupa esta clase, sino la base, que es la misma que usa el tipo de
 * cambio.
 *
 * Lo unico que se dice aqui es que nombres de columna se aceptan y como se
 * llama la magnitud en los avisos, que para el oro es "precio".
 *
 * Los nombres de la fecha son los mismos que los del tipo de cambio mas uno.
 * El que se anade es "mes", porque el archivo del precio del oro se arma
 * mucho con el mes en la cabecera en vez de con la fecha entera: "septiembre
 * de 2026" y abajo un dia del 1 al 30, una fila por dia, que es la forma que
 * tiene el sitio que publica el precio cuando lo pasa a Excel.
 *
 * Y pasa lo que se ha visto en el archivo del banco, que es lo unico que se
 * puede hacer con una fecha de ese tipo: no se entiende y se avisa. Fijarse
 * de que la primera palabra es el mes y deducir el año no es una opcion: el
 * archivo no dice de que año es, y un año inventado deja el mes entero
 * desplazado, que es peor que un archivo no leido. El usuario lo tiene que
 * dejar bien, y hay un boton que le da un ejemplo con las fechas ya puestas.
 */
class PrecioOroImportador extends LectorDeExcelDeFechasYNumeros
{
    /**
     * Nombres que se aceptan en la columna de la fecha.
     *
     * @return array<int, string>
     */
    protected function nombresDeFecha(): array
    {
        return [
            'fecha', 'fechacambio', 'fechadedoperacion', 'fechadeoperacion',
            'fechaoperacion', 'dia', 'fecdia', 'diaoperacion', 'mes',
        ];
    }

    /**
     * Nombres que se aceptan en la columna del precio.
     *
     * Se pone "valor" tambien, y no solo "precio", porque el archivo lo arma
     * alguien a mano y en la mitad de los casos esa columna se llama asi. Al
     * reves no hace falta: un archivo con una columna "precio" y otra
     * "valor" solo se equivoca con una, y el servicio se queda con la
     * primera que encuentra, que es la que esta mas a la izquierda.
     *
     * @return array<int, string>
     */
    protected function nombresDeValor(): array
    {
        return [
            'precio', 'preciodeloro', 'valor', 'preciooro', 'cotizacion',
            'valordolar', 'preciodolar',
        ];
    }

    protected function nombreDeLoQueSeImporta(): string
    {
        return 'precio';
    }
}
