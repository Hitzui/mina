<?php

namespace App\Models;

use App\Models\Base\Cliente as BaseCliente;

class Cliente extends BaseCliente
{
	protected $fillable = [
		self::NOMBRE,
		self::TELEFONO,
		self::DIRECCION,
		self::ESTADO
	];
}
