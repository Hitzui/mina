<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EquipoConsumo extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $casts = ['fecha' => 'date', 'cantidad' => 'float', 'costo_total_nio' => 'float'];

    public function insumo()
    {
        return $this->belongsTo(EquipoInsumo::class, 'equipo_insumo_id');
    }
}
