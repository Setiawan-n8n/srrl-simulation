<?php

namespace Database\Seeders;

use App\Models\Station;
use App\Models\Track;
use App\Models\TrackAdjacency;
use Illuminate\Database\Seeder;

class TrackAdjacencySeeder extends Seeder
{
    /**
     * Pasangan jalur SGU yang bertetangga langsung di ladder wesel,
     * diverifikasi VISUAL langsung dari img/emplasemen/sgu.png (bukan
     * ditebak dari kedekatan koordinat kode wesel semata -- percobaan
     * pertama dengan cara itu ternyata salah, lihat catatan migrasi
     * 2026_07_19_100003_create_track_adjacencies_table).
     *
     * Metodologi per baris di bawah: bandingkan polyline titik jalur
     * (database/seeders/data/sgu_geometri_presisi.json, field `points`)
     * antar-jalur -- kalau dua jalur punya y yang PERSIS SAMA sepanjang
     * suatu rentang x (rel-nya berhimpit, artinya secara fisik memakai
     * rel yang sama), atau titik akhir satu jalur bertemu presisi dengan
     * posisi wesel yang tercatat di jalur lain, itu bukti kuat mereka
     * bertetangga. Titik temu itu lalu di-crop dari sgu.png pada
     * koordinat yang sama (x * (lebar_png/5669.2915)) dan diperiksa
     * visual untuk memastikan memang ada garis rel yang menyatu (bukan
     * cuma dua garis sejajar yang kebetulan berdekatan).
     *
     * KETERBATASAN YANG SENGAJA DITERIMA (lihat AskUserQuestion sesi ini,
     * user memilih "model adjacency sederhana"): emplasemen SGU ternyata
     * berupa ladder wesel BERTINGKAT (rangkaian >10 wesel berurutan di
     * sisi barat: 201,203,205,209,211,213,219,225,229,233,... -- bukan 1
     * wesel per pasangan jalur), sehingga jalur yang TIDAK bertetangga
     * langsung pun bisa saja memakai lead bersama yang sama sebelum
     * wesel mereka masing-masing. Tabel ini TIDAK memodelkan itu -- hanya
     * pasangan jalur yang langsung bersebelahan di ladder. Ini pilihan
     * sadar (akurasi lebih rendah dari model ladder penuh, tapi jauh
     * lebih cepat & rendah risiko salah tebak) untuk kebutuhan deteksi
     * dini "konflik ladder wesel" di simulasi.
     */
    public function run(): void
    {
        $sgu = Station::where('code', 'SGU')->first();
        if (! $sgu) {
            return;
        }

        $tracks = Track::where('station_id', $sgu->id)->orderBy('sort_order')->get()->keyBy('code');

        TrackAdjacency::where('station_id', $sgu->id)->delete();

        $pairs = [
            // [kode A, kode B, sisi, catatan sumber]
            ['I', 'II', 'barat', 'Diverifikasi tidak langsung: keduanya bagian dari ladder barat yang sama (wesel 201/203 dst pada sgu.png), berurutan tepat setelah jalur II. Belum ada bukti koordinat setegas pasangan lain di bawah -- confidence lebih rendah.'],
            ['II', 'III', 'barat', 'Titik polyline jalur II & III BERHIMPIT PERSIS (y=944) dari x=1161 s/d x=1841 di sgu_geometri_presisi.json -- rel yang sama sebelum jalur III bercabang sendiri. Diverifikasi visual lewat crop sgu.png di area itu (jalur II & III sama-sama tampak sebagai satu garis di sana).'],
            ['III', 'IV', 'barat', 'Wesel 229 (tercatat sebagai wesel jalur IV, x=2227.3) posisinya PERSIS SAMA dengan titik awal (west_x) polyline jalur IV -- diverifikasi visual: wesel 229 pada sgu.png adalah titik jalur IV bercabang dari lead yang datang dari arah jalur III.'],
            ['IV', 'V', 'barat', 'Wesel 233 (tercatat sebagai wesel jalur V, x=2403.1) posisinya PERSIS SAMA dengan titik awal (west_x) polyline jalur V -- pola identik dengan III/IV di atas, diverifikasi visual pada sgu.png.'],
            ['V', 'VI', 'timur', 'Wesel 273 (tercatat sebagai wesel jalur V, x=3843.5, km 2.956) adalah titik jalur VI MENYATU ke rel jalur V (polyline jalur VI & V berhimpit persis, y=1306-1308, mulai x=3961 sampai ujung timur) -- diverifikasi visual pada sgu.png: satu garis gabung tunggal, jelas terlihat.'],
        ];

        foreach ($pairs as [$codeA, $codeB, $side, $note]) {
            $trackA = $tracks->get($codeA);
            $trackB = $tracks->get($codeB);
            if (! $trackA || ! $trackB) {
                continue;
            }

            TrackAdjacency::create([
                'station_id' => $sgu->id,
                'track_a_id' => $trackA->id,
                'track_b_id' => $trackB->id,
                'side' => $side,
                'source_note' => $note,
            ]);
        }
    }
}
