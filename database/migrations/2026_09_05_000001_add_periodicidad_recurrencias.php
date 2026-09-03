<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurrencias', function (Blueprint $table): void {
            $table->string('periodicidad', 20)->default('mensual')->after('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('recurrencias', fn (Blueprint $table) => $table->dropColumn('periodicidad'));
    }
};
