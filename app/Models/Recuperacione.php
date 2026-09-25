<?php

namespace App\Models;

use App\Models\Base\Recuperacione as BaseRecuperacione;

class Recuperacione extends BaseRecuperacione
{
	protected $fillable = [
		self::ORDEN_TRABAJO_ID,
		self::FECHA,
		self::GRAMOS,
		self::PUREZA,
		self::OBSERVACIONES
	];
}
