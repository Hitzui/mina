<?php

namespace App\Models;

use App\Models\Base\MovimientosInventario as BaseMovimientosInventario;

class MovimientosInventario extends BaseMovimientosInventario
{
	protected $fillable = [
		self::PRODUCTO_ID,
		self::ORDEN_TRABAJO_ID,
		self::PROCESO_ORDEN_ID,
		self::TIPO,
		self::FECHA,
		self::CANTIDAD,
		self::MONEDA_ID,
		self::COSTO_UNITARIO,
		self::COSTO_TOTAL,
		self::REFERENCIA,
		self::OBSERVACIONES,
		self::COSTO_UNITARIO_NIO,
		self::COSTO_TOTAL_NIO
	];
}
