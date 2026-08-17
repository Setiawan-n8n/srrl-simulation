<?php

namespace App\Support;

use App\Models\Station;
use App\Models\Track;
use App\Models\Train;
use App\Models\TrainSchedule;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class JadwalImporter
{
    /**
     * Kata kunci header (regex, case-insensitive) per kolom yang dicari
     * `detectColumns()` -- urutan array menentukan prioritas pencocokan
     * per sel (kolom lebih spesifik dicek duluan supaya tidak salah
     * tertangkap kolom lain, mis. "Track" jangan sampai kena pola "no_ka").
     */
    private const HEADER_PATTERNS = [
        'jalur' => '/jalur|track/i',
        'no_ka' => '/no\.?\s*ka|nomor\s*ka|train\s*no/i',
        'nama' => '/nama\s*ka|train\s*name|^nama$/i',
        'relasi' => '/relasi|relation/i',
        'dat' => '/\bdat\b|arrival|datang/i',
        'ber' => '/\bber\b|departure|berangkat/i',
        // 3 kolom tambahan dari template import (Agustus 2026) -- opsional,
        // TIDAK dihitung wajib untuk deteksi header (lihat $bestScore di
        // detectColumns()), supaya file format lama yang tidak punya
        // kolom-kolom ini tetap terdeteksi normal.
        'gan_gen' => '/gan.?gen/i',
        'waktu_tinggal' => '/waktu\s*tinggal/i',
        'keterangan' => '/keterangan|catatan|^notes?$/i',
    ];

    /**
     * Import jadwal KA dari file Excel.
     *
     * BEDA dari versi lama (bug diperbaiki Agustus 2026 -- lihat catatan
     * SDT di README.md): dulu baris/kolom header di-hardcode (baris 8,
     * kolom C-I persis), jadi kalau file sumbernya punya layout berbeda
     * (mis. hasil tombol "Export Excel" yang headernya ada di baris 1,
     * kolom A-K) importnya "berhasil" tanpa error tapi datanya KACAU --
     * kolom-kolom salah geser tanpa ketahuan. Sekarang `detectColumns()`
     * MENCARI SENDIRI baris header & kolom yang tepat berdasarkan label
     * teksnya (lihat HEADER_PATTERNS), jadi kedua layout (format lama
     * "No, No KA, Relasi, Nama, DAT, BER, JALUR" di baris 8 kolom C-I,
     * MAUPUN format baru gaya Export Excel "Train No., Train Name,
     * Relation, Arrival, Departure, Track" di baris 1 kolom A-K) sama-sama
     * kebaca benar tanpa perlu tahu formatnya duluan.
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
     * `$stationId` WAJIB diisi -- dipakai untuk (a) mengisi kolom
     * station_id tiap baris jadwal supaya muncul di halaman simulasi
     * stasiun yang benar (sebelumnya tidak pernah diisi sama sekali --
     * baris jadwal manapun yang diimport lewat importer lama TIDAK PERNAH
     * muncul di simulasi manapun), dan (b) membatasi resolusi kode jalur
     * (`$jalur`) HANYA ke jalur milik stasiun ini -- kalau dua stasiun
     * kebetulan punya kode jalur sama (mis. SGU & SDT sama-sama punya
     * jalur "VI"), baris tidak akan salah nyangkut ke jalur stasiun lain.
     *
     * @return int Jumlah baris yang berhasil diimport.
     */
    public static function importFromFile(string $file, string $tanggal, int $stationId, string $sheetName = 'Sheet1'): int
    {
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getSheetByName($sheetName) ?? $spreadsheet->getActiveSheet();
        $columns = self::detectColumns($sheet);

        return DB::transaction(function () use ($sheet, $tanggal, $stationId, $columns) {
            $stationCache = Station::query()->pluck('id', 'code')->all();
            $trackCache = Track::query()->where('station_id', $stationId)->pluck('id', 'code')->all();

            TrainSchedule::where('station_id', $stationId)->whereDate('tanggal', $tanggal)->delete();

            $imported = 0;
            $urutan = 0;

            foreach ($sheet->getRowIterator($columns['header_row'] + 1) as $row) {
                $rowIndex = $row->getRowIndex();
                $cells = [];
                foreach (['no_ka', 'relasi', 'nama', 'dat', 'ber', 'jalur', 'gan_gen', 'waktu_tinggal', 'keterangan'] as $key) {
                    $col = $columns[$key];
                    $cells[$key] = $col ? $sheet->getCell($col.$rowIndex)->getFormattedValue() : null;
                }

                $noKa = $cells['no_ka'];
                $relasi = $cells['relasi'];
                $nama = $cells['nama'];
                $dat = $cells['dat'];
                $ber = $cells['ber'];
                $jalur = $cells['jalur'];
                $ganGen = blank($cells['gan_gen']) ? null : trim((string) $cells['gan_gen']);
                $waktuTinggalDigits = blank($cells['waktu_tinggal']) ? '' : preg_replace('/\D+/', '', (string) $cells['waktu_tinggal']);
                $waktuTinggal = $waktuTinggalDigits === '' ? null : (int) $waktuTinggalDigits;
                $keterangan = blank($cells['keterangan']) ? null : trim((string) $cells['keterangan']);

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

                // Kunci upsert (tanggal, urutan) di bawah mengikuti unique
                // index DB apa adanya (lihat migrasi
                // add_unique_tanggal_urutan_to_train_schedules_table) --
                // index itu GLOBAL, tidak per-stasiun. Kalau kebetulan
                // sudah ada baris stasiun LAIN dengan (tanggal, urutan)
                // yang sama, jangan sampai updateOrCreate() menimpanya jadi
                // milik stasiun ini secara diam-diam -- lewati baris ini
                // saja (tetap dihitung, tidak imported) supaya data stasiun
                // lain tidak rusak. Ini hanya bisa terjadi kalau dua
                // stasiun diimport dengan tanggal yang SAMA persis.
                $existingLain = TrainSchedule::where('tanggal', $tanggal)
                    ->where('urutan', $urutan)
                    ->where('station_id', '!=', $stationId)
                    ->exists();
                if ($existingLain) {
                    continue;
                }

                $train = Train::query()->firstOrCreate(
                    ['no_ka' => (string) $noKa, 'nama' => (string) $nama],
                    ['kategori' => TrainCategorizer::classify((string) $nama)]
                );

                $payload = [
                    'station_id' => $stationId,
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
                ];

                // Gan-Gen / Waktu Tinggal / Keterangan biasanya diisi MANUAL
                // oleh admin lewat form edit setelah import (template sumber
                // sering mengosongkan 3 kolom ini). Supaya re-import file
                // yang sama tidak diam-diam MENGHAPUS data yang sudah
                // dilengkapi admin, key ini hanya disertakan kalau file
                // benar-benar berisi nilai (tidak kosong).
                if ($ganGen !== null) {
                    $payload['gan_gen'] = $ganGen;
                }
                if ($waktuTinggal !== null) {
                    $payload['waktu_tinggal_menit'] = $waktuTinggal;
                }
                if ($keterangan !== null) {
                    $payload['catatan'] = $keterangan;
                }

                TrainSchedule::query()->updateOrCreate(
                    [
                        'tanggal' => $tanggal,
                        'urutan' => $urutan,
                    ],
                    $payload
                );

                $imported++;
            }

            return $imported;
        });
    }

    /**
     * Cari baris header & kolom untuk masing-masing field (no_ka, relasi,
     * nama, dat, ber, jalur) dengan mencocokkan teks tiap sel di 15 baris
     * pertama terhadap HEADER_PATTERNS. Baris dengan jumlah kecocokan
     * TERBANYAK dianggap baris header (minimal 4 dari 6 field wajib cocok
     * supaya tidak salah tangkap baris data biasa).
     *
     * Kalau deteksi gagal total (skor < 4 atau kolom no_ka tidak
     * ketemu) -- mis. file dengan layout yang benar-benar di luar dugaan
     * -- fallback ke asumsi format lama (baris 8, kolom C-I) supaya
     * setidaknya berperilaku sama seperti sebelum perbaikan ini, bukan
     * error total.
     */
    private static function detectColumns(Worksheet $sheet): array
    {
        $maxRow = min($sheet->getHighestRow(), 15);
        $maxColIndex = max(Coordinate::columnIndexFromString($sheet->getHighestColumn()), 12);

        $bestRow = null;
        $bestMap = [];
        $bestScore = 0;

        for ($r = 1; $r <= $maxRow; $r++) {
            $map = [];
            for ($c = 1; $c <= $maxColIndex; $c++) {
                $colLetter = Coordinate::stringFromColumnIndex($c);
                $value = trim((string) $sheet->getCell($colLetter.$r)->getValue());
                if ($value === '') {
                    continue;
                }
                foreach (self::HEADER_PATTERNS as $key => $pattern) {
                    if (isset($map[$key])) {
                        continue;
                    }
                    if (preg_match($pattern, $value)) {
                        $map[$key] = $colLetter;
                        break;
                    }
                }
            }

            if (count($map) > $bestScore) {
                $bestScore = count($map);
                $bestRow = $r;
                $bestMap = $map;
            }
        }

        if ($bestScore < 4 || ! isset($bestMap['no_ka'])) {
            return [
                'header_row' => 8,
                'no_ka' => 'D', 'relasi' => 'E', 'nama' => 'F', 'dat' => 'G', 'ber' => 'H', 'jalur' => 'I',
                'gan_gen' => null, 'waktu_tinggal' => null, 'keterangan' => null,
            ];
        }

        return array_merge(
            ['header_row' => $bestRow],
            array_fill_keys(['no_ka', 'relasi', 'nama', 'dat', 'ber', 'jalur', 'gan_gen', 'waktu_tinggal', 'keterangan'], null),
            $bestMap
        );
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
