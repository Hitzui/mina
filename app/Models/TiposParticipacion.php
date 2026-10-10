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

    /** Acuerdos y cotizaciones que conservan la referencia histórica. */
    public function usos(): array
    {
        $usos = [];

        foreach ([
            'acuerdos_orden' => ['donde' => 'acuerdos', 'campo' => AcuerdosOrden::TIPO_PARTICIPACION_ID],
            'cotizaciones' => ['donde' => 'cotizaciones', 'campo' => 'tipo_participacion_id'],
        ] as $tabla => $configuracion) {
            $filas = DB::table($tabla)
                ->where($configuracion['campo'], $this->id)
                ->selectRaw('COUNT(*) AS cuantas, SUM(deleted_at IS NOT NULL) AS dadas')
                ->first();

            if ((int) ($filas->cuantas ?? 0) === 0) {
                continue;
            }

            $usos[] = [
                'tabla' => $tabla,
                'donde' => $configuracion['donde'],
                'cuantas' => (int) $filas->cuantas,
                'dadasDeBaja' => (int) $filas->dadas,
            ];
        }

        return $usos;
    }

    public function sePuedeBorrar(): bool
    {
        return $this->usos() === [];
    }

    public function fraseDeUsos(): string
    {
        $partes = [];

        foreach ($this->usos() as $uso) {
            $esCotizacion = $uso['tabla'] === 'cotizaciones';
            $cuantas = $uso['cuantas'];
            $nombre = $esCotizacion ? 'cotización' : 'acuerdo';
            $frase = $cuantas.' '.$nombre.($cuantas === 1 ? '' : ($esCotizacion ? 'es' : 's'));
            if ($uso['dadasDeBaja'] > 0) {
                $frase .= ' ('.$uso['dadasDeBaja'].' dado'.($uso['dadasDeBaja'] === 1 ? '' : 's').' de baja)';
            }
            $partes[] = $frase;
        }

        if ($partes === []) {
            return '';
        }

        if (count($partes) === 1) {
            return $partes[0];
        }

        $ultima = array_pop($partes);

        return implode(', ', $partes).' y '.$ultima;
    }
}
