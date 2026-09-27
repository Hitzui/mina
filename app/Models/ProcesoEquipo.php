<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

/**
 * Uso de un equipo dentro de un proceso, con su periodo.
 *
 * Un equipo puede trabajar en varios procesos seguidos, nunca en el
 * mismo instante: por eso el periodo va con fecha y hora, y por eso al
 * guardar se comprueba que no se solape con los otros periodos del mismo
 * equipo.
 */
class ProcesoEquipo extends Model
{
    use SoftDeletes;

    public const ID = 'id';
    public const PROCESO_ORDEN_ID = 'proceso_orden_id';
    public const EQUIPO_ID = 'equipo_id';
    public const FECHA_INICIO = 'fecha_inicio';
    public const FECHA_FIN = 'fecha_fin';
    public const DEPRECIACION_TOTAL = 'depreciacion_total';
    public const OBSERVACIONES = 'observaciones';

    protected $table = 'proceso_equipos';

    protected $fillable = [
        self::PROCESO_ORDEN_ID,
        self::EQUIPO_ID,
        self::FECHA_INICIO,
        self::FECHA_FIN,
        self::OBSERVACIONES,
    ];

    protected $casts = [
        self::ID => 'int',
        self::PROCESO_ORDEN_ID => 'int',
        self::EQUIPO_ID => 'int',
        self::FECHA_INICIO => 'datetime',
        self::FECHA_FIN => 'datetime',
        self::DEPRECIACION_TOTAL => 'float',
    ];

    public function procesoOrden()
    {
        return $this->belongsTo(ProcesosOrden::class, self::PROCESO_ORDEN_ID);
    }

    public function equipo()
    {
        return $this->belongsTo(Equipo::class, self::EQUIPO_ID);
    }

    /**
     * Suma de la depreciacion de los equipos de un proceso.
     *
     * Se calcula al momento, no se guarda: el detalle si queda guardado
     * en cada asignacion.
     */
    public static function depreciacionDeProceso(int $procesoOrdenId): float
    {
        return (float) static::query()
            ->where(self::PROCESO_ORDEN_ID, $procesoOrdenId)
            ->sum(self::DEPRECIACION_TOTAL);
    }

    /**
     * Dias que duro el uso. Si sigue abierto se cuenta hasta ahora.
     */
    public function diasDeUso(): float
    {
        $inicio = $this->fecha_inicio;

        if ($inicio === null) {
            return 0.0;
        }

        $fin = $this->fecha_fin ?? Carbon::now();

        $dias = $inicio->diffInSeconds($fin) / 86400;

        return max(0.0, round($dias, 4));
    }

    /**
     * Calcula la depreciacion del periodo y la deja actualizada.
     *
     * Se guarda y no se calcula al vuelo a proposito: es la foto del
     * valor de este uso, y tiene que seguir siendo la misma aunque
     * despues se toque el valor o la vida util del equipo.
     */
    public function calcularDepreciacion(): self
    {
        $equipo = $this->equipo;

        $this->depreciacion_total = $equipo
            ? $equipo->depreciacionPorDias($this->diasDeUso())
            : 0.0;

        return $this;
    }

    /**
     * Comprueba que este periodo no se solape con otro del mismo equipo.
     *
     * Dos periodos se solapan cuando uno empieza antes de que el otro
     * termine. Un fecha_fin en NULL significa que el uso sigue abierto y
     * se solapa con todo lo que empiece despues.
     *
     * @throws ValidationException
     */
    public function validarSinSolapamiento(): void
    {
        if ($this->fecha_inicio === null) {
            return;
        }

        // Un periodo en NULL esta abierto: se compara contra el infinito
        $fin = $this->fecha_fin?->copy() ?? Carbon::now()->addYears(100);

        $solapados = static::query()
            ->where(self::EQUIPO_ID, $this->equipo_id)
            ->when(
                $this->exists,
                fn($q) => $q->where($this->getKeyName(), '!=', $this->getKey())
            )
            // El otro periodo empieza antes de que termine el mio
            ->where(self::FECHA_INICIO, '<', $fin)
            // y sigue vivo cuando yo empiezo
            ->where(function ($q) {
                $q->whereNull(self::FECHA_FIN)
                    ->orWhere(self::FECHA_FIN, '>', $this->fecha_inicio);
            })
            ->with('equipo', 'procesoOrden')
            ->get();

        if ($solapados->isEmpty()) {
            return;
        }

        $otro = $solapados->first();

        throw ValidationException::withMessages([
            self::FECHA_INICIO => sprintf(
                'El equipo %s ya está asignado al proceso %s entre %s y %s. '
                . 'Un equipo no puede trabajar en dos procesos a la vez.',
                $otro->equipo?->codigo ?? 'desconocido',
                $otro->procesoOrden?->codigo ?? 'desconocido',
                $otro->fecha_inicio?->format('d/m/Y H:i'),
                $otro->fecha_fin
                    ? $otro->fecha_fin->format('d/m/Y H:i')
                    : 'hoy (sigue asignado)'
            ),
        ]);
    }

    /**
     * Comprueba que el periodo tenga sentido antes de guardarlo.
     *
     * @throws ValidationException
     */
    public function validarPeriodo(): void
    {
        $errores = [];

        if ($this->fecha_inicio === null) {
            $errores[self::FECHA_INICIO] = 'Indica cuándo empieza el uso del equipo.';
        }

        if ($this->fecha_fin !== null && $this->fecha_fin->lessThanOrEqualTo($this->fecha_inicio)) {
            $errores[self::FECHA_FIN] = 'La fecha de fin debe ser posterior a la de inicio.';
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }
    }
}
