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

    /**
     * Tanggal jadwal SDT, diimport dari file
     * "JADWAL KA SDT UPDATE 12 Agustus 2026.xlsx".
     */
    private string $tanggalSdt = '2026-08-12';

    public function run(): void
    {
        $stationCache = Station::query()->pluck('id', 'code')->all();

        $this->seedDari('jadwal_sgu_2026-07-15.json', 'SGU', $this->tanggal, $stationCache);
        $this->seedDari('jadwal_sdt_2026-08-12.json', 'SDT', $this->tanggalSdt, $stationCache);
    }

    /**
     * Import satu file jadwal (format JSON hasil konversi Excel, lihat
     * app/Support/JadwalImporter.php utk format Excel aslinya) ke jadwal
     * milik SATU stasiun (emplasemen) tertentu.
     *
     * Catatan jadwal SDT (12 Agustus 2026), beda dari SGU: file sumbernya
     * TIDAK punya kolom "Nama KA" terisi (hanya nomor KA, mis. "L90-3"),
     * jadi nama_ka di sini disamakan dengan no_ka. Kolom "DAFTAR JALUR" pada
     * file sumber sebagian besar berisi nama sepur dipo (mis. "DIPO LOK")
     * yang belum dipetakan sebagai salah satu dari 17 jalur hasil digitisasi
     * (lihat TrackSeeder::seedJalurSdtPresisi()) -- baris dengan jalur yang
     * tidak cocok disimpan dengan track_id kosong (KA tetap tercatat di
     * jadwal tapi tidak dianimasikan pada jalur tertentu).
     */
    private function seedDari(string $file, string $stationCode, string $tanggal, array $stationCache): void
    {
        $path = database_path('seeders/data/'.$file);
        if (! file_exists($path)) {
            return;
        }
        $rows = json_decode(file_get_contents($path), true);

        $stationId = $stationCache[$stationCode] ?? null;
        if (! $stationId) {
            return;
        }

        $trackCache = Track::query()->where('station_id', $stationId)->pluck('id', 'code')->all();

        // PENTING: pakai whereDate(), BUKAN where('tanggal', ...) biasa.
        // Kolom `tanggal` di-cast 'date' pada model (lihat TrainSchedule.php),
        // tapi Eloquent tetap MENYIMPANNYA di kolom sebagai datetime penuh
        // ("2026-07-15 00:00:00"), bukan "2026-07-15" saja. where('tanggal',
        // '2026-07-15') membandingkan string mentah dan TIDAK PERNAH cocok
        // dengan nilai tersimpan itu -- delete() jadi no-op (0 baris terhapus)
        // tanpa error, sehingga insert berikutnya bentrok UNIQUE(tanggal,
        // urutan) dengan baris lama begitu seeder ini dijalankan kedua
        // kalinya (mis. migrate --seed di server yang databasenya sudah
        // pernah di-seed sebelumnya). whereDate() membandingkan hanya bagian
        // tanggalnya lewat fungsi DATE() SQL, jadi selalu cocok dengan benar
        // di semua driver (termasuk SQLite).
        TrainSchedule::where('station_id', $stationId)->whereDate('tanggal', $tanggal)->delete();

        foreach ($rows as $row) {
            $train = Train::query()->firstOrCreate(
                ['no_ka' => (string) $row['no_ka'], 'nama' => $row['nama_ka']],
                ['kategori' => TrainCategorizer::classify($row['nama_ka'])]
            );

            TrainSchedule::query()->create([
                'station_id' => $stationId,
                'tanggal' => $tanggal,
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
                'track_id' => $row['jalur'] ? ($trackCache[$row['jalur']] ?? null) : null,
                'gan_gen' => $row['gan_gen'] ?? null,
                'waktu_tinggal_menit' => $row['waktu_tinggal_menit'] ?? null,
                'catatan' => $row['catatan'] ?? null,
            ]);
        }
    }
}
