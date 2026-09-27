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

    /**
     * Los trabajos de los empleados de esta orden, alcanzados a traves de
     * sus procesos: el trabajo ya no guarda orden_trabajo_id.
     */
    public function trabajos_empleados()
    {
        return $this->hasManyThrough(
            TrabajosEmpleado::class,
            ProcesosOrden::class,
            'orden_trabajo_id',
            'proceso_orden_id',
            'id',
            'id'
        );
    }
}
