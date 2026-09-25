<?php

namespace App\Models;

use App\Models\Base\InventarioProducto as BaseInventarioProducto;

class InventarioProducto extends BaseInventarioProducto
{
	protected $fillable = [
		self::PRODUCTO_ID,
		self::CANTIDAD_ACTUAL,
		self::VALOR_ACTUAL,
		self::CPP_ACTUAL
	];
}
