<?php

namespace App\Models;

use App\Models\Base\Produccione as BaseProduccione;

class Produccione extends BaseProduccione
{
    protected $fillable = [
        self::ORDEN_TRABAJO_ID,
        self::PROCESO_ORDEN_ID,
        self::TIPO_PRODUCCION_ID,
        self::FECHA,
        self::DESCRIPCION,
        self::CANTIDAD,
        self::UNIDAD_MEDIDA,
        self::UNIDAD_MEDIDA_ID,
        self::OBSERVACIONES,
    ];

    public const UNIDAD_MEDIDA_ID = 'unidad_medida_id';

    public function unidadMedida()
    {
        return $this->belongsTo(UnidadMedida::class, self::UNIDAD_MEDIDA_ID);
    }
}
