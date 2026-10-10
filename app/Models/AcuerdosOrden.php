<?php

namespace App\Models;

use App\Models\Base\AcuerdosOrden as BaseAcuerdosOrden;

class AcuerdosOrden extends BaseAcuerdosOrden
{
    protected $fillable = [
        self::ORDEN_TRABAJO_ID,
        self::TIPO_PARTICIPACION_ID,
        self::PORCENTAJE_CLIENTE,
        self::PORCENTAJE_EMPRESA,
        self::TARIFA_SERVICIO,
        self::MONEDA_ID,
        self::OBSERVACIONES,
        self::ESTADO,
    ];

    public function scopeDeOrden($query, int $ordenTrabajoId)
    {
        return $query->where(self::ORDEN_TRABAJO_ID, $ordenTrabajoId);
    }

    public function modalidad(): string
    {
        return $this->tarifa_servicio !== null ? 'tarifa' : 'porcentaje';
    }
}
