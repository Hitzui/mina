<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CierreCaja extends Model
{
    use SoftDeletes;

    protected $table = 'cierres_caja';

    protected $fillable = [
        'codigo', 'caja_id', 'fecha_desde', 'fecha_hasta', 'observaciones',
    ];

    protected $casts = [
        'fecha_desde' => 'date',
        'fecha_hasta' => 'date',
    ];

    public function caja()
    {
        return $this->belongsTo(Cajas::class, 'caja_id');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleCierreCaja::class, 'cierre_caja_id');
    }
}
