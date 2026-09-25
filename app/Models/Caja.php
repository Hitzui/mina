<?php

namespace App\Models;

use App\Models\Base\Caja as BaseCaja;

class Caja extends BaseCaja
{
	protected $fillable = [
		self::NOMBRE,
		self::DESCRIPCION,
		self::ESTADO
	];
}
