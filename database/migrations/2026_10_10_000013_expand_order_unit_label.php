<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->string('unidad_peso', 40)->default('kg')->change();
        });

        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->string('unidad_peso', 40)->change();
        });
    }

    public function down(): void
    {
        if (DB::table('ordenes_trabajo')->whereRaw('CHAR_LENGTH(unidad_peso) > 10')->exists()
            || DB::table('cotizaciones')->whereRaw('CHAR_LENGTH(unidad_peso) > 10')->exists()) {
            throw new RuntimeException(
                'No se puede revertir: hay etiquetas de unidad de más de 10 caracteres que se truncarían.'
            );
        }

        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->string('unidad_peso', 10)->change();
        });

        Schema::table('ordenes_trabajo', function (Blueprint $table) {
            $table->string('unidad_peso', 10)->default('kg')->change();
        });
    }
};
