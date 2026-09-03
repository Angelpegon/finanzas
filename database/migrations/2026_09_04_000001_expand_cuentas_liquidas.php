<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuentas_liquidas', function (Blueprint $table): void {
            $table->string('institucion')->nullable()->after('tipo');
            $table->string('numero_cuenta_enmascarado', 32)->nullable()->after('institucion');
            $table->bigInteger('saldo_inicial_centavos')->default(0)->after('numero_cuenta_enmascarado');
            $table->char('moneda', 3)->default('COP')->after('saldo_inicial_centavos');
            $table->string('estado', 20)->default('activa')->after('moneda');
        });
    }

    public function down(): void
    {
        Schema::table('cuentas_liquidas', function (Blueprint $table): void {
            $table->dropColumn([
                'institucion', 'numero_cuenta_enmascarado', 'saldo_inicial_centavos',
                'moneda', 'estado',
            ]);
        });
    }
};
