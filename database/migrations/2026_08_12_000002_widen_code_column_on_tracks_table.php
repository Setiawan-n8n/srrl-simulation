<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom `code` di tabel tracks tadinya VARCHAR(5) (cukup untuk kode
     * romawi terpanjang, "XVII"), tapi track baru "DIPO LOK" (siding depo
     * lokomotif Sidotopo -- lihat TrackSeeder::seedJalurSdtPresisi() &
     * sdt_geometri_presisi.json) butuh 8 karakter. Diperlebar ke VARCHAR(20)
     * supaya ada ruang untuk nama siding/depo lain juga kalau suatu saat
     * ditambahkan (mis. "DIPO KERETA", "DIPO MEKANIK").
     *
     * Catatan SQLite: secara praktis SQLite tidak menegakkan batas panjang
     * VARCHAR (kolom varchar(5) tetap bisa diisi teks lebih panjang tanpa
     * error/potongan), jadi migration ini murni untuk KEBENARAN SKEMA &
     * kompatibilitas driver lain (MySQL/Postgres) -- bukan perbaikan bug
     * yang sudah nyata terjadi di data SQLite yang ada sekarang.
     */
    public function up(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->string('code', 20)
                ->comment('I, II, III, ... XVII, atau nama siding seperti DIPO LOK')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->string('code', 5)->comment('I, II, III, IV, V, VI')->change();
        });
    }
};
