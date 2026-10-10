<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleCierreCaja extends Model
{
    protected $table = 'detalle_cierres_caja';

    protected $fillable = [
        'metodo_pago_id', 'moneda_id', 'monto_esperado', 'monto_contado', 'diferencia',
    ];

    public function metodoPago()
    {
        return $this->belongsTo(MetodosPagos::class, 'metodo_pago_id');
    }

    public function moneda()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }
}
