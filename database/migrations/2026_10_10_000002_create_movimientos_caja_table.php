<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_caja', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->foreignId('caja_id')->constrained('cajas')->restrictOnDelete();
            $table->date('fecha');
            $table->string('tipo', 30);
            $table->string('origen', 30);
            $table->foreignId('metodo_pago_id')->constrained('metodos_pago')->restrictOnDelete();
            $table->foreignId('moneda_id')->constrained('monedas')->restrictOnDelete();
            $table->decimal('monto', 14, 2);
            $table->string('descripcion', 255);
            $table->string('referencia', 100)->nullable();
            $table->foreignId('cobro_id')->nullable()->constrained('cobros')->nullOnDelete();
            $table->foreignId('transferencia_id')->nullable()->constrained('movimientos_caja')->restrictOnDelete();
            $table->foreignId('reversa_de_id')->nullable()->constrained('movimientos_caja')->restrictOnDelete();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('reversa_de_id', 'movimiento_caja_reversa_uq');
            $table->index(['caja_id', 'fecha', 'moneda_id', 'metodo_pago_id'], 'movimiento_caja_periodo_idx');
            $table->index(['origen', 'cobro_id'], 'movimiento_caja_cobro_idx');
        });

        // Los cobros finalizados existentes pasan al libro para no perder su saldo al
        // cambiar el cálculo de los cierres. Un registro por cobro evita duplicar líneas.
        $cobros = DB::table('cobros')
            ->join('detalle_cobros', function ($join) {
                $join->on('detalle_cobros.cobro_id', '=', 'cobros.id')
                    ->whereNull('detalle_cobros.deleted_at');
            })
            ->whereNull('cobros.deleted_at')
            ->where('cobros.estado', 3)
            ->groupBy(
                'cobros.id', 'cobros.codigo', 'cobros.caja_id', 'cobros.fecha',
                'cobros.metodo_pago_id', 'cobros.moneda_id'
            )
            ->selectRaw('cobros.id, cobros.codigo, cobros.caja_id, cobros.fecha, cobros.metodo_pago_id, cobros.moneda_id, ROUND(SUM(detalle_cobros.monto), 2) AS monto')
            ->get();

        foreach ($cobros as $cobro) {
            DB::table('movimientos_caja')->insert([
                'codigo' => 'MOV-COB-' . $cobro->id,
                'caja_id' => $cobro->caja_id,
                'fecha' => $cobro->fecha,
                'tipo' => 'entrada',
                'origen' => 'cobro',
                'metodo_pago_id' => $cobro->metodo_pago_id,
                'moneda_id' => $cobro->moneda_id,
                'monto' => $cobro->monto,
                'descripcion' => 'Cobro ' . $cobro->codigo,
                'referencia' => $cobro->codigo,
                'cobro_id' => $cobro->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_caja');
    }
};
