<?php

namespace App\Console\Commands;

use App\Models\Station;
use App\Support\JadwalImporter;
use Illuminate\Console\Command;

class ImportJadwalKa extends Command
{
    /**
     * php artisan jadwal:import "storage/app/jadwal.xlsx" --tanggal=2026-07-15 --stasiun=SGU
     */
    protected $signature = 'jadwal:import
        {file : Path ke file .xlsx jadwal KA}
        {--tanggal= : Tanggal jadwal (YYYY-MM-DD), default hari ini}
        {--stasiun= : Kode stasiun emplasemen tujuan (mis. SGU, SDT) -- WAJIB}
        {--sheet=Sheet1 : Nama sheet}';

    protected $description = 'Import jadwal KA di satu stasiun emplasemen dari file Excel (format: No, No KA, Relasi, Nama, DAT, BER, JALUR mulai kolom C, header di baris 8)';

    public function handle(): int
    {
        $file = $this->argument('file');

        if (! file_exists($file)) {
            $this->error("File tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        $stasiunCode = $this->option('stasiun');
        if (! $stasiunCode) {
            $this->error('Wajib isi --stasiun=KODE (mis. --stasiun=SDT). Tanpa ini, jadwal tidak akan muncul di halaman simulasi manapun.');

            return self::FAILURE;
        }

        $station = Station::query()->where('code', $stasiunCode)->first();
        if (! $station) {
            $this->error("Stasiun dengan kode '{$stasiunCode}' tidak ditemukan.");

            return self::FAILURE;
        }

        $tanggal = $this->option('tanggal') ?: now()->format('Y-m-d');

        $imported = JadwalImporter::importFromFile($file, $tanggal, $station->id, $this->option('sheet'));

        $this->info("Berhasil import {$imported} baris jadwal stasiun {$station->code} untuk tanggal {$tanggal}.");

        return self::SUCCESS;
    }
}
