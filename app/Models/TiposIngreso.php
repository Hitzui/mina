<?php

namespace App\Models;

use App\Models\Base\TiposIngreso as BaseTiposIngreso;

class TiposIngreso extends BaseTiposIngreso
{
	protected $fillable = [
		self::NOMBRE,
		self::DESCRIPCION,
		self::ESTADO
	];
}
