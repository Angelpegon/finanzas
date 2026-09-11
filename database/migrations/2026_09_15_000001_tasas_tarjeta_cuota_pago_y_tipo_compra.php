<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tarjetas_credito', function (Blueprint $table): void {
            $table->decimal('tasa_compras_mensual', 8, 4)->default(0)->after('ea_porcentaje');
            $table->decimal('tasa_avances_mensual', 8, 4)->default(0)->after('tasa_compras_mensual');
        });

        $tarjetas = DB::table('tarjetas_credito')->select('id', 'ea_porcentaje')->get();
        foreach ($tarjetas as $tarjeta) {
            $ea = (float) $tarjeta->ea_porcentaje;
            $mensual = $ea <= 0 ? 0.0 : ((pow(1 + ($ea / 100), 1 / 12) - 1) * 100);
            DB::table('tarjetas_credito')->where('id', $tarjeta->id)->update([
                'tasa_compras_mensual' => round($mensual, 4),
                'tasa_avances_mensual' => round($mensual, 4),
            ]);
        }

        Schema::table('compras_tarjeta', function (Blueprint $table): void {
            $table->string('tipo', 20)->default('compra')->after('tarjeta_credito_id');
            $table->foreignId('cuenta_liquida_id')
                ->nullable()
                ->after('categoria_id')
                ->constrained('cuentas_liquidas')
                ->nullOnDelete();
            $table->boolean('anulada')->default(false)->after('descripcion');
        });

        Schema::table('pagos', function (Blueprint $table): void {
            $table->foreignId('cuota_tarjeta_id')
                ->nullable()
                ->after('tarjeta_credito_id')
                ->constrained('cuotas_tarjeta')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cuota_tarjeta_id');
        });

        Schema::table('compras_tarjeta', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cuenta_liquida_id');
            $table->dropColumn(['tipo', 'anulada']);
        });

        Schema::table('tarjetas_credito', function (Blueprint $table): void {
            $table->dropColumn(['tasa_compras_mensual', 'tasa_avances_mensual']);
        });
    }
};
