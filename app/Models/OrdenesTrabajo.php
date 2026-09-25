<?php

namespace App\Models;

use App\Models\Base\OrdenesTrabajo as BaseOrdenesTrabajo;

class OrdenesTrabajo extends BaseOrdenesTrabajo
{
	protected $fillable = [
		self::CODIGO,
		self::CLIENTE_ID,
		self::FECHA,
		self::DESCRIPCION,
		self::PESO_MINERAL,
		self::UNIDAD_PESO,
		self::ESTADO
	];

    public function procesos_ordenes()
    {
        return $this->hasMany(ProcesosOrden::class, ProcesosOrden::ORDEN_TRABAJO_ID);
    }
}
