<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan station_id ke tracks/signals/wesels/train_schedules supaya
     * masing-masing bisa dimiliki oleh stasiun (emplasemen) yang berbeda,
     * bukan cuma SGU. Data lama di-backfill ke SGU supaya tidak hilang.
     */
    public function up(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->foreignId('station_id')->nullable()->after('id')->constrained('stations')->nullOnDelete();
        });

        Schema::table('signals', function (Blueprint $table) {
            $table->foreignId('station_id')->nullable()->after('id')->constrained('stations')->nullOnDelete();
        });

        Schema::table('wesels', function (Blueprint $table) {
            $table->foreignId('station_id')->nullable()->after('id')->constrained('stations')->nullOnDelete();
        });

        Schema::table('train_schedules', function (Blueprint $table) {
            $table->foreignId('station_id')->nullable()->after('id')->constrained('stations')->nullOnDelete();
        });

        $sguId = DB::table('stations')->where('code', 'SGU')->value('id');

        if ($sguId) {
            DB::table('tracks')->whereNull('station_id')->update(['station_id' => $sguId]);
            DB::table('signals')->whereNull('station_id')->update(['station_id' => $sguId]);
            DB::table('wesels')->whereNull('station_id')->update(['station_id' => $sguId]);
            DB::table('train_schedules')->whereNull('station_id')->update(['station_id' => $sguId]);
        }

        // Kode jalur/sinyal/wesel sekarang unik per-stasiun, bukan global lagi.
        Schema::table('tracks', function (Blueprint $table) {
            $table->dropUnique('tracks_code_unique');
            $table->unique(['station_id', 'code']);
        });

        Schema::table('signals', function (Blueprint $table) {
            $table->dropUnique('signals_code_side_unique');
            $table->unique(['station_id', 'code', 'side']);
        });

        Schema::table('wesels', function (Blueprint $table) {
            $table->dropUnique('wesels_code_side_unique');
            $table->unique(['station_id', 'code', 'side']);
        });
    }

    public function down(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->dropUnique(['station_id', 'code']);
            $table->unique('code');
            $table->dropConstrainedForeignId('station_id');
        });

        Schema::table('signals', function (Blueprint $table) {
            $table->dropUnique(['station_id', 'code', 'side']);
            $table->unique(['code', 'side']);
            $table->dropConstrainedForeignId('station_id');
        });

        Schema::table('wesels', function (Blueprint $table) {
            $table->dropUnique(['station_id', 'code', 'side']);
            $table->unique(['code', 'side']);
            $table->dropConstrainedForeignId('station_id');
        });

        Schema::table('train_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('station_id');
        });
    }
};
