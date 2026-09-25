<?php

namespace App\Models;

use App\Models\Base\EmpleadosPago as BaseEmpleadosPago;

class EmpleadosPago extends BaseEmpleadosPago
{
	protected $fillable = [
		self::EMPLEADO_ID,
		self::TIPO_PAGO_ID,
		self::TARIFA,
		self::MONEDA_ID,
		self::FECHA_INICIO,
		self::FECHA_FIN,
		self::ESTADO,
		self::OBSERVACIONES
	];
}
