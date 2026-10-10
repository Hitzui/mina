<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoCaja extends Model
{
    public const ENTRADA = 'entrada';
    public const SALIDA = 'salida';

    public const ORIGEN_COBRO = 'cobro';
    public const ORIGEN_MANUAL = 'manual';
    public const ORIGEN_SALDO_INICIAL = 'saldo_inicial';
    public const ORIGEN_TRANSFERENCIA = 'transferencia';
    public const ORIGEN_REVERSA = 'reversa';

    protected $table = 'movimientos_caja';

    protected $fillable = [
        'codigo', 'caja_id', 'fecha', 'tipo', 'origen', 'metodo_pago_id',
        'moneda_id', 'monto', 'descripcion', 'referencia', 'cobro_id',
        'transferencia_id', 'reversa_de_id', 'registrado_por',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'decimal:2',
    ];

    public function caja()
    {
        return $this->belongsTo(Cajas::class, 'caja_id');
    }

    public function metodoPago()
    {
        return $this->belongsTo(MetodosPagos::class, 'metodo_pago_id');
    }

    public function moneda()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function cobro()
    {
        return $this->belongsTo(Cobro::class, 'cobro_id');
    }

    public function registradoPor()
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function reversaDe()
    {
        return $this->belongsTo(self::class, 'reversa_de_id');
    }

    public function transferencia()
    {
        return $this->belongsTo(self::class, 'transferencia_id');
    }

    public function scopeEfectivo($query)
    {
        return $query->select('movimientos_caja.*')
            ->whereNotExists(function ($subquery) {
                $subquery->selectRaw('1')
                    ->from('movimientos_caja as reversas')
                    ->whereColumn('reversas.reversa_de_id', 'movimientos_caja.id');
            });
    }
}
