<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un equipo que se usa en los procesos y que se deprecia con el tiempo.
 *
 * La depreciacion se lleva a valor - residual repartido en los meses de
 * vida util, prorrateados a dias. El resultado se guarda en cada
 * asignacion (ProcesoEquipo) como foto historica: si manana se corrige la
 * vida util de este equipo, el costo de un proceso ya registrado no debe
 * cambiar.
 */
class Equipo extends Model
{
    use SoftDeletes;

    public const ID = 'id';
    public const CODIGO = 'codigo';
    public const NOMBRE = 'nombre';
    public const DESCRIPCION = 'descripcion';
    public const FECHA_ADQUISICION = 'fecha_adquisicion';
    public const VALOR_ADQUISICION = 'valor_adquisicion';
    public const VALOR_RESIDUAL = 'valor_residual';
    public const VIDA_UTIL_MESES = 'vida_util_meses';
    public const DEPRECIACION_ACUMULADA = 'depreciacion_acumulada';
    public const ESTADO = 'estado';

    /**
     * Dias que se toman por mes de vida util.
     *
     * El ano tiene 365 y da 365/12 = 30.4167 dias por mes. Se usa 30
     * porque es lo que se viene aplicando en el resto del sistema y
     * cambiarlo agora moveria los costos ya calculados.
     */
    public const DIAS_POR_MES = 30;

    protected $table = 'equipos';

    protected $fillable = [
        self::CODIGO,
        self::NOMBRE,
        self::DESCRIPCION,
        self::FECHA_ADQUISICION,
        self::VALOR_ADQUISICION,
        self::VALOR_RESIDUAL,
        self::VIDA_UTIL_MESES,
        self::ESTADO,
    ];

    protected $casts = [
        self::ID => 'int',
        self::FECHA_ADQUISICION => 'date',
        self::VALOR_ADQUISICION => 'float',
        self::VALOR_RESIDUAL => 'float',
        self::VIDA_UTIL_MESES => 'int',
        self::DEPRECIACION_ACUMULADA => 'float',
        self::ESTADO => 'int',
    ];

    public function asignaciones()
    {
        return $this->hasMany(ProcesoEquipo::class, ProcesoEquipo::EQUIPO_ID);
    }

    /**
     * Cuanto se ha depreciado hasta hoy, sumando los usos registrados.
     *
     * Se calcula y no se lee de la columna: la columna queda como
     * referencia de la base, pero un total que hay que acordarse de
     * actualizar a mano se desfasca en cuanto alguien registra un uso
     * desde otra pantalla. Aqui sale de los propios usos, que son la
     * fuente, y por eso no puede quedar viejo.
     */
    public function getDepreciacionAcumuladaAttribute(): float
    {
        // Con la relacion cargada no hace falta consultar otra vez
        if ($this->relationLoaded('asignaciones')) {
            return (float) $this->asignaciones->sum(
                fn($asignacion) => (float) $asignacion->depreciacion_total
            );
        }

        return (float) $this->asignaciones()->sum(
            ProcesoEquipo::DEPRECIACION_TOTAL
        );
    }

    /**
     * Cuanto le queda por depreciar: el valor de adquisicion menos el
     * valor residual y lo ya consumido.
     */
    public function valorPorDepreciar(): float
    {
        return max(
            0.0,
            (float) $this->valor_adquisicion
                - (float) $this->valor_residual
                - (float) $this->depreciacion_acumulada
        );
    }

    /**
     * Cuanto se deprecia por dia de uso.
     *
     * Un equipo sin vida util no deprecia nada en vez de dividir entre
     * cero, que en PHP daria laDivision por cero.
     */
    public function depreciacionDiaria(): float
    {
        $meses = (int) $this->vida_util_meses;

        if ($meses <= 0) {
            return 0.0;
        }

        $total = (float) $this->valor_adquisicion - (float) $this->valor_residual;

        if ($total <= 0) {
            return 0.0;
        }

        return $total / ($meses * self::DIAS_POR_MES);
    }

    /**
     * Depreciacion de un numero de dias, sin pasar de lo que queda por
     * depreciar. Asi el total de todos los periodos no puede superar el
     * valor del equipo.
     */
    public function depreciacionPorDias(float $dias): float
    {
        if ($dias <= 0) {
            return 0.0;
        }

        return min(
            round($this->depreciacionDiaria() * $dias, 2),
            round($this->valorPorDepreciar(), 2)
        );
    }
}
