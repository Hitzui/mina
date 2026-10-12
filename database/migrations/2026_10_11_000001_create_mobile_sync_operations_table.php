<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_sync_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('operation_key', 150);
            $table->string('operation_type', 50);
            $table->json('result');
            $table->timestamps();
            $table->unique(['user_id', 'operation_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_sync_operations');
    }
};
