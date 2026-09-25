<?php

namespace App\Models;

use App\Models\Base\TiposPagoEmpleado as BaseTiposPagoEmpleado;

class TiposPagoEmpleado extends BaseTiposPagoEmpleado
{
	protected $fillable = [
		self::NOMBRE,
		self::DESCRIPCION,
		self::ESTADO
	];
}
