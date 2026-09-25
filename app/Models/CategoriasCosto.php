<?php

namespace App\Models;

use App\Models\Base\CategoriasCosto as BaseCategoriasCosto;

class CategoriasCosto extends BaseCategoriasCosto
{
	protected $fillable = [
		self::NOMBRE,
		self::DESCRIPCION,
		self::ESTADO
	];
}
