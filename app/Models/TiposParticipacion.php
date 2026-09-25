<?php

namespace App\Models;

use App\Models\Base\TiposParticipacion as BaseTiposParticipacion;

class TiposParticipacion extends BaseTiposParticipacion
{
	protected $fillable = [
		self::NOMBRE,
		self::DESCRIPCION,
		self::ESTADO
	];
}
