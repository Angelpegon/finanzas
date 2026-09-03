<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table): void {
            $table->foreignId('categoria_id')->nullable()->after('cuenta_liquida_id')->constrained('categorias')->nullOnDelete();
            $table->string('destino')->nullable()->after('fecha');
            $table->string('referencia', 100)->nullable()->after('destino');
            $table->text('observaciones')->nullable()->after('referencia');
            $table->unique(['usuario_id', 'referencia']);
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table): void {
            $table->dropUnique(['usuario_id', 'referencia']);
            $table->dropForeign(['categoria_id']);
            $table->dropColumn(['categoria_id', 'destino', 'referencia', 'observaciones']);
        });
    }
};
