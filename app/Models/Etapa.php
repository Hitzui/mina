<?php

namespace App\Models;

use App\Models\Base\Etapa as BaseEtapa;

class Etapa extends BaseEtapa
{
	protected $fillable = [
		self::NOMBRE,
		self::DESCRIPCION,
		self::ORDEN,
		self::ESTADO
	];
}
