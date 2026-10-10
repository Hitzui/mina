<?php

namespace App\Models;

use App\Models\Base\TiposParticipacion as BaseTiposParticipacion;
use Illuminate\Support\Facades\DB;

class TiposParticipacion extends BaseTiposParticipacion
{
    protected $fillable = [
        self::NOMBRE,
        self::DESCRIPCION,
        self::ESTADO,
    ];

    public function scopeParaRegistrar($query)
    {
        return $query->where(self::ESTADO, true)->orderBy(self::NOMBRE);
    }

    /** Acuerdos que conservan referencia, incluidos los dados de baja. */
    public function usos(): array
    {
        $filas = DB::table('acuerdos_orden')
            ->where(AcuerdosOrden::TIPO_PARTICIPACION_ID, $this->id)
            ->selectRaw('COUNT(*) AS cuantas, SUM(deleted_at IS NOT NULL) AS dadas')
            ->first();

        if (! $filas || (int) $filas->cuantas === 0) {
            return [];
        }

        return [[
            'tabla' => 'acuerdos_orden',
            'donde' => 'acuerdos',
            'cuantas' => (int) $filas->cuantas,
            'dadasDeBaja' => (int) $filas->dadas,
        ]];
    }

    public function sePuedeBorrar(): bool
    {
        return $this->usos() === [];
    }

    public function fraseDeUsos(): string
    {
        $uso = $this->usos()[0] ?? null;
        if ($uso === null) {
            return '';
        }

        $cuantas = $uso['cuantas'];
        $frase = $cuantas.' acuerdo'.($cuantas === 1 ? '' : 's');
        if ($uso['dadasDeBaja'] > 0) {
            $frase .= ' ('.$uso['dadasDeBaja'].' dado'.($uso['dadasDeBaja'] === 1 ? '' : 's').' de baja)';
        }

        return $frase;
    }
}
