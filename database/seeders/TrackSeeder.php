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

    /**
     * Urutan jalur SDT dari atas ke bawah pada gambar sumber (17 jalur:
     * 1-5 & 7-9 & 16 berlabel angka biasa, VI/X-XV/XVII berlabel angka
     * Romawi -- persis seperti tercetak di "Gambar Emplasemen SDT.pdf").
     * "DIPO LOK" & "DIPO MEKANIK" ditambahkan di paling atas (Agustus
     * 2026) -- sepur buntu (dead-end siding), masing-masing dari wesel 3
     * & wesel 3a pada throat barat, TIDAK termasuk 17 jalur bernomor di
     * atas. Lihat sdt_geometri_presisi.json key "DIPO LOK"/"DIPO MEKANIK"
     * utk detail ekstraksi kurvanya dari PDF.
     */
    private array $urutanJalurSdt = ['DIPO LOK', 'DIPO MEKANIK', 'XVII', '16', 'XV', 'XIV', 'XIII', 'XII', 'XI', 'X', '9', '8', '7', 'VI', '5', '4', '3', '2', '1'];

    public function run(): void
    {
        $sgu = Station::where('code', 'SGU')->first();
        if ($sgu) {
            $this->seedJalurSguPresisi($sgu);
        }

        $sdt = Station::where('code', 'SDT')->first();
        if ($sdt) {
            $this->seedJalurSdtPresisi($sdt);
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

    /**
     * Jalur 1-17 (17 jalur) Sidotopo, digitisasi dari "Gambar Emplasemen
     * SDT.pdf" (Sintelis Daop 8, April 2020). Sumber: database/seeders/data/
     * sdt_geometri_presisi.json.
     *
     * Metodologi (BERBEDA dari SGU -- lihat perbedaan di bawah):
     * 1. Posisi Y & rentang X badan lurus tiap jalur (bagian sejajar di
     *    tengah gambar, tempat kereta langsir berhenti) diekstrak LANGSUNG
     *    dari koordinat vektor garis pada PDF (bukan perkiraan piksel) --
     *    ini akurat.
     * 2. Label "Km. N+MMM" pada gambar (55 titik) diekstrak dari lapisan
     *    teks PDF dan dipakai membangun kurva kalibrasi linear piksel-X ->
     *    KM chainage terpisah untuk sisi barat (kontinuitas KM SGU-BET,
     *    Km 2,5-3,5) dan sisi timur (kontinuitas KM ke arah Benteng, Km
     *    3,5-4,4). Sidotopo adalah simpang tiga dengan BEBERAPA sistem KM
     *    tumpang-tindih di sisi barat (SDT-KLM & SDT-SB mulai dari Km
     *    0+000 sendiri-sendiri, terpisah dari kontinuitas SGU-BET) --
     *    untuk kesederhanaan, kalibrasi barat yang dipakai di sini HANYA
     *    mengikuti kontinuitas KM SGU-BET; KM asli jalur SDT-KLM/SDT-SB
     *    tidak direpresentasikan dalam field west_km/east_km di sini.
     * 3. BEDA UTAMA dari SGU: PDF ini tidak menaruh nomor wesel/sinyal
     *    sebagai teks (nomor di gambar berupa bentuk vektor/glyph, bukan
     *    karakter) -- nomor wesel (mis. "21", "50a/b") di data ini didapat
     *    lewat OCR (tesseract) atas render PDF 300dpi, BUKAN dari lapisan
     *    teks. Sebagian nomor gagal terbaca/salah baca OCR (lihat field
     *    'ocr_conf' per wesel di JSON sumber) -- yang confidence-nya
     *    rendah sengaja TIDAK disertakan di sini.
     * 4. Kurva jalur pada area percabangan wesel (throat barat & timur,
     *    tempat 17 jalur menyatu ke sedikit jalur/arah) BUKAN hasil
     *    pelacakan vektor titik-per-titik seperti lengkungan jalur VI SGU,
     *    melainkan kurva Bezier kuadratik halus dari ujung badan lurus
     *    (akurat) menuju titik pertemuan yang diperkirakan dari inspeksi
     *    visual gambar (per kelompok jalur, bukan per wesel individual).
     *    Artinya: bentuk visual jalur di area ini mendekati tapi TIDAK
     *    identik dengan gambar sumber, dan topologi sambungan wesel yang
     *    sesungguhnya (jalur mana yang secara fisik terhubung ke arah Sgu
     *    vs Sb Kota vs dipo lewat wesel mana) belum dipetakan -- sama
     *    seperti keterbatasan crossover pada SGU, tapi lebih signifikan
     *    di sini karena Sidotopo adalah simpang tiga (bukan jalur lurus).
     * 5. Sidotopo tidak berperon (emplasemen langsir/depo barang, bukan
     *    stasiun penumpang) -- peron_west_x/peron_east_x dkk selalu null,
     *    KA tidak "berhenti pas peron" di sini (lihat stopXFor() di
     *    simulation.js, fallback ke titik tengah jalur kalau peron null).
     * 6. Track "DIPO LOK" & "DIPO MEKANIK" (ditambahkan Agustus 2026): sepur
     *    BUNTU (dead-end siding) tempat lokomotif "parkir"/dirawat setelah
     *    dinas, bercabang masing-masing dari wesel 3 & wesel 3a pada throat
     *    barat (lihat crop "Dari Sb ke dipo lok" pada PDF -- ketiga sepur
     *    "Dipo kereta"/"Dipo Lok"/"Dipo Mekanik" tergambar berurutan dari
     *    wesel 2/3/3a; "Dipo kereta" belum didigitisasi karena belum ada
     *    KA di jadwal yang butuh). Kurva keduanya diekstrak LANGSUNG dari
     *    koordinat bezier vektor PDF (bukan perkiraan/inspeksi visual
     *    seperti poin 4 di atas) -- garis "Dipo Lok"/"Dipo kereta" digambar
     *    tebal (linewidth 4pt) sedangkan "Dipo Mekanik" tipis (linewidth
     *    2pt), perbedaan ini dipakai sebagai salah satu penanda saat
     *    membedakan objek garis mana yang mana pada PDF. Keduanya tidak
     *    punya west_km/east_km (di luar sistem KM SGU-BET yang dipakai 17
     *    jalur bernomor) dan tidak punya data wesel/sinyal sendiri (wesel
     *    3 & 3a belum di-OCR dengan confidence yang layak).
     */
    private function seedJalurSdtPresisi(Station $sdt): void
    {
        $path = database_path('seeders/data/sdt_geometri_presisi.json');
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
                'peron_y_top' => $t['peron_y_top'] ?? null,
                'peron_y_bottom' => $t['peron_y_bottom'] ?? null,
                'siding' => null,
            ];

            // Nama & jenis khusus untuk sepur buntu (dead-end siding) yang
            // bukan bagian dari 17 jalur bernomor -- selain kodenya
            // (DIPO LOK/DIPO MEKANIK) sendiri sudah jelas, label "Jalur
            // DIPO LOK" akan terdengar aneh di UI kalau tidak dikustomisasi.
            $namaSiding = [
                'DIPO LOK' => 'Depo Lokomotif',
                'DIPO MEKANIK' => 'Depo Mekanik',
            ];

            Track::query()->updateOrCreate(
                ['station_id' => $sdt->id, 'code' => $code],
                [
                    'name' => $namaSiding[$code] ?? 'Jalur '.$code,
                    'jenis' => isset($namaSiding[$code]) ? 'Sepur buntu / depo' : 'Sepur langsir/depo',
                    'sort_order' => array_search($code, $this->urutanJalurSdt, true) + 1,
                    'diagram_path' => $diagramPath,
                    'km_start' => $t['west_km'],
                    'km_end' => $t['east_km'],
                    'peron_km_start' => null,
                    'peron_km_end' => null,
                    'panjang_jalur_m' => $t['length_m'],
                    'panjang_peron_m' => null,
                ]
            );
        }
    }
}
