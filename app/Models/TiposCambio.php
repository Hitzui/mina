<?php

namespace App\Models;

use App\Models\Base\TiposCambio as BaseTiposCambio;
use App\Models\Concerns\ClaveUnica;

class TiposCambio extends BaseTiposCambio
{
	use ClaveUnica;

	protected $fillable = [
		self::FECHA,
		self::MONEDA_ID,
		self::VALOR,
		self::FUENTE,
		self::OBSERVACIONES
	];

	/**
	 * Tipo de cambio vigente de una moneda en una fecha.
	 *
	 * Devuelve null cuando no hay ninguno, para que cada llamador decida
	 * como avisar en vez de que se le imponga una politica.
	 *
	 * Vive aqui y no en el controlador porque lo necesitan varios
	 * modulos (trabajos de empleados y costos) y la documentacion pide
	 * justo eso: centralizar el calculo de equivalentes NIO para que dos
	 * copias de la misma regla no acaben dando numeros distintos.
	 *
	 * Se toma el registro mas reciente cuya fecha sea menor o igual a la
	 * pedida. Entre dos tipos de cambio del mismo dia gana el de mayor id,
	 * que es el ultimo que se corrigio.
	 */
	public static function vigentePara(int $monedaId, string $fecha): ?float
	{
		$registro = static::query()
			->where(self::MONEDA_ID, $monedaId)
			->whereDate(self::FECHA, '<=', $fecha)
			->orderByDesc(self::FECHA)
			->orderByDesc(self::ID)
			->first();

		if (!$registro || !is_numeric($registro->valor)) {
			return null;
		}

		$valor = (float) $registro->valor;

		return $valor > 0 ? $valor : null;
	}
}
