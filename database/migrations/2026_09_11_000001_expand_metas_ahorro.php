<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metas_ahorro', function (Blueprint $table): void {
            $table->bigInteger('monto_actual_centavos')->default(0)->after('objetivo_centavos');
            $table->string('prioridad', 20)->default('media')->after('aporte_mensual_centavos');
            $table->string('estado', 20)->default('activa')->after('prioridad');
        });
    }

    public function down(): void
    {
        Schema::table('metas_ahorro', fn (Blueprint $table) => $table->dropColumn(['monto_actual_centavos', 'prioridad', 'estado']));
    }
};
