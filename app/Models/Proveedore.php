<?php

namespace App\Models;

use App\Models\Base\Proveedore as BaseProveedore;

class Proveedore extends BaseProveedore
{
	protected $fillable = [
		self::CODIGO,
		self::NOMBRE,
		self::TELEFONO,
		self::EMAIL,
		self::DIRECCION,
		self::CONTACTO,
		self::ESTADO,
		self::OBSERVACIONES
	];
}
