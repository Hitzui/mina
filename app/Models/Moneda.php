<?php

namespace App\Models;

use App\Models\Base\Moneda as BaseMoneda;

class Moneda extends BaseMoneda
{
	protected $fillable = [
		self::CODIGO,
		self::NOMBRE,
		self::SIMBOLO,
		self::ES_MONEDA_BASE,
		self::ESTADO
	];
}
