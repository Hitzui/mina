<?php

namespace App\Models;

use App\Models\Base\Liquidacione as BaseLiquidacione;

class Liquidacione extends BaseLiquidacione
{
    protected $fillable = [
        self::ORDEN_TRABAJO_ID,
        self::FECHA,
        self::GRAMOS_RECUPERADOS,
        self::GRAMOS_CLIENTE,
        self::GRAMOS_EMPRESA,
        self::PORCENTAJE_CLIENTE,
        self::PORCENTAJE_EMPRESA,
        self::OBSERVACIONES,
    ];

    public function scopeDeOrden($query, int $ordenTrabajoId)
    {
        return $query->where(self::ORDEN_TRABAJO_ID, $ordenTrabajoId);
    }

    /** Calcula en cuatro decimales y asigna el residuo a la parte empresa. */
    public function calcularReparto(float $gramos, float $porcentajeCliente, float $porcentajeEmpresa): self
    {
        $this->gramos_recuperados = round($gramos, 4);
        $this->porcentaje_cliente = round($porcentajeCliente, 4);
        $this->porcentaje_empresa = round($porcentajeEmpresa, 4);
        $this->gramos_cliente = round($this->gramos_recuperados * $this->porcentaje_cliente / 100, 4);
        $this->gramos_empresa = round($this->gramos_recuperados - $this->gramos_cliente, 4);

        return $this;
    }
}
