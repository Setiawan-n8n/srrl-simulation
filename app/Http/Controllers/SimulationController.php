<?php

namespace App\Http\Controllers;

use App\Models\Station;
use App\Models\TrainSchedule;
use Illuminate\Http\Request;

class SimulationController extends Controller
{
    public function index(Request $request)
    {
        $availableDates = TrainSchedule::query()
            ->selectRaw('DISTINCT tanggal')
            ->orderBy('tanggal')
            ->pluck('tanggal')
            ->map(fn ($d) => $d->format('Y-m-d'));

        $tanggal = $request->query('tanggal', $availableDates->last() ?? now()->format('Y-m-d'));

        $stations = Station::query()->simulasi()->get(['code', 'name']);

        $stasiun = $request->query('stasiun', 'SGU');
        if (! $stations->contains('code', $stasiun)) {
            $stasiun = $stations->first()->code ?? 'SGU';
        }

        return view('simulation.index', [
            'tanggal' => $tanggal,
            'availableDates' => $availableDates,
            'stations' => $stations,
            'stasiun' => $stasiun,
        ]);
    }
}
