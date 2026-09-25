<?php

use App\Models\TiposPagoEmpleado;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Configura el método de cálculo de los tipos de pago existentes.
 *
 * Antes de este cambio todos los registros quedaron con el valor por
 * defecto CANTIDAD_X_TARIFA, que multiplica cantidad × tarifa. Eso
 * calcula mal los pagos "por trabajo": si un empleado cobra C$700 por
 * un trabajo y se registran 8 horas, el sistema cobraba C$5,600.
 *
 * La regla se decide por el código del tipo de pago, no por el nombre,
 * para que el CRUD permita crear métodos nuevos sin tocar PHP.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         | Por código, no por id: los ids dependen del orden de captura
         | y pueden variar entre instalaciones.
         */
        $porTarifa = ['FIJO', 'TRABAJO'];

        DB::table('tipos_pago_empleado')
            ->whereIn('codigo', $porTarifa)
            ->update(['metodo_calculo' => TiposPagoEmpleado::METODO_TARIFA]);

        DB::table('tipos_pago_empleado')
            ->whereNotIn('codigo', $porTarifa)
            ->update(['metodo_calculo' => TiposPagoEmpleado::METODO_CANTIDAD_X_TARIFA]);
    }

    public function down(): void
    {
        DB::table('tipos_pago_empleado')
            ->update(['metodo_calculo' => TiposPagoEmpleado::METODO_CANTIDAD_X_TARIFA]);
    }
};
