<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `tarjetas_credito.cuotas` (scalar) sombreaba la relación HasMany.
 * Con NULL, `$tarjeta->cuotas->…` explotaba en 500. El conteo real vive en compras/cuotas_tarjeta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tarjetas_credito', 'cuotas')) {
            Schema::table('tarjetas_credito', function (Blueprint $table): void {
                $table->dropColumn('cuotas');
            });
        }

        if (Schema::hasColumn('tarjetas_credito', 'cuotas_pagadas')) {
            Schema::table('tarjetas_credito', function (Blueprint $table): void {
                $table->dropColumn('cuotas_pagadas');
            });
        }
    }

    public function down(): void
    {
        Schema::table('tarjetas_credito', function (Blueprint $table): void {
            if (! Schema::hasColumn('tarjetas_credito', 'cuotas')) {
                $table->unsignedSmallInteger('cuotas')->nullable();
            }
            if (! Schema::hasColumn('tarjetas_credito', 'cuotas_pagadas')) {
                $table->unsignedSmallInteger('cuotas_pagadas')->default(0);
            }
        });
    }
};
