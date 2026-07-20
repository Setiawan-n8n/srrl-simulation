<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Metadata tambahan untuk stasiun yang punya emplasemen sendiri
     * (is_own_station = true), dipakai untuk tab & denah simulasi.
     */
    public function up(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->string('km_position')->nullable()->after('is_own_station')
                ->comment('Posisi KM emplasemen, mis. "Km. 229+573"');
            $table->string('arah_barat_label')->nullable()->after('km_position')
                ->comment('Label arah sisi barat pada denah, mis. "Arah Wonokromo"');
            $table->string('arah_timur_label')->nullable()->after('arah_barat_label')
                ->comment('Label arah sisi timur pada denah, mis. "Arah Sidotopo"');
            $table->unsignedInteger('sort_order')->default(0)->after('arah_timur_label')
                ->comment('Urutan tab stasiun pada halaman simulasi (urut sepanjang lintas)');
        });
    }

    public function down(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->dropColumn(['km_position', 'arah_barat_label', 'arah_timur_label', 'sort_order']);
        });
    }
};
