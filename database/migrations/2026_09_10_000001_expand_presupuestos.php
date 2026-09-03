<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presupuestos', function (Blueprint $table): void {
            $table->json('umbrales_alerta')->nullable()->after('mes');
        });
    }

    public function down(): void
    {
        Schema::table('presupuestos', fn (Blueprint $table) => $table->dropColumn('umbrales_alerta'));
    }
};
