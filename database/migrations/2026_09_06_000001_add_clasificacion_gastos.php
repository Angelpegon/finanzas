<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hechos_tesoreria', function (Blueprint $table): void {
            $table->string('tipo_gasto', 20)->nullable()->after('tipo');
        });

        Schema::table('recurrencias', function (Blueprint $table): void {
            $table->string('tipo_gasto', 20)->nullable()->after('periodicidad');
        });
    }

    public function down(): void
    {
        Schema::table('recurrencias', fn (Blueprint $table) => $table->dropColumn('tipo_gasto'));
        Schema::table('hechos_tesoreria', fn (Blueprint $table) => $table->dropColumn('tipo_gasto'));
    }
};
