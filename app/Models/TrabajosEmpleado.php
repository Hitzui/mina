<?php

namespace App\Models;

use App\Models\Base\TrabajosEmpleado as BaseTrabajosEmpleado;

class TrabajosEmpleado extends BaseTrabajosEmpleado
{
	protected $fillable = [
		self::EMPLEADO_ID,
		self::ORDEN_TRABAJO_ID,
		self::PROCESO_ORDEN_ID,
		self::TIPO_PAGO_ID,
		self::FECHA,
		self::HORA_INICIO,
		self::HORA_FIN,
		self::DESCRIPCION,
		self::CANTIDAD,
		self::TARIFA,
		self::TOTAL,
		self::TARIFA_NIO,
		self::TOTAL_NIO,
		self::MONEDA_ID,
		self::OBSERVACIONES,
		self::UNIDAD,
        self::TIPO_CAMBIO
	];
}
