<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan geometri berbasis KM chainage asli (hasil digitisasi
     * presisi dari lapisan teks PDF "Gambar Emplasemen Stasiun Wilayah
     * SRRL.pdf", halaman SURABAYA GUBENG) ke tabel tracks.
     *
     * Kenapa berbasis KM, bukan piksel gambar: gambar sumber adalah
     * diagram skematik (topologi), bukan gambar berskala -- jarak antar
     * simbol di gambar tidak proporsional terhadap jarak sebenarnya.
     * KM chainage yang tercetak pada tiap simbol dipakai sebagai sumber
     * kebenaran untuk menghitung panjang jalur & peron dalam meter;
     * koordinat piksel (pos_x/pos_y, disimpan di kolom diagram_path)
     * tetap dipertahankan untuk kebutuhan render visual di atas gambar
     * latar (img/emplasemen/sgu.png) yang memang sama persis skalanya
     * dengan halaman PDF sumber (1 pt PDF = 1 px viewBox).
     */
    public function up(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->decimal('km_start', 7, 3)->nullable()->after('diagram_path')
                ->comment('KM chainage ujung barat jalur (titik kereta muncul/masuk dari arah barat)');
            $table->decimal('km_end', 7, 3)->nullable()->after('km_start')
                ->comment('KM chainage ujung timur jalur (titik kereta muncul/masuk dari arah timur)');
            $table->decimal('peron_km_start', 7, 3)->nullable()->after('km_end')
                ->comment('KM chainage tepi barat peron/platform efektif jalur ini');
            $table->decimal('peron_km_end', 7, 3)->nullable()->after('peron_km_start')
                ->comment('KM chainage tepi timur peron/platform efektif jalur ini');
            $table->decimal('panjang_jalur_m', 8, 1)->nullable()->after('peron_km_end')
                ->comment('Panjang jalur dari km_start ke km_end dalam meter (|km_start-km_end| x 1000)');
            $table->decimal('panjang_peron_m', 8, 1)->nullable()->after('panjang_jalur_m')
                ->comment('Panjang peron efektif dalam meter, dipakai untuk menghentikan kereta pas di ujung peron');
        });
    }

    public function down(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->dropColumn([
                'km_start', 'km_end', 'peron_km_start', 'peron_km_end',
                'panjang_jalur_m', 'panjang_peron_m',
            ]);
        });
    }
};
