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
	 * La valoracion de esta recuperacion, si la tiene.
	 *
	 * Va declarada aqui y no se pide con una consulta aparte porque la
	 * necesitan dos sitios que miran la lista de recuperaciones de golpe: el
	 * desplegable de la pantalla de valoraciones, para marcar las que ya estan
	 * valoradas, y el saber si al borrar una recuperacion se lleva un valor
	 * consigo.
	 *
	 * Es una sola, y no una coleccion, porque hay un indice unico en la base
	 * que lo garantiza. Esa es justo la razon de que sea hasOne y no
	 * hasMany: si la relacion admitiera varias, el codigo que recorre la lista
	 * tendria que decidir cual enseña, y con el indice no hay esa decision que
	 * tomar.
	 */
	public function valoracion()
	{
		return $this->hasOne(ValoracionesOro::class, ValoracionesOro::RECUPERACION_ID);
	}

	/**
	 * Si esta recuperacion ya tiene un valor puesto.
	 */
	public function estaValorada(): bool
	{
		return $this->valoracion !== null;
	}

	/**
	 * Si la orden de la que salio estos gramos esta cancelada.
	 *
	 * Se pregunta y no se deduce del estado de la recuperacion, que no lo
	 * guarda: lo que se guarda es el estado de la ORDEN, que es lo unico que
	 * se cancela. Una recuperacion de una orden cancelada se puede borrar y
	 * corregir como cualquier otra —el registro es real, paso—; lo que no se
	 * puede es valorarla, porque no se hizo el trabajo.
	 */
	public function ordenEstaCancelada(): bool
	{
		return (int) $this->orden_trabajo?->estado === OrdenesTrabajo::ESTADO_CANCELADA;
	}

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
