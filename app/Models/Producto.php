<?php

namespace App\Models;

use App\Models\Base\Producto as BaseProducto;

class Producto extends BaseProducto
{
	protected $fillable = [
		self::CODIGO,
		self::NOMBRE,
		self::DESCRIPCION,
		self::UNIDAD_MEDIDA,
		self::CATEGORIA,
		self::STOCK_MINIMO,
		self::ESTADO
	];
}
