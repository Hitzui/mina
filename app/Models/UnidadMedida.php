<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnidadMedida extends Model
{
    protected $table = 'unidades_medida';

    protected $fillable = ['nombre', 'estado'];

    protected $casts = ['estado' => 'boolean'];

    public function productos()
    {
        return $this->hasMany(Producto::class, 'unidad_medida_id');
    }

    public function puedeEliminarse(): bool
    {
        foreach ([
            ['productos', 'unidad_medida_id'],
            ['ordenes_trabajo', 'unidad_peso_id'],
            ['cotizaciones', 'unidad_peso_id'],
            ['producciones', 'unidad_medida_id'],
            ['ingresos', 'unidad_medida_id'],
            ['precios_oro', 'unidad_id'],
            ['trabajos_empleados', 'unidad_id'],
        ] as [$tabla, $campo]) {
            if (\DB::table($tabla)->where($campo, $this->id)->exists()) {
                return false;
            }
        }

        return true;
    }
}
