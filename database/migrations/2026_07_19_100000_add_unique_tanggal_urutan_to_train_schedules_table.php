<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prevents future duplicate schedule rows at the database level.
     *
     * Root cause of the duplicate rows seen in production: nothing stopped
     * the same (tanggal, urutan) pair from being inserted more than once
     * (e.g. an import request submitted twice in a row). JadwalImporter now
     * upserts on this pair instead of blindly inserting, and this unique
     * index makes it impossible even under a race between two requests.
     *
     * IMPORTANT: if the production table already has duplicate
     * (tanggal, urutan) rows, this migration will fail to add the index.
     * Run `php artisan schedules:dedupe --apply` first to remove the
     * existing duplicates, then run this migration.
     */
    public function up(): void
    {
        Schema::table('train_schedules', function (Blueprint $table) {
            $table->unique(['tanggal', 'urutan'], 'train_schedules_tanggal_urutan_unique');
        });
    }

    public function down(): void
    {
        Schema::table('train_schedules', function (Blueprint $table) {
            $table->dropUnique('train_schedules_tanggal_urutan_unique');
        });
    }
};
