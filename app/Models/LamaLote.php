<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class LamaLote extends Model
{
    use SoftDeletes;

    protected $fillable = ['orden_origen_id', 'acuerdo_id', 'fecha', 'cantidad_estimada', 'unidad_id', 'unidad', 'ubicacion', 'destino', 'porcentaje_empresa', 'observaciones'];
    protected $casts = ['fecha' => 'date', 'cantidad_estimada' => 'float', 'porcentaje_empresa' => 'float'];

    protected static function booted(): void
    {
        static::updating(function (self $lote) {
            if ($lote->tratamiento()->withTrashed()->exists()) {
                throw ValidationException::withMessages(['lote' => 'El lote ya tiene un tratamiento. Su cantidad y condiciones de origen deben conservarse.']);
            }
        });
        static::deleting(function (self $lote) {
            if ($lote->tratamiento()->withTrashed()->exists()) {
                throw ValidationException::withMessages(['lote' => 'El lote tiene historial de tratamiento y no puede eliminarse.']);
            }
        });
    }

    public function origen() { return $this->belongsTo(OrdenesTrabajo::class, 'orden_origen_id'); }
    public function acuerdo() { return $this->belongsTo(AcuerdosOrden::class); }
    public function tratamiento() { return $this->hasOne(OrdenesTrabajo::class, 'lama_lote_id'); }
    public function getCodigoAttribute(): string { return 'LAMA-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT); }
}
