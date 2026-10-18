<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

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

    protected static function booted(): void
    {
        static::deleting(function ($uso) {
            if ($uso->consumos()->withTrashed()->exists() || $uso->entregas()->exists() || $uso->reparaciones()->withTrashed()->exists()) {
                throw ValidationException::withMessages(['equipo_id' => 'El uso tiene historial de insumos o reparaciones y debe conservarse.']);
            }
        });
    }

    public const ID = 'id';
    public const PROCESO_ORDEN_ID = 'proceso_orden_id';
    public const EQUIPO_ID = 'equipo_id';
    public const FECHA_INICIO = 'fecha_inicio';
    public const FECHA_FIN = 'fecha_fin';
    public const DEPRECIACION_TOTAL = 'depreciacion_total';
    public const METODO_COSTO = 'metodo_costo';
    public const POTENCIA_KW_USADA = 'potencia_kw_usada';
    public const TARIFA_KWH_NIO = 'tarifa_kwh_nio';
    public const ENERGIA_KWH = 'energia_kwh';
    public const COSTO_ENERGIA_NIO = 'costo_energia_nio';
    public const COSTO_TOTAL_NIO = 'costo_total_nio';
    public const OBSERVACIONES = 'observaciones';
    public const EXPRESION_COSTO = "proceso_equipos.costo_total_nio + COALESCE((SELECT SUM(ec.costo_total_nio) FROM equipo_consumos ec WHERE ec.proceso_equipo_id = proceso_equipos.id AND ec.tipo = 'consumo' AND ec.deleted_at IS NULL), 0)";

    protected $table = 'proceso_equipos';

    protected $fillable = [
        self::PROCESO_ORDEN_ID,
        self::EQUIPO_ID,
        self::FECHA_INICIO,
        self::FECHA_FIN,
        self::METODO_COSTO,
        self::OBSERVACIONES,
        'horas_efectivas', 'horometro_inicial', 'horometro_final', 'consumos_confirmados',
    ];

    protected $casts = [
        self::ID => 'int',
        self::PROCESO_ORDEN_ID => 'int',
        self::EQUIPO_ID => 'int',
        self::FECHA_INICIO => 'datetime',
        self::FECHA_FIN => 'datetime',
        self::DEPRECIACION_TOTAL => 'float',
        self::POTENCIA_KW_USADA => 'float',
        self::TARIFA_KWH_NIO => 'float',
        self::ENERGIA_KWH => 'float',
        self::COSTO_ENERGIA_NIO => 'float',
        self::COSTO_TOTAL_NIO => 'float',
        'horas_efectivas' => 'float',
        'horometro_inicial' => 'float',
        'horometro_final' => 'float',
        'depreciacion_hora_nio' => 'float',
        'depreciacion_aplicada' => 'boolean',
        'consumos_confirmados' => 'boolean',
        'recursos_estimados' => 'array',
    ];

    public function procesoOrden()
    {
        return $this->belongsTo(ProcesosOrden::class, self::PROCESO_ORDEN_ID);
    }

    public function equipo()
    {
        return $this->belongsTo(Equipo::class, self::EQUIPO_ID);
    }

    public function consumos()
    {
        return $this->hasMany(EquipoConsumo::class);
    }

    public function reparaciones()
    {
        return $this->hasMany(EquipoReparacion::class, 'proceso_equipo_id');
    }

    public function entregas()
    {
        return $this->hasMany(EquipoInsumo::class);
    }

    public function getCostoInsumosNioAttribute(): float
    {
        if ($this->relationLoaded('consumos')) {
            return (float) $this->consumos->where('tipo', 'consumo')->sum('costo_total_nio');
        }

        return (float) $this->consumos()->where('tipo', 'consumo')->sum('costo_total_nio');
    }

    public function getCostoUsoNioAttribute(): float
    {
        return round((float) $this->costo_total_nio + $this->costo_insumos_nio, 2);
    }

    public function getCostoEstimadoNioAttribute(): float
    {
        $estimado = collect($this->recursos_estimados ?? [])->sum(fn ($r) =>
            (float) $r['cantidad_por_hora'] * (float) ($this->horas_efectivas ?? 0) * (float) $r['costo_unitario_nio']
        );

        return round((float) $this->costo_total_nio + $estimado, 2);
    }

    public function estadoCosteo(): string
    {
        if ($this->depreciacion_aplicada === null) return 'Histórico por periodo asignado';
        if ($this->horas_efectivas === null) return 'Pendiente de horas efectivas';
        if (($this->recursos_estimados ?? []) !== [] && !$this->consumos_confirmados) return 'Consumos por confirmar';
        return $this->potencia_kw_usada > 0 ? 'Electricidad estimada por horas efectivas' : 'Costos registrados';
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

    public static function costoDeEquiposDeProceso(int $procesoOrdenId): float
    {
        $base = (float) static::query()
            ->where(self::PROCESO_ORDEN_ID, $procesoOrdenId)
            ->sum(self::COSTO_TOTAL_NIO);
        $insumos = EquipoConsumo::query()->where('tipo', 'consumo')
            ->whereIn('proceso_equipo_id', static::query()->where(self::PROCESO_ORDEN_ID, $procesoOrdenId)->select('id'))
            ->sum('costo_total_nio');

        return round($base + (float) $insumos, 2);
    }

    /** Agregados SQL sin subconsultas correlacionadas dentro de GROUP BY. */
    public static function consultaParaCostos(): \Illuminate\Database\Query\Builder
    {
        $consumos = EquipoConsumo::query()->where('tipo', 'consumo')
            ->select('proceso_equipo_id')->selectRaw('SUM(costo_total_nio) AS total_consumos')
            ->groupBy('proceso_equipo_id');
        $usos = DB::table('proceso_equipos')
            ->leftJoinSub($consumos, 'consumos_equipo', 'consumos_equipo.proceso_equipo_id', '=', 'proceso_equipos.id')
            ->whereNull('proceso_equipos.deleted_at')
            ->select('proceso_equipos.*')
            ->selectRaw('proceso_equipos.costo_total_nio + COALESCE(consumos_equipo.total_consumos, 0) AS costo_uso_nio');

        return DB::query()->fromSub($usos, 'costos_equipos');
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

    public function horasDeUso(): float
    {
        if ($this->fecha_inicio === null) {
            return 0.0;
        }

        $fin = $this->fecha_fin ?? Carbon::now();

        return max(0.0, $this->fecha_inicio->diffInSeconds($fin) / 3600);
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

    /** Calcula la depreciación y/o energía como valores históricos del uso. */
    public function calcularCostos(?float $tarifaKwhNio): self
    {
        $equipo = $this->equipo;
        if ($this->depreciacion_aplicada === null || $this->isDirty('equipo_id')) {
            $this->depreciacion_aplicada = $equipo?->usaDepreciacion() ?? false;
            $this->metodo_costo = $equipo?->metodo_costo ?? 'depreciacion';
            $this->depreciacion_hora_nio = ($equipo?->depreciacionDiaria() ?? 0) / 24;
            $this->potencia_kw_usada = $equipo?->usaEnergia() ? (float) $equipo->consumo_kw : null;
            $this->tarifa_kwh_nio = $this->potencia_kw_usada > 0 ? $tarifaKwhNio : null;
            $this->recursos_estimados = $equipo?->recursosParaEstimar() ?? [];
        }

        $horas = (float) ($this->horas_efectivas ?? 0);
        $anterior = $this->exists && !$this->isDirty('equipo_id') ? (float) $this->getRawOriginal('depreciacion_total') : 0;
        $disponible = ($equipo?->valorPorDepreciar() ?? 0) + $anterior;
        $this->depreciacion_total = $this->depreciacion_aplicada
            ? min(round((float) $this->depreciacion_hora_nio * $horas, 2), round($disponible, 2)) : 0;
        $this->energia_kwh = round((float) $this->potencia_kw_usada * $horas, 4);
        $this->costo_energia_nio = round($this->energia_kwh * (float) $this->tarifa_kwh_nio, 2);
        $this->costo_total_nio = round(
            (float) $this->depreciacion_total + (float) $this->costo_energia_nio,
            2
        );

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

        if (($this->horometro_inicial === null) !== ($this->horometro_final === null)) {
            $errores['horometro_final'] = 'Indique las dos lecturas del horómetro.';
        } elseif ($this->horometro_inicial !== null) {
            $diferencia = round($this->horometro_final - $this->horometro_inicial, 4);
            if ($diferencia < 0) {
                $errores['horometro_final'] = 'El horómetro final no puede ser menor al inicial.';
            } elseif ($this->horas_efectivas !== null && abs($this->horas_efectivas - $diferencia) > 0.0001) {
                $errores['horas_efectivas'] = 'Las horas deben coincidir con la diferencia del horómetro.';
            } else {
                $this->horas_efectivas = $diferencia;
            }
        }
        if ($this->horas_efectivas !== null && ($this->horas_efectivas < 0 ||
            $this->horas_efectivas > $this->horasDeUso() + 0.0001)) {
            $errores['horas_efectivas'] = 'Las horas efectivas deben ser positivas y caber dentro del periodo de asignación.';
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }
    }
}
