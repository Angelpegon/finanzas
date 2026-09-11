<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table): void {
            $table->foreignId('cuota_prestamo_id')
                ->nullable()
                ->after('prestamo_id')
                ->constrained('cuotas_prestamo')
                ->nullOnDelete();
            $table->json('cronograma_snapshot')->nullable()->after('extraordinario');
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cuota_prestamo_id');
            $table->dropColumn('cronograma_snapshot');
        });
    }
};
