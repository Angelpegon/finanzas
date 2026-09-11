<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->tieneForeignKeyMeta()) {
            return;
        }

        Schema::table('hechos_tesoreria', function (Blueprint $table): void {
            $table->foreign('meta_ahorro_id')
                ->references('id')
                ->on('metas_ahorro')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! $this->tieneForeignKeyMeta()) {
            return;
        }

        Schema::table('hechos_tesoreria', function (Blueprint $table): void {
            $table->dropForeign(['meta_ahorro_id']);
        });
    }

    private function tieneForeignKeyMeta(): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA foreign_key_list('hechos_tesoreria')");

            foreach ($rows as $row) {
                if (($row->table ?? null) === 'metas_ahorro' && ($row->from ?? null) === 'meta_ahorro_id') {
                    return true;
                }
            }

            return false;
        }

        return collect(DB::select(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?
               AND CONSTRAINT_TYPE = ?',
            ['hechos_tesoreria', 'hechos_tesoreria_meta_ahorro_id_foreign', 'FOREIGN KEY']
        ))->isNotEmpty();
    }
};
