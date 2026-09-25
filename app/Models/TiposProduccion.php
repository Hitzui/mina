<?php

namespace App\Models;

use App\Models\Base\TiposProduccion as BaseTiposProduccion;

class TiposProduccion extends BaseTiposProduccion
{
	protected $fillable = [
		self::NOMBRE,
		self::DESCRIPCION,
		self::ESTADO
	];
}
