<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EquipoReparacion extends Model
{
    use SoftDeletes;

    protected $table = 'equipo_reparaciones';
    protected $fillable = ['proceso_equipo_id', 'inicio', 'fin', 'motivo', 'modalidad', 'trabajo_realizado', 'created_by'];
    protected $casts = ['inicio' => 'datetime', 'fin' => 'datetime', 'version' => 'integer'];

    public function asignacion() { return $this->belongsTo(ProcesoEquipo::class, 'proceso_equipo_id'); }
    public function costos() { return $this->hasMany(MovimientosCosto::class, 'reparacion_id'); }
}
