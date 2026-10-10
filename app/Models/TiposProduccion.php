<?php

namespace App\Models;

use App\Models\Base\TiposProduccion as BaseTiposProduccion;
use Illuminate\Support\Facades\DB;

class TiposProduccion extends BaseTiposProduccion
{
    protected $fillable = [
        self::NOMBRE,
        self::DESCRIPCION,
        self::ESTADO,
    ];

    /** Tipos disponibles al registrar una producción nueva. */
    public function scopeParaRegistrar($query)
    {
        return $query->where(self::ESTADO, true)->orderBy(self::NOMBRE);
    }

    /** Cantidad de producciones que conservan referencia a este tipo. */
    public function usos(): array
    {
        $filas = DB::table('producciones')
            ->where(Produccione::TIPO_PRODUCCION_ID, $this->id)
            ->selectRaw('COUNT(*) AS cuantas, SUM(deleted_at IS NOT NULL) AS dadas')
            ->first();

        if (! $filas || (int) $filas->cuantas === 0) {
            return [];
        }

        return [[
            'tabla' => 'producciones',
            'donde' => 'producciones',
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
        $frase = $cuantas.' producción'.($cuantas === 1 ? '' : 'es');

        if ($uso['dadasDeBaja'] > 0) {
            $frase .= ' ('.$uso['dadasDeBaja'].' dada'.($uso['dadasDeBaja'] === 1 ? '' : 's').' de baja)';
        }

        return $frase;
    }
}
