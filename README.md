# Simulasi Perjalanan KA — Stasiun Surabaya Gubeng (SGU)

Aplikasi web Laravel + Filament untuk mensimulasikan perjalanan KA keluar-masuk
Stasiun Surabaya Gubeng (SGU) berdasarkan jadwal harian, dengan panel admin
untuk mengelola seluruh data (jadwal, KA, jalur, sinyal, wesel, stasiun relasi).

Dibangun untuk SRRL Project, berdasarkan data:
- `JADWAL KA SGU UPDATE 15 JULI 2026.xlsx` (304 baris jadwal — sudah di-seed otomatis)
- `Gambar Emplasemen Stasiun SGU.pdf` (denah emplasemen 6 jalur, Sintelis Daop 8, Juni 2017)

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
- 6 jalur emplasemen (I–VI)
- Sinyal & wesel dasar (representasi disederhanakan dari PDF emplasemen)
- 25 stasiun/relasi yang muncul di jadwal (dengan arah barat/timur)
- 304 baris jadwal KA tanggal 15 Juli 2026, sudah dipetakan ke Train master data
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

## Struktur simulasi (untuk pengembangan lanjutan)

Logika utama animasi ada di `public/js/simulation.js` (vanilla JS, tanpa build
step), fungsi `getPhase(row, waktuMenit)` menentukan posisi & fase (masuk /
berhenti / keluar / lewat langsung) tiap baris jadwal pada suatu waktu.
Data dipasok oleh `GET /api/schedule?tanggal=YYYY-MM-DD`
(`App\Http\Controllers\Api\ScheduleController`).

Untuk memperbarui jadwal harian berikutnya, upload file Excel format yang sama
(kolom C–I: No, No KA, Relasi, Nama, DAT, BER, JALUR, header di baris 8) lewat
tombol Import di panel admin.
