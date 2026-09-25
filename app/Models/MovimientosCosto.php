<?php

namespace App\Models;

use App\Models\Base\MovimientosCosto as BaseMovimientosCosto;

class MovimientosCosto extends BaseMovimientosCosto
{
	protected $fillable = [
		self::ORDEN_TRABAJO_ID,
		self::PROCESO_ORDEN_ID,
		self::CATEGORIA_COSTO_ID,
		self::FECHA,
		self::DESCRIPCION,
		self::CANTIDAD,
		self::COSTO_UNITARIO,
		self::COSTO_TOTAL,
		self::COSTO_UNITARIO_NIO,
		self::COSTO_TOTAL_NIO,
		self::MONEDA_ID,
		self::OBSERVACIONES
	];
}
