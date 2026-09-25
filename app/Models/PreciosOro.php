<?php

namespace App\Models;

use App\Models\Base\PreciosOro as BasePreciosOro;

class PreciosOro extends BasePreciosOro
{
	protected $fillable = [
		self::FECHA,
		self::PRECIO,
		self::UNIDAD,
		self::MONEDA_ID,
		self::FUENTE,
		self::OBSERVACIONES
	];
}
