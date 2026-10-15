<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipoRecurso extends Model
{
    public $timestamps = false;
    protected $fillable = ['producto_id', 'cantidad_por_hora'];
    protected $casts = ['cantidad_por_hora' => 'float'];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}
