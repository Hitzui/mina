<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cotizacion extends Model
{
    use SoftDeletes;

    public const ESTADO_BORRADOR = 'borrador';

    public const ESTADO_ENVIADA = 'enviada';

    public const ESTADO_ACEPTADA = 'aceptada';

    public const ESTADO_RECHAZADA = 'rechazada';

    public const ESTADO_VENCIDA = 'vencida';

    public const ESTADOS = [
        self::ESTADO_BORRADOR => 'Borrador',
        self::ESTADO_ENVIADA => 'Enviada',
        self::ESTADO_ACEPTADA => 'Aceptada',
        self::ESTADO_RECHAZADA => 'Rechazada',
        self::ESTADO_VENCIDA => 'Vencida',
    ];

    protected $table = 'cotizaciones';

    protected $fillable = [
        'codigo',
        'cliente_id',
        'fecha',
        'fecha_vencimiento',
        'descripcion',
        'peso_mineral',
        'unidad_peso',
        'unidad_peso_id',
        'tipo_participacion_id',
        'porcentaje_cliente',
        'porcentaje_empresa',
        'tarifa_servicio',
        'moneda_id',
        'observaciones',
        'estado',
        'orden_trabajo_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'fecha_vencimiento' => 'date',
        'peso_mineral' => 'decimal:3',
        'porcentaje_cliente' => 'decimal:4',
        'porcentaje_empresa' => 'decimal:4',
        'tarifa_servicio' => 'decimal:2',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class)->withTrashed();
    }

    public function unidadMedida()
    {
        return $this->belongsTo(UnidadMedida::class, 'unidad_peso_id');
    }

    public function tipoParticipacion()
    {
        return $this->belongsTo(TiposParticipacion::class, 'tipo_participacion_id')->withTrashed();
    }

    public function moneda()
    {
        return $this->belongsTo(Moneda::class)->withTrashed();
    }

    public function ordenTrabajo()
    {
        return $this->belongsTo(OrdenesTrabajo::class, 'orden_trabajo_id');
    }

    public function estaVencida(): bool
    {
        return in_array($this->estado, [self::ESTADO_BORRADOR, self::ESTADO_ENVIADA], true)
            && $this->fecha_vencimiento->isBefore(today());
    }

    public function estadoActual(): string
    {
        return $this->estaVencida() ? self::ESTADO_VENCIDA : $this->estado;
    }

    public function estadoEtiqueta(): string
    {
        $estado = $this->estadoActual();
        $clase = match ($estado) {
            self::ESTADO_ACEPTADA => 'bg-success',
            self::ESTADO_RECHAZADA, self::ESTADO_VENCIDA => 'bg-danger',
            self::ESTADO_ENVIADA => 'bg-info',
            default => 'bg-secondary',
        };

        return sprintf('<span class="badge %s">%s</span>', $clase, self::ESTADOS[$estado] ?? 'Desconocido');
    }

    public function modalidad(): string
    {
        return $this->tarifa_servicio !== null ? 'tarifa' : 'porcentajes';
    }
}
