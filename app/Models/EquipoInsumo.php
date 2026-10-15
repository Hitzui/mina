<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipoInsumo extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['fecha' => 'date', 'cantidad' => 'float', 'disponible' => 'float', 'costo_unitario_nio' => 'float'];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}
