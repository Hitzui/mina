<?php

namespace App\Models;

use App\Models\Base\TiposCambio as BaseTiposCambio;

class TiposCambio extends BaseTiposCambio
{
	protected $fillable = [
		self::FECHA,
		self::MONEDA_ID,
		self::VALOR,
		self::FUENTE,
		self::OBSERVACIONES
	];
}
