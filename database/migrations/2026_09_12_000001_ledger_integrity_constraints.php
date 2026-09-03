<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asientos', function (Blueprint $table) {
            $table->unique(['usuario_id', 'origen_tipo', 'origen_id'], 'asientos_origen_unico');
            $table->foreign('asiento_reversado_id')
                ->references('id')->on('asientos')->nullOnDelete();
        });

        Schema::table('hechos_tesoreria', function (Blueprint $table) {
            $table->foreign('meta_ahorro_id')
                ->references('id')->on('metas_ahorro')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hechos_tesoreria', function (Blueprint $table) {
            $table->dropForeign(['meta_ahorro_id']);
        });

        Schema::table('asientos', function (Blueprint $table) {
            $table->dropForeign(['asiento_reversado_id']);
            $table->dropUnique('asientos_origen_unico');
        });
    }
};
