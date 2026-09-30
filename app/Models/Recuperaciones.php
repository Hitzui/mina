<?php

namespace App\Models;

use App\Models\Base\Recuperacione as BaseRecuperaciones;

class Recuperaciones extends BaseRecuperaciones
{
	protected $fillable = [
		self::ORDEN_TRABAJO_ID,
		self::FECHA,
		self::GRAMOS,
		self::PUREZA,
		self::OBSERVACIONES
	];

	/**
	 * Cuantos gramos se recuperaron, ya con la pureza puesta si la hay.
	 *
	 * Devuelve los gramos tal cual y no "los gramos del oro fino". Es
	 * deliberado, y la razon es que la pureza aqui no siempre se sabe: la
	 * tabla la admite a null y hay recuperaciones en las que nadie midio la
	 * pureza. Si el metodo devolviera solo el oro fino, en esas
	 * recuperaciones devolveria null o cero, y quien lo llamara no
	 * distinguiria entre "no se sabe la pureza" y "no se recupero nada", que
	 * son dos cosas muy distintas.
	 *
	 * Quien necesite el oro fino tiene que decidir que hacer con la falta de
	 * pureza, y esa decision es suya: puede usar los gramos tal cual, que es
	 * lo que se ha hecho siempre, o puede mirar la tabla de precios y ver
	 * por que unidad esta dado el precio.
	 */
	public function gramosRecuperados(): float
	{
		return (float) $this->gramos;
	}

	/**
	 * La pureza en porcentaje, para enseñarla.
	 *
	 * La columna guarda una fraccion —1.000000 es cien por ciento—, que es lo
	 * que dice la documentacion. En pantalla, sin embargo, nadie razona en
	 * fracciones: se dice "91,5 %" y no "0,915". La conversion va aqui y no
	 * en la vista porque hay tres sitios que la necesitan —la lista, la ficha y
	 * el valoracion— y en cuanto se escribe en dos, uno se queda atras.
	 *
	 * Cuando no hay pureza, se devuelve null y no cero: cero por ciento de
	 * pureza no es lo mismo que no saber la pureza, y en la lista se ven
	 * distinto a proposito.
	 */
	public function purezaEnPorcentaje(): ?float
	{
		if ($this->pureza === null || !is_numeric($this->pureza)) {
			return null;
		}

		return round((float) $this->pureza * 100, 2);
	}

	/**
	 * Si el precio con el que se va a valorar esta en dolares.
	 *
	 * El precio del oro se guarda con su moneda, y el tipo de cambio de esa
	 * moneda el dia de esa fecha es lo que lo pasa a cordoba. Va como
	 * pregunta y no como calculo a proposito: el valor final lo decide
	 * valoraciones_oro, que es la que guarda el resultado, y meter aqui el
	 * calculo del valor seria tener la misma regla en dos sitios.
	 *
	 * Se deja el metodo aun asi, para el sitio que quiera preguntar "de que
	 * moneda es el precio que se va a usar" sin tener que acordarse de la
	 * llamada.
	 */
	public function monedaDelPrecio(): int
	{
		return (int) Moneda::where('es_moneda_base', true)->value('id');
	}
}
