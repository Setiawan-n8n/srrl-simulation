<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom untuk mode "denah asli" -- stasiun yang punya gambar emplasemen
     * asli (hasil render PDF) sebagai latar, dengan jalur KA bergerak sesuai
     * jalur nyata di gambar (bukan skema sederhana).
     */
    public function up(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->string('diagram_svg_path')->nullable()->after('sort_order')
                ->comment('Path asset SVG/gambar denah asli, mis. img/emplasemen/sgu.svg');
            $table->string('diagram_viewbox')->nullable()->after('diagram_svg_path')
                ->comment('viewBox SVG yang dipakai, mis. "0 0 5669.2915 1984.252"');
        });

        Schema::table('tracks', function (Blueprint $table) {
            $table->json('diagram_path')->nullable()->after('sort_order')
                ->comment('Titik jalur nyata pada denah asli: west_entry, dwell_start_x, dwell_end_x, east_entry, y');
        });
    }

    public function down(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->dropColumn(['diagram_svg_path', 'diagram_viewbox']);
        });

        Schema::table('tracks', function (Blueprint $table) {
            $table->dropColumn('diagram_path');
        });
    }
};
