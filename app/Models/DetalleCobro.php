<?php

namespace App\Models;

use App\Models\Base\DetalleCobro as BaseDetalleCobro;

class DetalleCobro extends BaseDetalleCobro
{
	protected $fillable = [
		self::COBRO_ID,
		self::INGRESO_ID,
		self::MONTO,
		self::OBSERVACIONES
	];
}
