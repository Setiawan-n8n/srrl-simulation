<?php

namespace App\Support;

use App\Models\Station;
use App\Models\Track;
use App\Models\Train;
use App\Models\TrainSchedule;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class JadwalImporter
{
    /**
     * Import jadwal KA dari file Excel dengan format kolom (mulai C):
     * No, No KA, Relasi, Nama, DAT, BER, JALUR — header berada di baris 8.
     *
     * File sumber biasanya berisi BEBERAPA tabel dalam satu sheet: daftar
     * lengkap ("KA DI STASIUN ..."), lalu beberapa tabel turunan per
     * kategori (Keberangkatan, Kedatangan, KA Lokal, KA Barang, dst.) yang
     * isinya adalah PENGULANGAN dari daftar lengkap tersebut. Supaya jadwal
     * tidak terduplikasi, import ini otomatis BERHENTI begitu menemukan
     * baris kosong pertama (penanda akhir tabel pertama/daftar lengkap).
     *
     * Idempotent by design: each row is upserted on (tanggal, urutan)
     * rather than blindly inserted, and the whole import runs inside a
     * single DB transaction. This means importing the same file for the
     * same date twice — or a form double-submission race — can no longer
     * produce duplicate rows the way plain create() calls could. The
     * (tanggal, urutan) pair also has a DB-level unique constraint (see
     * the add_unique_tanggal_urutan_to_train_schedules_table migration)
     * as a second line of defence.
     *
     * @return int Jumlah baris yang berhasil diimport.
     */
    public static function importFromFile(string $file, string $tanggal, string $sheetName = 'Sheet1'): int
    {
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getSheetByName($sheetName) ?? $spreadsheet->getActiveSheet();

        return DB::transaction(function () use ($sheet, $tanggal) {
            $stationCache = Station::query()->pluck('id', 'code')->all();
            $trackCache = Track::query()->pluck('id', 'code')->all();

            TrainSchedule::where('tanggal', $tanggal)->delete();

            $imported = 0;
            $urutan = 0;

            foreach ($sheet->getRowIterator(9) as $row) {
                $cells = [];
                foreach ($row->getCellIterator('C', 'I') as $cell) {
                    $cells[] = $cell->getFormattedValue();
                }

                [$no, $noKa, $relasi, $nama, $dat, $ber, $jalur] = array_pad($cells, 7, null);

                // Baris kosong menandai akhir tabel pertama (daftar lengkap) —
                // hentikan import di sini agar tabel-tabel turunan di bawahnya
                // (yang isinya duplikat) tidak ikut terbaca.
                if (blank($noKa) && blank($relasi) && blank($nama)) {
                    break;
                }

                if (blank($noKa) || $noKa === 'No KA') {
                    continue;
                }

                $urutan++;

                [$asal, $tujuan] = self::splitRelasi($relasi);
                [$jamDatang, $ketDatang] = self::splitTime($dat);
                [$jamBerangkat, $ketBerangkat] = self::splitTime($ber);

                foreach ([$asal, $tujuan] as $code) {
                    if ($code && ! isset($stationCache[$code])) {
                        $station = Station::query()->create([
                            'code' => $code,
                            'name' => $code,
                            'side' => 'barat',
                            'keterangan' => 'Dibuat otomatis oleh import, mohon lengkapi nama & sisi (arah) yang benar.',
                        ]);
                        $stationCache[$code] = $station->id;
                    }
                }

                $train = Train::query()->firstOrCreate(
                    ['no_ka' => (string) $noKa, 'nama' => (string) $nama],
                    ['kategori' => TrainCategorizer::classify((string) $nama)]
                );

                TrainSchedule::query()->updateOrCreate(
                    [
                        'tanggal' => $tanggal,
                        'urutan' => $urutan,
                    ],
                    [
                        'train_id' => $train->id,
                        'no_ka' => (string) $noKa,
                        'nama_ka' => (string) $nama,
                        'relasi_asal_id' => $asal ? ($stationCache[$asal] ?? null) : null,
                        'relasi_tujuan_id' => $tujuan ? ($stationCache[$tujuan] ?? null) : null,
                        'relasi_raw' => $relasi,
                        'jam_datang' => $jamDatang,
                        'jam_datang_ket' => $ketDatang,
                        'jam_berangkat' => $jamBerangkat,
                        'jam_berangkat_ket' => $ketBerangkat,
                        'track_id' => $jalur ? ($trackCache[trim((string) $jalur)] ?? null) : null,
                    ]
                );

                $imported++;
            }

            return $imported;
        });
    }

    private static function splitRelasi(?string $relasi): array
    {
        if (blank($relasi)) {
            return [null, null];
        }

        $parts = array_map('trim', explode('-', $relasi));

        if (count($parts) < 2) {
            return [$parts[0] ?? null, null];
        }

        return [$parts[0], $parts[count($parts) - 1]];
    }

    private static function splitTime(?string $value): array
    {
        if (blank($value)) {
            return [null, null];
        }

        if (preg_match('/^\d{1,2}:\d{2}$/', trim($value))) {
            return [trim($value), null];
        }

        return [null, trim($value)];
    }
}
