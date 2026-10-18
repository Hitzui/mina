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
        'lama_destino',
        'lama_porcentaje_empresa',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $acuerdo) {
            if ($acuerdo->isDirty(['porcentaje_empresa', 'porcentaje_cliente', 'tarifa_servicio', 'orden_trabajo_id', 'estado'])
                && OrdenesTrabajo::whereKey($acuerdo->orden_trabajo_id)->whereNotNull('lama_lote_id')->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['acuerdo' => 'El reparto del tratamiento proviene del lote de lama y debe conservarse.']);
            }
        });
        static::deleting(function (self $acuerdo) {
            if (LamaLote::withTrashed()->where('acuerdo_id', $acuerdo->id)->exists()
                || OrdenesTrabajo::whereKey($acuerdo->orden_trabajo_id)->whereNotNull('lama_lote_id')->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['acuerdo' => 'Este acuerdo respalda un lote o tratamiento de lama y debe conservarse.']);
            }
        });
    }

    public function scopeDeOrden($query, int $ordenTrabajoId)
    {
        return $query->where(self::ORDEN_TRABAJO_ID, $ordenTrabajoId);
    }

    public function modalidad(): string
    {
        return $this->tarifa_servicio !== null ? 'tarifa' : 'porcentaje';
    }
}
