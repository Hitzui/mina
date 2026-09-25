<?php

namespace App\Models;

use App\Models\Base\CierresOrden as BaseCierresOrden;

class CierresOrden extends BaseCierresOrden
{
	protected $fillable = [
		self::CODIGO,
		self::ORDEN_TRABAJO_ID,
		self::FECHA_CIERRE,
		self::TOTAL_COSTOS,
		self::TOTAL_INGRESOS,
		self::UTILIDAD,
		self::TOTAL_COBRADO,
		self::SALDO_PENDIENTE,
		self::GRAMOS_RECUPERADOS,
		self::OBSERVACIONES
	];
}
