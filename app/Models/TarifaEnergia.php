<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TarifaEnergia extends Model
{
    protected $table = 'tarifas_energia';

    protected $fillable = ['tarifa_kwh_nio'];

    protected $casts = ['tarifa_kwh_nio' => 'float'];

    public static function actual(): ?self
    {
        return static::query()->latest('id')->first();
    }
}
