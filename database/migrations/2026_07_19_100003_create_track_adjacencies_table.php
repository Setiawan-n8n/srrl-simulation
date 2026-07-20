<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daftar pasangan jalur yang secara fisik BERTETANGGA di ladder wesel
     * (berbagi rel/lead yang sama sebelum bercabang sendiri-sendiri) pada
     * satu sisi (barat/timur) emplasemen -- dipakai simulasi untuk
     * mendeteksi "konflik ladder wesel" (dua kereta di jalur PERON berbeda
     * tapi sama-sama memakai persimpangan fisik yang sama, lihat
     * findCollision() di public/js/simulation.js).
     *
     * SENGAJA terpisah dari tabel `wesels` (bukan menambah field di sana):
     * tabel `wesels` berisi titik-titik wesel INDIVIDUAL hasil digitisasi
     * teks PDF (lihat SignalWeselSeeder), dan menetapkan SATU nomor wesel
     * spesifik sebagai "penyambung" 2 jalur ternyata tidak selalu bisa
     * dipastikan tunggal & pasti dari data teks itu saja -- gambar
     * emplasemen aslinya (img/emplasemen/sgu.png) menunjukkan ladder wesel
     * BERTINGKAT (rangkaian wesel berurutan berbagi lead yang sama, bukan
     * cuma 1 wesel per pasangan jalur). Tabel ini memakai pendekatan yang
     * lebih sederhana & aman: cukup catat jalur mana yang BERTETANGGA
     * (tanpa mengklaim wesel spesifik mana), diverifikasi visual langsung
     * dari gambar untuk SGU (lihat catatan sumber per baris di
     * TrackAdjacencySeeder).
     */
    public function up(): void
    {
        Schema::create('track_adjacencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained('stations')->cascadeOnDelete();
            $table->foreignId('track_a_id')->constrained('tracks')->cascadeOnDelete();
            $table->foreignId('track_b_id')->constrained('tracks')->cascadeOnDelete();
            $table->enum('side', ['barat', 'timur'])->comment('Sisi ladder tempat kedua jalur ini berbagi lead/persimpangan yang sama');
            $table->string('source_note', 500)->nullable()->comment('Catatan verifikasi (mis. referensi wesel & rentang km yang dipakai sebagai bukti visual)');
            $table->timestamps();

            $table->unique(['track_a_id', 'track_b_id', 'side']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('track_adjacencies');
    }
};
