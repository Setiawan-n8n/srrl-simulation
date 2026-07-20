<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccidentLog;
use App\Models\Station;
use App\Models\Track;
use Illuminate\Http\Request;

class AccidentLogController extends Controller
{
    /**
     * Persists a collision the simulation detected client-side (see
     * findCollision()/reportCollision() in public/js/simulation.js). Two
     * kinds of events are logged here: (1) two different trains occupying
     * the SAME track with overlapping positions ('track' type -- pauses
     * the simulation), and (2) two trains on DIFFERENT tracks converging
     * on the same switch-ladder side at the same time ('throat' type --
     * advisory only, does not pause; track_code for this kind looks like
     * "V/VI"). Public endpoint — the simulation page itself has no auth —
     * so this only ever writes a log row, nothing else.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'stasiun' => 'nullable|string|max:10',
            'tanggal' => 'required|date',
            'clock_time' => 'required|string|max:5',
            'track_code' => 'nullable|string|max:10',
            'km_position' => 'nullable|numeric',
            'train_a_no_ka' => 'nullable|string|max:30',
            'train_a_nama' => 'nullable|string|max:255',
            'train_b_no_ka' => 'nullable|string|max:30',
            'train_b_nama' => 'nullable|string|max:255',
            'detail' => 'nullable|string',
        ]);

        $station = ! empty($data['stasiun'])
            ? Station::query()->where('code', $data['stasiun'])->first()
            : null;

        $track = ($station && ! empty($data['track_code']))
            ? Track::query()->where('station_id', $station->id)->where('code', $data['track_code'])->first()
            : null;

        $log = AccidentLog::query()->create([
            'station_id' => $station?->id,
            'tanggal' => $data['tanggal'],
            'clock_time' => $data['clock_time'],
            'track_id' => $track?->id,
            'track_code' => $data['track_code'] ?? null,
            'km_position' => $data['km_position'] ?? null,
            'train_a_no_ka' => $data['train_a_no_ka'] ?? null,
            'train_a_nama' => $data['train_a_nama'] ?? null,
            'train_b_no_ka' => $data['train_b_no_ka'] ?? null,
            'train_b_nama' => $data['train_b_nama'] ?? null,
            'detail' => $data['detail'] ?? null,
            'detected_at' => now(),
        ]);

        return response()->json(['id' => $log->id], 201);
    }
}
