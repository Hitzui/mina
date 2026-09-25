<?php

namespace App\Models;

use App\Models\Base\Cobro as BaseCobro;

class Cobro extends BaseCobro
{
	protected $fillable = [
		self::CLIENTE_ID,
		self::CAJA_ID,
		self::METODO_PAGO_ID,
		self::FECHA,
		self::MONTO,
		self::MONEDA_ID,
		self::REFERENCIA,
		self::ESTADO,
		self::OBSERVACIONES,
		self::CODIGO
	];
}
