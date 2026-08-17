<?php

namespace Database\Seeders;

use App\Models\Signal;
use App\Models\Station;
use App\Models\Track;
use App\Models\Wesel;
use Illuminate\Database\Seeder;

class SignalWeselSeeder extends Seeder
{
    /** Stasiun lain yang dapat denah sederhana (1 sinyal masuk + 1 wesel per jalur per sisi). */
    private array $stasiunLain = ['SBI', 'SBK', 'WO', 'WR', 'GDG', 'SDA'];

    public function run(): void
    {
        $sgu = Station::where('code', 'SGU')->first();
        if ($sgu) {
            $this->seedSguPresisi($sgu);
        }

        $sdt = Station::where('code', 'SDT')->first();
        if ($sdt) {
            $this->seedSdtPresisi($sdt);
        }

        foreach ($this->stasiunLain as $code) {
            $station = Station::where('code', $code)->first();
            if (! $station) {
                continue;
            }

            $tracks = Track::where('station_id', $station->id)->orderBy('sort_order')->get();
            $labelBarat = $station->arah_barat_label ?: 'Barat';
            $labelTimur = $station->arah_timur_label ?: 'Timur';

            foreach ($tracks as $i => $track) {
                $n = $i + 1;
                $y = 90 + $i * 70;

                $this->buatSinyalWesel(
                    $station->id,
                    $track,
                    $y,
                    "{$code}M{$n}B",
                    "{$code}M{$n}T",
                    "{$code}W{$n}B",
                    "{$code}W{$n}T",
                    $labelBarat,
                    $labelTimur
                );
            }
        }
    }

    /**
     * Sinyal & wesel SGU, digitisasi presisi dari lapisan teks PDF (lihat
     * catatan metodologi lengkap di TrackSeeder::seedJalurSguPresisi()).
     * Sumber: database/seeders/data/sgu_geometri_presisi.json.
     *
     * Setiap titik sudah punya posisi (x,y) pada viewBox gambar asli DAN
     * posisi_km (KM chainage sungguhan, dipakai untuk tooltip & hitungan
     * panjang) -- bukan lagi kode "*T" (nomor seksi track circuit) seperti
     * data lama, melainkan nomor wesel/sinyal fisik (201, 203, ..., 281,
     * kotak sinyal 51-55/71-74, dst.) langsung dari label pada gambar.
     *
     * Keterbatasan yang masih berlaku: pengelompokan tiap titik ke jalur
     * (I-VI) memakai jarak piksel-Y terdekat, dan pembedaan wesel fisik vs
     * kelompok ikon sinyal masuk memakai heuristik pola nomor (lihat
     * TrackSeeder). Topologi sambungan antar-wesel (persilangan ke jalur
     * lain) belum dipetakan -- posisi & KM tiap titik akurat, tapi field
     * track_from_id/track_to_id pada Wesel di sini disamakan dengan
     * track_id jalur tempat titik itu berada (belum memodelkan crossover).
     */
    private function seedSguPresisi(Station $sgu): void
    {
        $path = database_path('seeders/data/sgu_geometri_presisi.json');
        if (! file_exists($path)) {
            return;
        }
        $data = json_decode(file_get_contents($path), true);
        $tracks = Track::where('station_id', $sgu->id)->orderBy('sort_order')->get();

        // PENTING: hapus dulu SEMUA sinyal/wesel SGU yang sudah ada sebelum
        // menulis ulang dari data presisi. Versi lama seeder ini (sebelum
        // digitisasi presisi) sempat menulis kode placeholder gaya "*T"
        // (nomor seksi track circuit, mis. "225T", "281T") dan kode
        // auto-numbering lain -- karena updateOrCreate() di bawah hanya
        // meng-update baris yang KODE-nya cocok persis dengan data baru,
        // baris lama dengan kode yang sudah tidak dipakai lagi (tidak ada
        // padanannya di data presisi) tidak pernah terhapus otomatis dan
        // terus nyangkut di database produksi -- itulah sebabnya sempat
        // muncul tooltip hover seperti "Switch 225T"/"Signal 70" yang tidak
        // punya simbol di gambar (posisinya peninggalan metode lama, bukan
        // dari digitisasi presisi ini). Wipe-and-recreate di sini menjamin
        // tidak ada sisa data lama, dengan konsekuensi: kalau ada
        // sinyal/wesel SGU yang PERNAH diedit manual lewat panel admin,
        // editan itu akan hilang & ditimpa ulang oleh hasil digitisasi tiap
        // kali seeder ini dijalankan.
        Signal::where('station_id', $sgu->id)->delete();
        Wesel::where('station_id', $sgu->id)->delete();

        $sisiDari = function (float $x, array $t): string {
            // barat = sebelah barat peron (arah Wonokromo), timur = sebelah timur peron (arah Sidotopo/Kota)
            return $x <= (($t['peron_west_x'] + $t['peron_east_x']) / 2) ? 'barat' : 'timur';
        };

        foreach ($data['tracks'] as $code => $t) {
            $track = $tracks->firstWhere('code', $code);
            if (! $track) {
                continue;
            }

            foreach ($t['wesels'] as $w) {
                $side = $sisiDari($w['x'], $t);
                if (($w['type'] ?? 'wesel') === 'signal') {
                    Signal::query()->updateOrCreate(
                        ['station_id' => $sgu->id, 'code' => $w['code'], 'side' => $side],
                        [
                            'track_id' => $track->id,
                            'jenis' => 'masuk',
                            'posisi_km' => $w['km'],
                            'pos_x' => (int) round($w['x']),
                            'pos_y' => (int) round($w['y']),
                            'keterangan' => "Sinyal masuk untuk {$track->name}, KM {$this->fmtKm($w['km'])}. Posisi diambil langsung dari label kode pada lapisan teks PDF (Gambar Emplasemen Stasiun Wilayah SRRL, halaman SURABAYA GUBENG), dikonversi ke KM chainage lewat kurva kalibrasi titik-KM asli pada gambar yang sama (bukan perkiraan piksel).",
                        ]
                    );
                } else {
                    Wesel::query()->updateOrCreate(
                        ['station_id' => $sgu->id, 'code' => $w['code'], 'side' => $side],
                        [
                            'track_from_id' => $track->id,
                            'track_to_id' => $track->id,
                            'posisi_km' => $w['km'],
                            'pos_x' => (int) round($w['x']),
                            'pos_y' => (int) round($w['y']),
                            'keterangan' => "Wesel/titik pengaman {$track->name}, KM {$this->fmtKm($w['km'])}. Posisi & KM diambil langsung dari label kode pada lapisan teks PDF (Gambar Emplasemen Stasiun Wilayah SRRL, halaman SURABAYA GUBENG). Topologi sambungan ke jalur lain (crossover) belum dipetakan -- track_from_id/track_to_id disamakan dengan jalur ini.",
                        ]
                    );
                }
            }
        }

        // Sinyal masuk tingkat stasiun (kotak nomor 51-55 & 71-74) yang posisinya
        // berada di baris bersama di atas seluruh jalur, sehingga jalur spesifik
        // yang dikendalikannya tidak bisa dipastikan dari data teks saja --
        // disimpan tanpa track_id (berlaku umum untuk arah tsb).
        foreach ($data['station_signals'] as $s) {
            Signal::query()->updateOrCreate(
                ['station_id' => $sgu->id, 'code' => $s['code'], 'side' => $s['arah']],
                [
                    'track_id' => null,
                    'jenis' => 'masuk',
                    'posisi_km' => $s['km'],
                    'pos_x' => (int) round($s['x']),
                    'pos_y' => (int) round($s['y']),
                    'keterangan' => "Sinyal masuk stasiun arah ".($s['arah'] === 'barat' ? 'Wonokromo' : 'Sidotopo/Surabaya Kota').", KM {$this->fmtKm($s['km'])}. Posisi diambil langsung dari kotak nomor sinyal pada lapisan teks PDF. Sinyal ini berada di baris bersama sebelum jalur bercabang ke I-VI, sehingga belum dipetakan ke satu jalur spesifik (track_id kosong) -- secara operasional mengendalikan beberapa jalur sekaligus lewat interlocking.",
                ]
            );
        }
    }

    /**
     * Wesel Sidotopo, digitisasi dari "Gambar Emplasemen SDT.pdf" (lihat
     * catatan metodologi lengkap di TrackSeeder::seedJalurSdtPresisi()).
     * Sumber: database/seeders/data/sdt_geometri_presisi.json.
     *
     * BEDA dari SGU: kode wesel di sini berasal dari OCR (bukan lapisan
     * teks PDF), sehingga sebagian nomor tidak lengkap/tidak akurat --
     * hanya wesel dengan confidence OCR tinggi (>= 60%) yang disertakan
     * saat data ini dibangun. Semua entri disimpan sebagai jenis 'wesel'
     * (bukan 'signal') karena PDF sumber tidak membedakan keduanya lewat
     * teks yang bisa diekstrak -- lihat legenda gambar ("Kedudukan biasa
     * wesel terlayan pusat/tempat") untuk makna simbol lingkaran kecil di
     * dekat tiap nomor. Sisi (barat/timur) ditentukan dari titik tengah
     * jalur (west_x/east_x) karena Sidotopo tidak punya peron sebagai
     * acuan (beda dari SGU yang memakai titik tengah peron).
     */
    private function seedSdtPresisi(Station $sdt): void
    {
        $path = database_path('seeders/data/sdt_geometri_presisi.json');
        if (! file_exists($path)) {
            return;
        }
        $data = json_decode(file_get_contents($path), true);
        $tracks = Track::where('station_id', $sdt->id)->orderBy('sort_order')->get();

        // Wipe-and-recreate, sama alasannya dengan seedSguPresisi(): supaya
        // tidak ada sisa wesel dari percobaan seeding sebelumnya yang
        // kode/posisinya sudah tidak dipakai lagi oleh data presisi ini.
        Signal::where('station_id', $sdt->id)->delete();
        Wesel::where('station_id', $sdt->id)->delete();

        foreach ($data['tracks'] as $code => $t) {
            $track = $tracks->firstWhere('code', $code);
            if (! $track) {
                continue;
            }

            $tengah = ($t['west_x'] + $t['east_x']) / 2;

            foreach ($t['wesels'] as $w) {
                $side = $w['x'] <= $tengah ? 'barat' : 'timur';
                $confidence = $w['ocr_conf'] ?? null;

                Wesel::query()->updateOrCreate(
                    ['station_id' => $sdt->id, 'code' => $w['code'], 'side' => $side],
                    [
                        'track_from_id' => $track->id,
                        'track_to_id' => $track->id,
                        'posisi_km' => $w['km'],
                        'pos_x' => (int) round($w['x']),
                        'pos_y' => (int) round($w['y']),
                        'keterangan' => "Wesel {$track->name}, dekat KM {$this->fmtKm($w['km'])}. Nomor & posisi dibaca lewat OCR dari render PDF (confidence {$confidence}%), bukan dari lapisan teks -- mohon diverifikasi ulang lewat panel admin terhadap gambar sumber. Topologi sambungan ke jalur lain (crossover di area percabangan Sgu/Sb Kota/dipo) belum dipetakan.",
                    ]
                );
            }
        }
    }

    private function fmtKm(float $km): string
    {
        $whole = floor($km);
        $meter = (int) round(($km - $whole) * 1000);

        return $whole.'+'.str_pad((string) $meter, 3, '0', STR_PAD_LEFT);
    }

    private function buatSinyalWesel(
        int $stationId,
        Track $track,
        int $y,
        string $sinyalBarat,
        string $sinyalTimur,
        string $weselBarat,
        string $weselTimur,
        string $labelBarat,
        string $labelTimur
    ): void {
        Signal::query()->updateOrCreate(
            ['station_id' => $stationId, 'code' => $sinyalBarat, 'side' => 'barat'],
            [
                'track_id' => $track->id,
                'jenis' => 'masuk',
                'pos_x' => 170,
                'pos_y' => $y,
                'keterangan' => "Sinyal masuk arah {$labelBarat} untuk {$track->name}",
            ]
        );

        Signal::query()->updateOrCreate(
            ['station_id' => $stationId, 'code' => $sinyalTimur, 'side' => 'timur'],
            [
                'track_id' => $track->id,
                'jenis' => 'masuk',
                'pos_x' => 1030,
                'pos_y' => $y,
                'keterangan' => "Sinyal masuk arah {$labelTimur} untuk {$track->name}",
            ]
        );

        Wesel::query()->updateOrCreate(
            ['station_id' => $stationId, 'code' => $weselBarat, 'side' => 'barat'],
            [
                'track_from_id' => $track->id,
                'track_to_id' => $track->id,
                'pos_x' => 280,
                'pos_y' => $y,
                'keterangan' => "Wesel throat barat untuk {$track->name}",
            ]
        );

        Wesel::query()->updateOrCreate(
            ['station_id' => $stationId, 'code' => $weselTimur, 'side' => 'timur'],
            [
                'track_from_id' => $track->id,
                'track_to_id' => $track->id,
                'pos_x' => 920,
                'pos_y' => $y,
                'keterangan' => "Wesel throat timur untuk {$track->name}",
            ]
        );
    }
}
