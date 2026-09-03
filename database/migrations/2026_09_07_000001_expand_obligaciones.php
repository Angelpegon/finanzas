<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prestamos', function (Blueprint $table): void {
            $table->string('entidad')->nullable()->after('nombre');
            $table->string('tipo_obligacion', 30)->default('prestamo_bancario')->after('entidad');
            $table->string('tipo_tasa', 20)->default('ea')->after('ea_porcentaje');
            $table->date('fecha_vencimiento')->nullable()->after('fecha_desembolso');
            $table->string('periodicidad', 20)->default('mensual')->after('dia_pago');
            $table->string('metodo_amortizacion', 20)->default('frances')->after('periodicidad');
            $table->bigInteger('seguro_centavos')->default(0)->after('cuota_centavos');
            $table->bigInteger('otros_cargos_centavos')->default(0)->after('seguro_centavos');
            $table->unsignedSmallInteger('cuotas_pagadas')->default(0)->after('cuota_centavos');
        });
        Schema::table('cuotas_prestamo', function (Blueprint $table): void {
            $table->bigInteger('seguro_centavos')->default(0)->after('interes_centavos');
            $table->bigInteger('otros_cargos_centavos')->default(0)->after('seguro_centavos');
            $table->bigInteger('total_centavos')->default(0)->after('otros_cargos_centavos');
        });

        Schema::table('tarjetas_credito', function (Blueprint $table): void {
            $table->string('entidad')->nullable()->after('nombre');
            $table->string('tipo_obligacion', 30)->default('tarjeta_credito')->after('entidad');
            $table->string('tipo_tasa', 20)->default('ea')->after('ea_porcentaje');
            $table->date('fecha_inicio')->nullable()->after('dia_pago');
            $table->date('fecha_vencimiento')->nullable()->after('fecha_inicio');
            $table->string('periodicidad', 20)->default('mensual')->after('fecha_vencimiento');
            $table->unsignedSmallInteger('cuotas')->nullable()->after('periodicidad');
            $table->unsignedSmallInteger('cuotas_pagadas')->default(0)->after('cuotas');
            $table->string('estado', 20)->default('activa')->after('cuotas_pagadas');
        });
    }

    public function down(): void
    {
        Schema::table('tarjetas_credito', function (Blueprint $table): void {
            $table->dropColumn(['entidad', 'tipo_obligacion', 'tipo_tasa', 'fecha_inicio', 'fecha_vencimiento', 'periodicidad', 'cuotas', 'cuotas_pagadas', 'estado']);
        });
        Schema::table('prestamos', function (Blueprint $table): void {
            $table->dropColumn(['entidad', 'tipo_obligacion', 'tipo_tasa', 'fecha_vencimiento', 'periodicidad', 'metodo_amortizacion', 'seguro_centavos', 'otros_cargos_centavos', 'cuotas_pagadas', 'estado']);
        });
        Schema::table('cuotas_prestamo', fn (Blueprint $table) => $table->dropColumn(['seguro_centavos', 'otros_cargos_centavos', 'total_centavos']));
    }
};
