<?php

namespace App\Models;

use App\Models\Base\Liquidacione as BaseLiquidacione;

class Liquidacione extends BaseLiquidacione
{
	protected $fillable = [
		self::ORDEN_TRABAJO_ID,
		self::FECHA,
		self::GRAMOS_RECUPERADOS,
		self::GRAMOS_CLIENTE,
		self::GRAMOS_EMPRESA,
		self::PORCENTAJE_CLIENTE,
		self::PORCENTAJE_EMPRESA,
		self::OBSERVACIONES
	];
}
