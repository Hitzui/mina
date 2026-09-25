<?php

namespace App\Models;

use App\Models\Base\MetodosPago as BaseMetodosPago;

class MetodosPago extends BaseMetodosPago
{
	protected $fillable = [
		self::NOMBRE,
		self::DESCRIPCION,
		self::ESTADO
	];
}
