<?php

namespace App\Models;

use App\Models\Base\AcuerdosOrden as BaseAcuerdosOrden;

class AcuerdosOrden extends BaseAcuerdosOrden
{
	protected $fillable = [
		self::ORDEN_TRABAJO_ID,
		self::TIPO_PARTICIPACION_ID,
		self::PORCENTAJE_CLIENTE,
		self::PORCENTAJE_EMPRESA,
		self::TARIFA_SERVICIO,
		self::MONEDA_ID,
		self::OBSERVACIONES,
		self::ESTADO
	];
}
