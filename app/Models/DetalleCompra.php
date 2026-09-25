<?php

namespace App\Models;

use App\Models\Base\DetalleCompra as BaseDetalleCompra;

class DetalleCompra extends BaseDetalleCompra
{
	protected $fillable = [
		self::COMPRA_ID,
		self::PRODUCTO_ID,
		self::CANTIDAD,
		self::COSTO_UNITARIO,
		self::SUBTOTAL
	];
}
