<?php

namespace App\Models;

use App\Models\Base\ValoracionesOro as BaseValoracionesOro;

class ValoracionesOro extends BaseValoracionesOro
{
	protected $fillable = [
		self::RECUPERACION_ID,
		self::PRECIO_ORO_ID,
		self::VALOR,
		self::MONEDA_ID,
		self::FECHA,
		self::OBSERVACIONES
	];
}
