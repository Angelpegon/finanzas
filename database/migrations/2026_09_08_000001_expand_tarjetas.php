<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras_tarjeta', function (Blueprint $table): void {
            $table->decimal('tasa_interes_porcentaje', 8, 4)->default(0)->after('cuotas');
        });
    }

    public function down(): void
    {
        Schema::table('compras_tarjeta', fn (Blueprint $table) => $table->dropColumn('tasa_interes_porcentaje'));
    }
};
