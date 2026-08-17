# Simulasi Perjalanan KA — Stasiun Surabaya Gubeng (SGU) & Sidotopo (SDT)

Aplikasi web Laravel + Filament untuk mensimulasikan perjalanan KA keluar-masuk
Stasiun Surabaya Gubeng (SGU) dan Sidotopo (SDT) berdasarkan jadwal harian,
dengan panel admin untuk mengelola seluruh data (jadwal, KA, jalur, sinyal,
wesel, stasiun relasi). Arsitekturnya multi-stasiun (tab pemilih stasiun di
halaman simulasi) -- SGU & SDT punya denah presisi berbasis gambar asli,
stasiun lain (Pasar Turi, Surabaya Kota, Wonokromo, Waru, Gedangan, Sidoarjo)
masih pakai denah generik sederhana.

Dibangun untuk SRRL Project, berdasarkan data:
- `JADWAL KA SGU UPDATE 15 JULI 2026.xlsx` (304 baris jadwal SGU — sudah di-seed otomatis)
- `Gambar Emplasemen Stasiun SGU.pdf` (denah emplasemen 6 jalur, Sintelis Daop 8, Juni 2017)
- `JADWAL KA SDT UPDATE 12 Agustus 2026.xlsx` (105 baris jadwal SDT — sudah di-seed otomatis)
- `Gambar Emplasemen SDT.pdf` (denah emplasemen 17 jalur langsir/depo, Sintelis Daop 8, April 2020)

## Fitur

- **Panel admin** (Filament) di `/admin` untuk CRUD: Jadwal KA, KA/relasi, Jalur,
  Sinyal, Wesel, dan Stasiun relasi (arah Wonokromo/Sidotopo).
- **Import Excel** langsung dari panel admin (tombol "Import Jadwal (Excel)" di
  halaman Jadwal KA) atau via `php artisan jadwal:import file.xlsx --tanggal=YYYY-MM-DD`.
- **Halaman simulasi publik** (`/`) menampilkan denah emplasemen SVG dengan animasi
  KA berjalan sesuai jadwal, kontrol putar/jeda, kecepatan simulasi, dan slider waktu.
  Sinyal berubah warna (hijau/merah) otomatis mengikuti okupansi jalur.

## Prasyarat

- PHP 8.1+ dengan ekstensi umum (mbstring, pdo_sqlite, xml, gd/intl untuk PhpSpreadsheet)
- Composer 2
- **Tidak perlu Node.js/npm** — halaman simulasi & admin panel Filament tidak
  memerlukan proses build front-end apa pun.

## Instalasi

```bash
cd simulasi-ka-sgu
composer install
copy .env.example .env        # Windows (PowerShell/CMD)
# atau: cp .env.example .env  # Git Bash / WSL / macOS / Linux
php artisan key:generate
```

Database sudah diset ke SQLite (`database/database.sqlite`, filenya sudah
disertakan kosong) — tidak perlu setup MySQL. Jalankan migrasi + seeder:

```bash
php artisan migrate --seed
```

Ini akan membuat seluruh tabel dan mengisi:
- 6 jalur emplasemen SGU (I–VI) + 17 jalur emplasemen Sidotopo (1–17)
- Sinyal & wesel SGU + Sidotopo (presisi, lihat catatan metodologi di atas),
  plus sinyal & wesel dasar untuk stasiun lain (representasi disederhanakan)
- 30 stasiun/relasi yang muncul di jadwal (dengan arah barat/timur)
- 304 baris jadwal KA SGU tanggal 15 Juli 2026 + 105 baris jadwal KA Sidotopo
  tanggal 12 Agustus 2026, sudah dipetakan ke Train master data
- 1 user admin

Jalankan server:

```bash
php artisan serve
```

Buka:
- `http://localhost:8000/` — halaman simulasi
- `http://localhost:8000/admin` — panel admin (login: `admin@sgu.local` / `password123`)

**Segera ganti password admin default setelah login pertama kali**, lewat
menu profil di panel admin atau `php artisan tinker`.

## Struktur data

| Tabel            | Isi                                                          |
|-------------------|---------------------------------------------------------------|
| `stations`        | Kode relasi (SGU, SB, KTG, dst.), nama, sisi (barat/timur)    |
| `tracks`           | Jalur I–VI                                                     |
| `signals`          | Sinyal per jalur per sisi (posisi X/Y untuk denah SVG)         |
| `wesels`           | Wesel per jalur per sisi                                       |
| `trains`           | Master No KA + nama + kategori (penumpang/barang/komuter/dinas)|
| `train_schedules`  | Baris jadwal harian: jam datang/berangkat, jalur, relasi        |

## Catatan penting / keterbatasan yang perlu diverifikasi

Saya (Claude) menyusun aplikasi ini tanpa akses menjalankan PHP/Composer secara
langsung di lingkungan kerja saya (dibatasi kebijakan jaringan sandbox), sehingga
kode sudah divalidasi sintaks penuh (88 file PHP, 0 error) dan logika inti
simulasi (perhitungan posisi KA & status sinyal) sudah diuji unit secara terpisah,
tapi **belum pernah benar-benar dijalankan end-to-end di server Laravel sungguhan**.
Kalau ada error saat `composer install` / `migrate` / `serve`, kirimkan pesan
errornya dan akan saya perbaiki.

Beberapa hal yang disederhanakan dan sebaiknya Anda tinjau/koreksi lewat panel admin:

1. **Arah stasiun relasi (`side` = barat/timur).** Saya menandai setiap kode
   stasiun (SB, KTG, SBI, dst.) sebagai arah Wonokromo (barat) atau arah
   Sidotopo/Surabaya Kota (timur) berdasarkan pengetahuan umum geografi jalur
   KA Surabaya — bukan dari dokumen resmi. Ini menentukan dari sisi mana KA
   masuk/keluar pada simulasi. Beberapa kode (SB, SBE, PB, PS, CN, CP, MLK)
   punya tingkat keyakinan lebih rendah — mohon dicek di menu **Master Data →
   Stasiun / Relasi**.
2. **Posisi & penomoran sinyal/wesel jalur I-VI SGU — sudah presisi (update
   Juli 2026).** Sebelumnya bagian ini memakai denah sederhana (satu sinyal +
   satu wesel per jalur per sisi, posisi perkiraan). Sekarang seluruh titik
   (52 sinyal/wesel, polyline 425 titik koordinat) diekstrak langsung dari
   **lapisan teks PDF** "Gambar Emplasemen Stasiun Wilayah SRRL.pdf" (halaman
   SURABAYA GUBENG) — bukan dibaca visual — lalu dikonversi ke KM chainage
   asli lewat kurva kalibrasi dari label "Km. N+MMM" pada gambar yang sama
   (gambar sumber skematik/tidak berskala, jadi KM asli dipakai sebagai
   sumber kebenaran, bukan jarak piksel). Metodologi lengkap ada di komentar
   `TrackSeeder::seedJalurSguPresisi()` dan `SignalWeselSeeder::seedSguPresisi()`.
   Yang **masih perkiraan**: pengelompokan tiap titik ke jalur tertentu
   (dari jarak piksel-Y terdekat) dan topologi sambungan antar-wesel
   (persilangan/crossover ke jalur lain belum dipetakan — posisi & KM tiap
   titik akurat, tapi rute-rute wesel belum di-link satu sama lain). 6 sinyal
   kotak (51, 52, 54, 71-74) belum bisa dipastikan mengontrol jalur yang mana
   persis (disimpan tanpa `track_id`, lihat `station_signals` di
   `sgu_geometri_presisi.json`). Stasiun selain SGU masih memakai denah
   sederhana lama.
3. **Sinyal bersifat indikatif, bukan interlocking sungguhan** — warnanya
   mengikuti jadwal (okupansi jalur), bukan simulasi logika pengamanan wesel/
   rute yang sebenarnya. Posisi wesel (normal/reverse) juga belum dimodelkan.
4. **Bentuk jalur VI menjelang ujung timur — sudah mengikuti lengkungan
   asli (update Juli 2026).** Sebelumnya SEMUA jalur (termasuk VI)
   dimodelkan sebagai satu garis lurus datar (Y konstan) hasil interpolasi
   kalibrasi KM semata. Padahal pada gambar sumber, jalur VI menjelang
   ujung timur benar-benar melengkung ke atas saat menyatu dengan jalur
   trunk menuju Sidotopo (Benteng) — dipakai a.l. oleh KA "Dinas Rangkaian
   ... SB" (langsir rangkaian kosong, ~20 baris jadwal/hari). Bentuk
   lengkungan ini sudah ditelusuri langsung dari piksel garis pada
   `img/emplasemen/sgu.png` resolusi asli (bukan estimasi) dan
   dimasukkan ke `tracks.VI.points` di `sgu_geometri_presisi.json`, jadi
   otomatis diikuti tanpa logika khusus per-KA. (Sempat ada percobaan fix
   yang salah arah — mengira KA tsb menyimpang ke sepur "Ke BY Sgu" via
   wesel 261/282 — sudah dikoreksi; data sepur itu masih tersimpan di
   `tracks.VI.siding` sebagai referensi tapi tidak dipakai baris jadwal
   manapun saat ini.) **Masih perlu ditelusuri**: jalur I-V lain mungkin
   juga melengkung serupa menjelang ujung jalurnya masing-masing, belum
   dicek satu-satu seperti jalur VI.

### Panjang jalur & peron SGU (hasil digitisasi presisi, KM chainage asli)

| Jalur | Panjang jalur efektif | Panjang peron | KM barat → KM timur |
|---|---|---|---|
| I | 1.685 m | 233 m | 4+274 → 2+589 |
| II | 2.223 m | 226 m | 4+867 → 2+644 |
| III | 1.084 m | 157 m | 4+274 → 3+190 |
| IV | 524 m | 152 m | 3+593 → 3+069 |
| V | 1.823 m | 151 m | 3+553 → 1+730 |
| VI | 942 m | 154 m | 3+433 → 2+491 |

"Panjang jalur efektif" = jarak antara wesel/sinyal terluar yang berhasil
dipetakan ke jalur tsb pada gambar (titik kereta muncul & menghilang di
simulasi); "panjang peron" = lebar kotak peron abu-abu pada gambar asli,
dipakai untuk menghentikan kereta pas di ujung peron (lihat `stopXFor()` di
`simulation.js`). Kedua angka dihitung dari selisih KM chainage (bukan
piksel) supaya akurat terhadap gambar sumber yang skematik/tidak berskala.

## Catatan Sidotopo (SDT) — ditambahkan Agustus 2026

Sidotopo ditambahkan sebagai stasiun simulasi kedua (17 jalur langsir/depo,
simpang tiga barat ke arah Surabaya Gubeng / Surabaya Kota (lewat STM) / dipo,
timur menyatu ke arah Benteng-Tanjung Perak). Metodologinya MIRIP SGU tapi
dengan beberapa perbedaan penting karena karakteristik file sumbernya
berbeda — mohon dibaca sebelum mempercayai denah ini sepenuhnya:

1. **Posisi & panjang jalur (data vektor asli, akurat).** Posisi Y dan
   rentang X badan lurus tiap jalur (bagian sejajar tempat KA langsir
   berhenti) diekstrak langsung dari koordinat garis vektor pada PDF —
   sama presisinya dengan SGU. KM chainage dihitung dari 55 label
   "Km. N+MMM" pada lapisan teks PDF, dengan kurva kalibrasi terpisah
   untuk sisi barat (kontinuitas KM SGU-BET) dan timur (kontinuitas KM ke
   arah Benteng) — lihat detail di `TrackSeeder::seedJalurSdtPresisi()`.
2. **Nomor wesel dari OCR, BUKAN lapisan teks (beda dari SGU).** PDF
   sumber Sidotopo tidak menaruh nomor wesel/sinyal sebagai karakter teks
   (dirender sebagai bentuk vektor) — 26 titik wesel di data ini didapat
   lewat OCR (tesseract) atas render PDF 300dpi. Sebagian nomor bisa
   salah baca atau tidak lengkap; hanya yang confidence OCR ≥ 60% yang
   disertakan (field `ocr_conf` di `sdt_geometri_presisi.json`).
3. **Kurva jalur di area percabangan wesel = interpolasi, BUKAN vector-trace.**
   Berbeda dari lengkungan jalur VI SGU (ditelusuri titik-per-titik dari
   piksel), kurva 17 jalur SDT di area throat barat/timur adalah kurva
   Bezier kuadratik halus dari ujung badan lurus (akurat) menuju titik
   pertemuan yang diperkirakan dari inspeksi visual gambar per kelompok
   jalur — percobaan pelacakan vektor otomatis penuh (endpoint-matching
   antar objek garis PDF) dicoba tapi tidak cukup andal (banyak
   segmen throat digambar terpisah/tidak presisi bersentuhan di file
   sumbernya, beda dari SGU). Akibatnya bentuk visual jalur di area throat
   MENDEKATI tapi tidak identik dengan gambar asli, dan topologi sambungan
   wesel sesungguhnya (jalur mana yang secara fisik terhubung ke arah Sgu
   vs Sb Kota vs dipo lewat wesel mana) belum dipetakan.
4. **Tidak ada peron.** Sidotopo adalah emplasemen langsir/depo barang,
   bukan stasiun penumpang — semua field peron (`peron_west_x`,
   `peron_length_m`, dst.) bernilai null. KA tidak "berhenti pas peron",
   `stopXFor()` di `simulation.js` fallback ke titik tengah jalur.
5. **Jadwal SDT (105 baris, 12 Agustus 2026).** Sumber data:
   `template_import_jadwal_sdt_12agustus2026.xlsx` (header di baris 1,
   kolom A-K, sebagian baris punya nama KA deskriptif seperti "DINAS LOK
   GAYA BARU MALAM SELATAN" / "LOKOMOTIF COMMUTER LINE DHOHO" — baris
   lain yang tidak dikasih nama pakai nomor KA sebagai fallback nama).
   Semua 105 baris sekarang punya `track_id` dan teranimasi penuh: 78
   baris lewat jalur "through" bernomor (VI/XIII/XIV/XV/XVII), 27 sisanya
   (semua dinas lok/lokomotif kedatangan dari SB tanpa jam berangkat)
   lewat track baru "DIPO LOK" — lihat poin 11.
6. **Kode relasi baru: `KLM` (Kalimas)**, ditambahkan ke Master Data →
   Stasiun karena muncul di jadwal SDT tapi belum ada sebelumnya —
   belum ada data emplasemen sendiri untuknya.
7. **Bug ditemukan & diperbaiki Agustus 2026: `JadwalImporter` tidak
   men-set `station_id`.** Sebelum perbaikan ini, import lewat tombol
   "Import Jadwal (Excel)" di panel admin (dan `php artisan jadwal:import`)
   TIDAK PERNAH mengisi kolom `station_id` pada baris yang dibuat/diupdate
   — akibatnya baris jadwal yang diimport lewat UI tidak tertaut ke
   stasiun manapun dan TIDAK AKAN PERNAH muncul di halaman simulasi mana
   pun (karena `ScheduleController` memfilter jadwal berdasarkan
   `station_id`), meskipun terlihat normal di listing admin. Sudah
   diperbaiki: form Import sekarang mewajibkan pilih **Station**, begitu
   juga form create/edit `TrainScheduleResource` (field baru "Station" di
   paling atas) dan command `jadwal:import` (opsi baru `--stasiun=KODE`,
   wajib diisi). `track_id` pada form/importer sekarang juga dibatasi ke
   jalur milik stasiun yang dipilih (sebelumnya tidak discope per stasiun
   — berisiko salah pilih jalur kalau ada kode jalur yang sama persis di
   dua stasiun, mis. SGU & SDT sama-sama punya jalur "VI").
8. **Bug kedua yang ikut ditemukan di importer yang sama: sel kosong pada
   Excel bisa membuat kolom lain ikut geser.** `JadwalImporter` tadinya
   membaca kolom C-I lewat `getCellIterator()`, yang diam-diam MELOMPATI
   sel yang benar-benar belum pernah ditulisi apa pun (bukan cuma string
   kosong) — kalau mis. kolom "Nama KA" kosong-total pada suatu baris,
   nilai kolom berikutnya (jam datang) ikut kegeser masuk ke slot Nama,
   dst., dan kolom JALUR di ujung jadi hilang. Sudah diperbaiki dengan
   membaca tiap kolom lewat koordinat sel eksplisit.
9. **Batasan skema yang sudah ada sebelumnya, belum diperbaiki:** unique
   constraint `(tanggal, urutan)` pada tabel `train_schedules` bersifat
   GLOBAL, tidak per-stasiun — aman untuk saat ini karena tanggal jadwal
   SGU (15 Juli 2026) dan SDT (12 Agustus 2026) berbeda, tapi kalau suatu
   saat dua stasiun perlu jadwal di tanggal yang sama, constraint ini
   perlu diubah jadi `(station_id, tanggal, urutan)`. `JadwalImporter`
   sekarang setidaknya melindungi diri dari kasus ini (baris yang akan
   menimpa data stasiun LAIN di tanggal+urutan yang sama akan dilewati,
   bukan menimpa diam-diam), tapi belum ada solusi jangka panjangnya.
10. **3 kolom tambahan (Agustus 2026): Gan-Gen, Waktu Tinggal (menit),
    Keterangan.** Sesuai kolom I/J/K pada
    `template_import_jadwal_sdt_12agustus2026.xlsx` (kolom K "Keterangan"
    sebenarnya sudah lama ada sebagai `catatan`, cuma di form admin
    labelnya masih "Notes"). Migration baru
    `add_gan_gen_waktu_tinggal_to_train_schedules_table` menambah kolom
    `gan_gen` (string, nullable) dan `waktu_tinggal_menit` (integer,
    nullable) — **wajib jalankan `php artisan migrate` di server**, bukan
    cuma upload file, supaya kolomnya benar-benar ada di database. Field
    ini muncul di form create/edit `TrainScheduleResource` dan sebagai
    kolom toggleable (tersembunyi default) di tabel listing. `JadwalImporter`
    sekarang juga mendeteksi otomatis kolom ini kalau ada di file Excel
    (header cocok "Gan-Gen" / "Waktu Tinggal" / "Keterangan"); di data
    SDT 12 Agustus 2026 hanya kolom Gan-Gen yang sudah terisi (52 dari 105
    baris, nilainya "GANJIL"/"GENAP"), Waktu Tinggal & Keterangan memang
    masih kosong di file sumber dan perlu dilengkapi manual oleh admin.
    Supaya re-import file yang sama tidak diam-diam menghapus isian manual
    admin, importer HANYA menimpa ketiga kolom ini kalau nilai di file
    tidak kosong — kalau kosong di file, nilai lama yang sudah ada di
    database dibiarkan apa adanya.
11. **Track baru "DIPO LOK" (Agustus 2026).** 27 baris jadwal SDT (semua
    dinas lokomotif kedatangan dari SB tanpa jam berangkat, mis. "DINAS LOK
    GAYA BARU MALAM SELATAN") sebelumnya punya kolom Track kosong di
    template karena belum ada jalur yang cocok didigitisasi — sesuai
    gambar sumber, KA lokomotif ini masuk ke sepur BUNTU (dead-end)
    "Dipo Lok" yang bercabang dari wesel 3 pada throat barat (di antara
    sepur "Dipo kereta" & "Dipo Mekanik" yang belum didigitisasi). Kurva
    track ini diekstrak LANGSUNG dari koordinat bezier vektor PDF (bukan
    perkiraan visual seperti kurva throat 17 jalur lain di poin 3) dan
    dikonfirmasi lewat overlay render PDF resolusi tinggi — lihat
    `TrackSeeder::seedJalurSdtPresisi()` poin 6. Kolom `code` di tabel
    `tracks` diperlebar dari varchar(5) ke varchar(20) lewat migration baru
    `widen_code_column_on_tracks_table` supaya muat "DIPO LOK" — **wajib
    `php artisan migrate`**, sama seperti poin 10. Track ini tidak punya
    KM chainage (di luar sistem KM SGU-BET) dan tidak punya data wesel
    sendiri (wesel 3 belum di-OCR dengan confidence yang layak). Semua 105
    baris jadwal SDT sekarang punya `track_id` dan teranimasi penuh.

Sama seperti SGU, silakan tinjau/koreksi wesel & jalur SDT lewat panel
admin (Master Data → Jalur / Wesel) — terutama nomor wesel hasil OCR dan
bentuk kurva throat, yang tingkat keyakinannya lebih rendah dari SGU.

## Struktur simulasi (untuk pengembangan lanjutan)

Logika utama animasi ada di `public/js/simulation.js` (vanilla JS, tanpa build
step), fungsi `getPhase(row, waktuMenit)` menentukan posisi & fase (masuk /
berhenti / keluar / lewat langsung) tiap baris jadwal pada suatu waktu.
Data dipasok oleh `GET /api/schedule?tanggal=YYYY-MM-DD`
(`App\Http\Controllers\Api\ScheduleController`).

Untuk memperbarui jadwal harian berikutnya, upload file Excel format yang sama
(kolom C–I: No, No KA, Relasi, Nama, DAT, BER, JALUR, header di baris 8) lewat
tombol Import di panel admin.
