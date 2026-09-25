<?php

namespace App\Models;

use App\Models\Base\Empleado as BaseEmpleado;

class Empleado extends BaseEmpleado
{
	protected $fillable = [
		self::CODIGO,
		self::NOMBRE,
		self::TELEFONO,
		self::TIPO_EMPLEADO_ID,
		self::FECHA_INGRESO,
		self::ESTADO,
		self::OBSERVACIONES
	];
}
