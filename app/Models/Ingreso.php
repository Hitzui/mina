<?php

namespace App\Models;

use App\Models\Base\Ingreso as BaseIngreso;

/**
 * Lo que entra en una orden de trabajo.
 *
 * Es la otra cara de los costos: los costos son lo que sale y los ingresos lo
 * que entra. Viven en la misma ficha de la orden, en secciones separadas, y por
 * eso el total va en una columna y no se calcula al vuelo entre las dos: lo que
 * sale y lo que entra son numeros de sistemas distintos, y mezclarlos en un solo
 * total daria un beneficio que en este taller no significa nada —una orden puede
 * no haber dado dinero, y eso no es una perdida sino como funciona.
 *
 * LA MONEDA BASE NO NECESITA TIPO DE CAMBIO, y por eso el total_nio tambien se
 * escribe cuando la moneda es la base. En el resto del sistema, cuando la moneda
 * es la base el equivalente se queda a null porque no hay nada que convertir.
 * Aqui no: un ingreso en cordoba tiene su equivalente en cordoba, y es el mismo
 * numero. Dejarlo vacio haria que una suma de ingresos dijera "no convertible"
 * de una fila que si lo es, y el total de la orden saldria mas bajo del que es.
 */
class Ingreso extends BaseIngreso
{
	protected $fillable = [
		self::ORDEN_TRABAJO_ID,
		self::LIQUIDACION_ID,
		self::TIPO_INGRESO_ID,
		self::FECHA,
		self::DESCRIPCION,
		self::CANTIDAD,
		self::UNIDAD_MEDIDA,
		self::PRECIO_UNITARIO,
		self::TOTAL,
		self::PRECIO_UNITARIO_NIO,
		self::TOTAL_NIO,
		self::MONEDA_ID,
		self::ESTADO,
		self::OBSERVACIONES
	];

	/**
	 * Los ingresos de una orden.
	 *
	 * Van por fecha de mas reciente a mas antigua y no por id: lo que se mira al
	 * entrar en una orden es el ultimo ingreso que se registro, no el primero que
	 * se escribio en el sistema.
	 */
	public function scopeDeOrden($query, int $ordenTrabajoId)
	{
		return $query->where(self::ORDEN_TRABAJO_ID, $ordenTrabajoId)
			->orderByDesc(self::FECHA)
			->orderByDesc(self::ID);
	}

	/**
	 * Calcula el total y su equivalente en cordoba, y los deja actualizados.
	 *
	 * El total NUNCA se acepta del formulario: se calcula aqui con la cantidad y
	 * el precio unitario, que son lo unico que viene de la pantalla. Si se
	 * recibiera ya calculado, un valor equivocado en el formulario se guardaria
	 * como si fuera verdad y no habria forma de saber que no cuadra con la
	 * multiplicacion.
	 *
	 * El equivalente en cordoba se guarda y no se recalcula al leer, y por el
	 * mismo motivo que la depreciacion y que los costos: si manana se corrige el
	 * tipo de cambio de una fecha, los ingresos de una orden ya cerrada no deben
	 * moverse. Un documento que cambia de valor solo porque se corrigio una tabla
	 * de hace tres meses no es un documento, es una consulta.
	 *
	 * Y con la moneda base el tipo de cambio es 1, no null. Ver el comentario de
	 * la clase, que explica por que aqui es distinto del resto del sistema.
	 *
	 * @param  float|null  $tipoCambio  Cuantos cordobas vale una unidad de la
	 *                                   moneda del ingreso, o null si no se
	 *                                   encontro ninguno.
	 */
	public function calcularTotales(?float $tipoCambio): self
	{
		$this->total = round(
			(float) $this->cantidad * (float) $this->precio_unitario,
			2
		);

		if ($tipoCambio !== null) {
			$this->precio_unitario_nio = round(
				(float) $this->precio_unitario * $tipoCambio,
				4
			);

			$this->total_nio = round($this->total * $tipoCambio, 2);
		} else {
			$this->precio_unitario_nio = null;
			$this->total_nio = null;
		}

		return $this;
	}
}
