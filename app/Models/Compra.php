<?php

namespace App\Models;

use App\Models\Base\Compra as BaseCompra;

class Compra extends BaseCompra
{
	protected $fillable = [
		self::CODIGO,
		self::PROVEEDOR_ID,
		self::FECHA,
		self::NUMERO_DOCUMENTO,
		self::SUBTOTAL,
		self::IMPUESTO,
		self::TOTAL,
		self::MONEDA_ID,
		self::ESTADO,
		self::OBSERVACIONES
	];
}
