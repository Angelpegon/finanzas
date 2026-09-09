<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hechos_tesoreria', function (Blueprint $table): void {
            $table->foreign('meta_ahorro_id')
                ->references('id')
                ->on('metas_ahorro')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hechos_tesoreria', function (Blueprint $table): void {
            $table->dropForeign(['meta_ahorro_id']);
        });
    }
};
