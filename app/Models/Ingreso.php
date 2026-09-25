<?php

namespace App\Models;

use App\Models\Base\Ingreso as BaseIngreso;

class Ingreso extends BaseIngreso
{
	protected $fillable = [
		self::ORDEN_TRABAJO_ID,
		self::LIQUIDACION_ID,
		self::TIPO_INGRESO_ID,
		self::FECHA,
		self::DESCRIPCION,
		self::CANTIDAD,
		self::UNIDAD_MEDIDA,
		self::PRECIO_UNITARIO,
		self::TOTAL,
		self::PRECIO_UNITARIO_NIO,
		self::TOTAL_NIO,
		self::MONEDA_ID,
		self::ESTADO,
		self::OBSERVACIONES
	];
}
