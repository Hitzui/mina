<?php

namespace App\Models;

use App\Models\Base\TiposEmpleado as BaseTiposEmpleado;

class TiposEmpleado extends BaseTiposEmpleado
{
	protected $fillable = [
		self::NOMBRE,
		self::DESCRIPCION,
		self::ESTADO
	];
}
