<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MariaDB antiguo falla con `ADD ... json` en el mismo ALTER que otras columnas.
 * Usamos longText: el cast `array` de Eloquent serializa JSON igual.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pagos', 'cuota_prestamo_id')) {
            Schema::table('pagos', function (Blueprint $table): void {
                $table->foreignId('cuota_prestamo_id')
                    ->nullable()
                    ->after('prestamo_id')
                    ->constrained('cuotas_prestamo')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('pagos', 'cronograma_snapshot')) {
            Schema::table('pagos', function (Blueprint $table): void {
                $table->longText('cronograma_snapshot')->nullable()->after('extraordinario');
            });
        }
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table): void {
            if (Schema::hasColumn('pagos', 'cuota_prestamo_id')) {
                $table->dropConstrainedForeignId('cuota_prestamo_id');
            }
            if (Schema::hasColumn('pagos', 'cronograma_snapshot')) {
                $table->dropColumn('cronograma_snapshot');
            }
        });
    }
};
