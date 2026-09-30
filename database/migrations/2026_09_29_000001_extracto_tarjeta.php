<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extracto por corte. El pago del usuario es contra el ciclo, no contra cada compra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tarjetas_credito', function (Blueprint $table): void {
            if (! Schema::hasColumn('tarjetas_credito', 'porcentaje_abono_capital_minimo')) {
                $table->decimal('porcentaje_abono_capital_minimo', 8, 4)->default(5);
            }
            if (! Schema::hasColumn('tarjetas_credito', 'cuota_manejo_centavos')) {
                $table->unsignedBigInteger('cuota_manejo_centavos')->default(0);
            }
            if (! Schema::hasColumn('tarjetas_credito', 'tasa_mora_mensual')) {
                $table->decimal('tasa_mora_mensual', 8, 4)->default(0);
            }
        });

        Schema::table('ciclos_facturacion', function (Blueprint $table): void {
            if (! Schema::hasColumn('ciclos_facturacion', 'estado')) {
                $table->string('estado', 20)->default('abierto');
            }
            if (! Schema::hasColumn('ciclos_facturacion', 'pago_total_centavos')) {
                $table->bigInteger('pago_total_centavos')->default(0);
            }
            if (! Schema::hasColumn('ciclos_facturacion', 'pagado_centavos')) {
                $table->bigInteger('pagado_centavos')->default(0);
            }
            if (! Schema::hasColumn('ciclos_facturacion', 'capital_rotativo_centavos')) {
                $table->bigInteger('capital_rotativo_centavos')->default(0);
            }
            if (! Schema::hasColumn('ciclos_facturacion', 'capital_diferido_centavos')) {
                $table->bigInteger('capital_diferido_centavos')->default(0);
            }
            if (! Schema::hasColumn('ciclos_facturacion', 'interes_rotativo_centavos')) {
                $table->bigInteger('interes_rotativo_centavos')->default(0);
            }
            if (! Schema::hasColumn('ciclos_facturacion', 'interes_diferido_centavos')) {
                $table->bigInteger('interes_diferido_centavos')->default(0);
            }
            if (! Schema::hasColumn('ciclos_facturacion', 'interes_mora_centavos')) {
                $table->bigInteger('interes_mora_centavos')->default(0);
            }
            if (! Schema::hasColumn('ciclos_facturacion', 'cargos_centavos')) {
                $table->bigInteger('cargos_centavos')->default(0);
            }
            if (! Schema::hasColumn('ciclos_facturacion', 'causado_centavos')) {
                $table->bigInteger('causado_centavos')->default(0);
            }
        });

        Schema::table('cuotas_tarjeta', function (Blueprint $table): void {
            if (! Schema::hasColumn('cuotas_tarjeta', 'abonado_centavos')) {
                $table->bigInteger('abonado_centavos')->default(0);
            }
            if (! Schema::hasColumn('cuotas_tarjeta', 'ciclo_facturacion_id')) {
                $table->foreignId('ciclo_facturacion_id')
                    ->nullable()
                    ->constrained('ciclos_facturacion')
                    ->nullOnDelete();
            }
        });

        Schema::table('pagos', function (Blueprint $table): void {
            if (! Schema::hasColumn('pagos', 'ciclo_facturacion_id')) {
                $table->foreignId('ciclo_facturacion_id')
                    ->nullable()
                    ->after('tarjeta_credito_id')
                    ->constrained('ciclos_facturacion')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table): void {
            if (Schema::hasColumn('pagos', 'ciclo_facturacion_id')) {
                $table->dropConstrainedForeignId('ciclo_facturacion_id');
            }
        });
        Schema::table('cuotas_tarjeta', function (Blueprint $table): void {
            if (Schema::hasColumn('cuotas_tarjeta', 'ciclo_facturacion_id')) {
                $table->dropConstrainedForeignId('ciclo_facturacion_id');
            }
            if (Schema::hasColumn('cuotas_tarjeta', 'abonado_centavos')) {
                $table->dropColumn('abonado_centavos');
            }
        });
        Schema::table('ciclos_facturacion', function (Blueprint $table): void {
            $cols = [
                'estado', 'pago_total_centavos', 'pagado_centavos',
                'capital_rotativo_centavos', 'capital_diferido_centavos',
                'interes_rotativo_centavos', 'interes_diferido_centavos',
                'interes_mora_centavos', 'cargos_centavos', 'causado_centavos',
            ];
            $presentes = array_values(array_filter($cols, fn (string $c) => Schema::hasColumn('ciclos_facturacion', $c)));
            if ($presentes !== []) {
                $table->dropColumn($presentes);
            }
        });
        Schema::table('tarjetas_credito', function (Blueprint $table): void {
            $cols = ['porcentaje_abono_capital_minimo', 'cuota_manejo_centavos', 'tasa_mora_mensual'];
            $presentes = array_values(array_filter($cols, fn (string $c) => Schema::hasColumn('tarjetas_credito', $c)));
            if ($presentes !== []) {
                $table->dropColumn($presentes);
            }
        });
    }
};
