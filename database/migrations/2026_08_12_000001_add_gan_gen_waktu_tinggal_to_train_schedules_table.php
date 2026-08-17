<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan 2 kolom yang ada di template import jadwal SDT
     * ("template_import_jadwal_sdt_12agustus2026.xlsx", kolom I & J) tapi
     * belum punya field di aplikasi -- kolom K ("Keterangan") di template
     * itu SUDAH ada sebelumnya sebagai kolom `catatan` (lihat migrasi
     * create_train_schedules_table), cuma di form admin labelnya masih
     * "Notes", sekarang diganti jadi "Keterangan" biar konsisten dengan
     * istilah di template.
     */
    public function up(): void
    {
        // Pakai Schema::hasColumn() sebelum tiap ->addColumn supaya migration
        // ini AMAN dijalankan ulang (idempotent) -- kejadian di server:
        // percobaan migrate sebelumnya sempat menambahkan `gan_gen` tapi
        // gagal sebelum sempat tercatat "sudah dijalankan" di tabel
        // `migrations`, jadi php artisan migrate mengulang lagi dan error
        // "duplicate column name: gan_gen". Dengan guard ini, kolom yang
        // sudah ada dilewati, yang belum ada tetap ditambahkan.
        Schema::table('train_schedules', function (Blueprint $table) {
            if (! Schema::hasColumn('train_schedules', 'gan_gen')) {
                $table->string('gan_gen', 60)->nullable()->after('track_id')
                    ->comment('Kolom "Gan-Gen" (gandeng-gantung rangkaian) dari template import jadwal');
            }
            if (! Schema::hasColumn('train_schedules', 'waktu_tinggal_menit')) {
                $table->unsignedInteger('waktu_tinggal_menit')->nullable()->after('gan_gen')
                    ->comment('Kolom "Waktu Tinggal (menit)" dari template import jadwal -- lama KA berhenti di stasiun ini dalam menit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('train_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('train_schedules', 'gan_gen')) {
                $table->dropColumn('gan_gen');
            }
            if (Schema::hasColumn('train_schedules', 'waktu_tinggal_menit')) {
                $table->dropColumn('waktu_tinggal_menit');
            }
        });
    }
};
