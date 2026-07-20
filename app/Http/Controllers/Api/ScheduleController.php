<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Signal;
use App\Models\SimulationSetting;
use App\Models\Station;
use App\Models\Track;
use App\Models\TrackAdjacency;
use App\Models\TrainSchedule;
use App\Models\Wesel;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    /**
     * Mengembalikan seluruh data yang dibutuhkan halaman simulasi untuk satu
     * stasiun (emplasemen) & satu tanggal: jalur, sinyal, wesel, stasiun
     * relasi, dan jadwal KA (kalau ada).
     */
    public function index(Request $request)
    {
        $stasiunCode = $request->query('stasiun', 'SGU');

        $stasiun = Station::query()->simulasi()->where('code', $stasiunCode)->first()
            ?? Station::query()->simulasi()->where('code', 'SGU')->first();

        $daftarStasiun = Station::query()->simulasi()->get(['code', 'name', 'sort_order']);

        if (! $stasiun) {
            return response()->json([
                'error' => 'Belum ada data stasiun simulasi. Jalankan migrasi & seeder.',
                'daftar_stasiun' => $daftarStasiun,
            ], 404);
        }

        $punyaJadwal = TrainSchedule::query()->where('station_id', $stasiun->id)->exists();

        $tanggal = $request->query('tanggal');

        if (! $tanggal) {
            $tanggal = TrainSchedule::query()->where('station_id', $stasiun->id)->max('tanggal');
        }

        $tracks = Track::query()->where('station_id', $stasiun->id)->orderBy('sort_order')->get([
            'id', 'code', 'name', 'jenis', 'diagram_path',
            'km_start', 'km_end', 'peron_km_start', 'peron_km_end', 'panjang_jalur_m', 'panjang_peron_m',
        ]);

        $signals = Signal::query()->where('station_id', $stasiun->id)->get(['id', 'code', 'track_id', 'side', 'jenis', 'posisi_km', 'pos_x', 'pos_y']);

        $wesels = Wesel::query()->where('station_id', $stasiun->id)->get(['id', 'code', 'track_from_id', 'track_to_id', 'side', 'posisi_km', 'pos_x', 'pos_y']);

        // Pasangan jalur yang bertetangga langsung di ladder wesel (lihat
        // TrackAdjacencySeeder) -- dipakai simulasi utk deteksi "konflik
        // ladder wesel" (dua jalur peron berbeda, tapi berbagi
        // persimpangan fisik yang sama di sisi barat/timur yang sama).
        $trackAdjacencies = TrackAdjacency::query()
            ->where('station_id', $stasiun->id)
            ->with(['trackA:id,code', 'trackB:id,code'])
            ->get()
            ->filter(fn (TrackAdjacency $a) => $a->trackA && $a->trackB)
            ->map(fn (TrackAdjacency $a) => [
                'track_a' => $a->trackA->code,
                'track_b' => $a->trackB->code,
                'side' => $a->side,
            ])
            ->values();

        $stations = Station::query()->get(['id', 'code', 'name', 'side', 'is_own_station']);

        $jadwal = TrainSchedule::query()
            ->where('station_id', $stasiun->id)
            ->with(['asal:id,code,name,side', 'tujuan:id,code,name,side', 'track:id,code,name', 'train:id,no_ka,nama,kategori'])
            ->when($tanggal, fn ($q) => $q->whereDate('tanggal', $tanggal))
            ->orderBy('urutan')
            ->get()
            ->map(function (TrainSchedule $s) {
                return [
                    'id' => $s->id,
                    'urutan' => $s->urutan,
                    'no_ka' => $s->no_ka,
                    'nama_ka' => $s->nama_ka,
                    'kategori' => $s->train?->kategori ?? 'lainnya',
                    'relasi_raw' => $s->relasi_raw,
                    'asal' => $s->asal ? ['code' => $s->asal->code, 'name' => $s->asal->name, 'side' => $s->asal->side] : null,
                    'tujuan' => $s->tujuan ? ['code' => $s->tujuan->code, 'name' => $s->tujuan->name, 'side' => $s->tujuan->side] : null,
                    'jam_datang' => optional($s->jam_datang)->format('H:i'),
                    'jam_datang_ket' => $s->jam_datang_ket,
                    'jam_berangkat' => optional($s->jam_berangkat)->format('H:i'),
                    'jam_berangkat_ket' => $s->jam_berangkat_ket,
                    'track' => $s->track ? ['code' => $s->track->code, 'name' => $s->track->name] : null,
                ];
            });

        $settings = SimulationSetting::current();

        return response()->json([
            'stasiun' => [
                'code' => $stasiun->code,
                'name' => $stasiun->name,
                'km_position' => $stasiun->km_position,
                'arah_barat_label' => $stasiun->arah_barat_label,
                'arah_timur_label' => $stasiun->arah_timur_label,
                'diagram_svg_path' => $stasiun->diagram_svg_path,
                'diagram_viewbox' => $stasiun->diagram_viewbox,
            ],
            'daftar_stasiun' => $daftarStasiun,
            'punya_jadwal' => $punyaJadwal,
            'tanggal' => $tanggal,
            'tracks' => $tracks,
            'signals' => $signals,
            'wesels' => $wesels,
            'track_adjacencies' => $trackAdjacencies,
            'stations' => $stations,
            'jadwal' => $jadwal,
            // Parameter waktu animasi, dapat diatur lewat admin panel
            // (App\Filament\Pages\SimulationSettings) alih-alih di-hardcode
            // di simulation.js -- lihat komentar pada model & migrasinya.
            'settings' => [
                'approach_minutes' => $settings->approach_minutes,
                'dwell_static_minutes' => $settings->dwell_static_minutes,
                'arrival_only_dwell_minutes' => $settings->arrival_only_dwell_minutes,
            ],
        ]);
    }
}
