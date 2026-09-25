<?php

namespace App\Models;

use App\Models\Base\ProcesosOrden as BaseProcesosOrden;

class ProcesosOrden extends BaseProcesosOrden
{
	protected $fillable = [
		self::ORDEN_TRABAJO_ID,
		self::ETAPA_ID,
		self::FECHA_INICIO,
		self::FECHA_FIN,
		self::PESO_ENTRADA,
		self::PESO_SALIDA,
		self::ESTADO,
		self::OBSERVACIONES,
		self::CODIGO
	];
}
