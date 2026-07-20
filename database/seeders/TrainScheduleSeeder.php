<?php

namespace Database\Seeders;

use App\Models\Station;
use App\Models\Track;
use App\Models\Train;
use App\Models\TrainSchedule;
use App\Support\TrainCategorizer;
use Illuminate\Database\Seeder;

class TrainScheduleSeeder extends Seeder
{
    /**
     * Tanggal jadwal ini diimport dari file
     * "JADWAL KA SGU UPDATE 15 JULI 2026.xlsx".
     */
    private string $tanggal = '2026-07-15';

    public function run(): void
    {
        $path = database_path('seeders/data/jadwal_sgu_2026-07-15.json');
        $rows = json_decode(file_get_contents($path), true);

        $sguId = Station::where('code', 'SGU')->value('id');

        $stationCache = Station::query()->pluck('id', 'code')->all();
        $trackCache = Track::query()->where('station_id', $sguId)->pluck('id', 'code')->all();

        TrainSchedule::where('tanggal', $this->tanggal)->delete();

        foreach ($rows as $row) {
            $train = Train::query()->firstOrCreate(
                ['no_ka' => (string) $row['no_ka'], 'nama' => $row['nama_ka']],
                ['kategori' => TrainCategorizer::classify($row['nama_ka'])]
            );

            TrainSchedule::query()->create([
                'station_id' => $sguId,
                'tanggal' => $this->tanggal,
                'urutan' => $row['urutan'] ?? 0,
                'train_id' => $train->id,
                'no_ka' => (string) $row['no_ka'],
                'nama_ka' => $row['nama_ka'],
                'relasi_asal_id' => $stationCache[$row['relasi_asal']] ?? null,
                'relasi_tujuan_id' => $stationCache[$row['relasi_tujuan']] ?? null,
                'relasi_raw' => $row['relasi_raw'],
                'jam_datang' => $row['jam_datang'],
                'jam_datang_ket' => $row['jam_datang_ket'],
                'jam_berangkat' => $row['jam_berangkat'],
                'jam_berangkat_ket' => $row['jam_berangkat_ket'],
                'track_id' => $trackCache[$row['jalur']] ?? null,
            ]);
        }
    }
}
