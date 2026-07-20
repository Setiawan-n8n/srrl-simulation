@extends('layouts.app')

@section('title', 'Train Journey Simulation - SRRL Region')

@section('content')
<div class="app-shell">
    <header class="topbar">
        <div class="topbar-title">
            <span class="topbar-kicker">SRRL Project</span>
            <h1 id="pageTitle">Train Journey Simulation</h1>
        </div>
        <div class="topbar-actions">
            <label class="field-inline">
                <span>Date</span>
                <select id="tanggalSelect">
                    @forelse($availableDates as $d)
                        <option value="{{ $d }}" @selected($d === $tanggal)>{{ \Carbon\Carbon::parse($d)->locale('en')->translatedFormat('d F Y') }}</option>
                    @empty
                        <option value="{{ $tanggal }}">{{ $tanggal }}</option>
                    @endforelse
                </select>
            </label>
            <a href="{{ url('/admin') }}" class="btn btn-ghost" target="_blank" rel="noopener">Admin Panel &rarr;</a>
        </div>
    </header>

    <main class="stage">
        <section class="board-wrap">
            <div class="board-toolbar">
                <button id="btnPlay" class="btn btn-primary" type="button">&#9654; Start</button>
                <button id="btnReset" class="btn btn-ghost" type="button">&#8635; Reset</button>

                <label class="field-inline">
                    <span>Speed</span>
                    <select id="speedSelect">
                        <option value="1" selected>1x</option>
                        <option value="5">5x</option>
                        <option value="15">15x</option>
                        <option value="30">30x</option>
                        <option value="60">60x</option>
                        <option value="120">120x</option>
                    </select>
                </label>

                <div class="clock" id="clockReadout">00:00</div>

                <input type="range" id="timeSlider" min="0" max="1439" value="0" step="1" class="time-slider">

                <div class="zoom-controls">
                    <button id="btnZoomOut" class="btn btn-ghost" type="button" title="Zoom out">&minus;</button>
                    <span id="zoomReadout" class="zoom-readout">100%</span>
                    <button id="btnZoomIn" class="btn btn-ghost" type="button" title="Zoom in">+</button>
                    <button id="btnZoomReset" class="btn btn-ghost" type="button" title="Reset zoom">Reset</button>
                </div>
            </div>

            <p id="noJadwalNotice" class="notice-no-jadwal" hidden>
                Train schedule for this station is not yet available &mdash; the yard layout below is shown
                statically without train animation.
            </p>

            <div class="board-canvas" id="boardCanvas">
                <svg id="stationSvg" viewBox="0 0 1200 520" preserveAspectRatio="xMidYMid meet"></svg>
            </div>
            <div id="kmTooltip" class="km-tooltip" hidden></div>

            <div class="board-legend">
                <span class="legend-item"><i class="dot dot-penumpang"></i> Passenger</span>
                <span class="legend-item"><i class="dot dot-komuter"></i> Commuter</span>
                <span class="legend-item"><i class="dot dot-barang"></i> Freight</span>
                <span class="legend-item"><i class="dot dot-dinas"></i> Service/Shunting</span>
                <span class="legend-item"><i class="sig sig-hijau"></i> Signal Clear</span>
                <span class="legend-item"><i class="sig sig-merah"></i> Signal Danger</span>
            </div>
        </section>

        <aside class="side-panel">
            <h2>Currently At Station</h2>
            <div id="currentTrains" class="train-list">
                <p class="muted">Waiting for simulation to start&hellip;</p>
            </div>

            <h2>Upcoming Schedule</h2>
            <div id="upcomingTrains" class="train-list"></div>
        </aside>
    </main>

    <nav class="station-tabs">
        @foreach($stations as $s)
            <a href="{{ url('/') }}?stasiun={{ $s->code }}"
               class="station-tab @if($s->code === $stasiun) active @endif">{{ $s->name }}</a>
        @endforeach
    </nav>

    <div id="collisionModal" class="collision-overlay" hidden>
        <div class="collision-box" role="alertdialog" aria-labelledby="collisionModalTitle">
            <div class="collision-icon">&#9888;</div>
            <h2 id="collisionModalTitle">Train Collision Detected</h2>
            <p id="collisionModalMessage"></p>
            <p class="collision-hint">
                The simulation has been paused. This usually means the schedule assigns two
                services to the same track with overlapping dwell times &mdash; check and correct
                the schedule data before resuming.
            </p>
            <div class="collision-actions">
                <button id="btnCollisionDismiss" class="btn btn-primary" type="button">Understood, keep paused</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    window.SIMULATION_CONFIG = {
        apiUrl: @json(url('/api/schedule')),
        accidentLogUrl: @json(url('/api/accident-logs')),
        assetBase: @json(url('/')),
        tanggal: @json($tanggal),
        stasiun: @json($stasiun),
    };
</script>
<script src="{{ asset('js/simulation.js') }}"></script>
@endpush
