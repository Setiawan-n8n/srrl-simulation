<?php

namespace Database\Seeders;

use App\Models\Station;
use App\Models\Track;
use Illuminate\Database\Seeder;

class TrackSeeder extends Seeder
{
    private array $roman = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII', 'XIII'];

    /**
     * Jenis jalur per kode, dipakai untuk data SGU presisi (lihat run()).
     */
    private array $jenisSgu = [
        'I' => 'Sepur lurus (Commuter Line)',
        'II' => 'Sepur badug',
        'III' => 'Sepur badug',
        'IV' => 'Sepur badug',
        'V' => 'Sepur badug',
        'VI' => 'Sepur lurus / dinas rangkaian',
    ];

    /**
     * Jumlah jalur stasiun lain, dibaca (disederhanakan) dari
     * "Gambar Emplasemen Stasiun Wilayah SRRL.pdf" (Sintelis Daop 8).
     */
    private array $jumlahJalur = [
        'SBI' => 13, // Surabaya Pasar Turi
        'SBK' => 13, // Surabaya Kota
        'WO' => 5,   // Wonokromo
        'WR' => 4,   // Waru
        'GDG' => 2,  // Gedangan
        'SDA' => 4,  // Sidoarjo
    ];

    public function run(): void
    {
        $sgu = Station::where('code', 'SGU')->first();
        if ($sgu) {
            $this->seedJalurSguPresisi($sgu);
        }

        foreach ($this->jumlahJalur as $stationCode => $jumlah) {
            $station = Station::where('code', $stationCode)->first();
            if (! $station) {
                continue;
            }

            for ($i = 0; $i < $jumlah; $i++) {
                $code = $this->roman[$i] ?? (string) ($i + 1);
                $jenis = 'Sepur badug';
                if ($i === 0) {
                    $jenis = 'Sepur lurus';
                } elseif ($i === $jumlah - 1) {
                    $jenis = 'Sepur lurus / dinas rangkaian';
                }

                Track::query()->updateOrCreate(
                    ['station_id' => $station->id, 'code' => $code],
                    [
                        'name' => 'Jalur '.$code,
                        'jenis' => $jenis,
                        'sort_order' => $i + 1,
                    ]
                );
            }
        }
    }

    /**
     * Jalur I-VI Surabaya Gubeng, digitisasi presisi dari lapisan teks
     * (bukan raster) pada "Gambar Emplasemen Stasiun Wilayah SRRL.pdf",
     * halaman "SURABAYA GUBENG" (viewBox identik dengan img/emplasemen/sgu.png,
     * 1 pt PDF = 1 px viewBox, sudah diverifikasi visual titik-per-titik).
     *
     * Metodologi:
     * 1. Setiap label "Km. N+MMM" pada gambar diekstrak berikut posisi
     *    piksel (x,y) persisnya via pdftotext -bbox (bukan dibaca visual).
     *    Ini membentuk kurva kalibrasi piksel-x -> KM chainage asli
     *    sepanjang gambar (gambar bersifat skematik/topologis, BUKAN
     *    berskala -- jarak piksel tidak proporsional terhadap jarak
     *    sebenarnya, sehingga KM asli dipakai sebagai sumber kebenaran,
     *    bukan piksel).
     * 2. Kode wesel & sinyal (mis. "201", "281", kotak nomor "51") diambil
     *    dari label yang sama, posisinya dikonversi ke KM lewat kurva
     *    kalibrasi di atas, lalu dikelompokkan ke jalur terdekat (I-VI)
     *    berdasarkan jarak piksel-Y ke garis horizontal jalur tsb.
     * 3. Peron (platform) tiap jalur dideteksi dari kotak abu-abu
     *    (RGB 233,233,233) pada gambar lewat analisis warna piksel,
     *    batas kiri/kanannya dikonversi ke KM yang sama.
     * 4. Panjang jalur & peron dalam meter = selisih KM x 1000 (bukan
     *    hasil pengukuran piksel, supaya akurat terhadap gambar sumber
     *    yang tidak berskala).
     *
     * Data hasil ekstraksi disimpan di
     * database/seeders/data/sgu_geometri_presisi.json (per jalur: y,
     * west_x/west_km & east_x/east_km = titik kereta muncul/masuk di tepi
     * gambar, peron_west_x/peron_west_km & peron_east_x/peron_east_km =
     * batas peron, points = polyline padat [x,y,km] tiap 20 m sepanjang
     * jalur untuk animasi kereta, wesels = daftar titik wesel/sinyal
     * dengan kode+posisi+KM asli).
     *
     * Catatan keterbatasan: pengelompokan kode wesel/sinyal ke jalur
     * (langkah 2) dan pemisahan mana yang benar-benar wesel fisik vs
     * kelompok ikon sinyal masuk memakai heuristik posisi (jarak
     * piksel-Y & pola nomor 51-55/71-74 = sinyal berkotak) -- bukan
     * penelusuran manual tiap simbol satu-persatu di gambar. Posisi KM
     * setiap titik sudah presisi (dari teks asli), tapi topologi
     * sambungan wesel (wesel mana terhubung ke wesel mana / persilangan
     * antar-jalur) belum dipetakan penuh -- lihat SignalWeselSeeder.
     */
    private function seedJalurSguPresisi(Station $sgu): void
    {
        $path = database_path('seeders/data/sgu_geometri_presisi.json');
        if (! file_exists($path)) {
            return;
        }
        $data = json_decode(file_get_contents($path), true);

        foreach ($data['tracks'] as $code => $t) {
            $diagramPath = [
                'y' => $t['y'],
                'west_entry' => [$t['west_x'], $t['y']],
                'east_entry' => [$t['east_x'], $t['y']],
                'dwell_start_x' => $t['peron_west_x'],
                'dwell_end_x' => $t['peron_east_x'],
                'points' => $t['points'],
                // Batas kotak peron (abu-abu, RGB 233,233,233 pada gambar
                // sumber) dalam sumbu-Y, dideteksi lewat analisis warna
                // piksel -- dipakai simulation.js utk menggambar area hover
                // transparan di atas kotak peron supaya bisa menampilkan
                // info platform saat kursor diarahkan ke sana.
                'peron_y_top' => $t['peron_y_top'] ?? null,
                'peron_y_bottom' => $t['peron_y_bottom'] ?? null,
                // Sepur menyimpang (siding), kalau ada -- lihat jalur VI:
                // KA "Dinas Rangkaian ... SB" secara fisik menyimpang ke
                // arah Balai Yasa (BY) lewat wesel 261/282, BUKAN
                // meneruskan lurus ke ujung jalur utama. simulation.js
                // memakai 'for_relasi_code' utk mendeteksi baris jadwal
                // mana yang harus dianimasikan lewat sepur ini (lihat
                // getPhase() -> sidingMatches()).
                'siding' => $t['siding'] ?? null,
            ];

            Track::query()->updateOrCreate(
                ['station_id' => $sgu->id, 'code' => $code],
                [
                    'name' => 'Jalur '.$code,
                    'jenis' => $this->jenisSgu[$code] ?? 'Sepur badug',
                    'sort_order' => array_search($code, $this->roman, true) + 1,
                    'diagram_path' => $diagramPath,
                    'km_start' => $t['west_km'],
                    'km_end' => $t['east_km'],
                    'peron_km_start' => $t['peron_west_km'],
                    'peron_km_end' => $t['peron_east_km'],
                    'panjang_jalur_m' => $t['length_m'],
                    'panjang_peron_m' => $t['peron_length_m'],
                ]
            );
        }
    }
}
