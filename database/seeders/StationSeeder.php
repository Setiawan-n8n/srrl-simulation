<?php

namespace Database\Seeders;

use App\Models\Station;
use Illuminate\Database\Seeder;

class StationSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/stations.json');
        $stations = json_decode(file_get_contents($path), true);

        foreach ($stations as $s) {
            Station::query()->updateOrCreate(
                ['code' => $s['code']],
                [
                    'name' => $s['name'],
                    'side' => $s['side'],
                    'is_own_station' => $s['is_own_station'],
                    'km_position' => $s['km_position'] ?? null,
                    'arah_barat_label' => $s['arah_barat_label'] ?? null,
                    'arah_timur_label' => $s['arah_timur_label'] ?? null,
                    'sort_order' => $s['sort_order'] ?? 0,
                    'diagram_svg_path' => $s['diagram_svg_path'] ?? null,
                    'diagram_viewbox' => $s['diagram_viewbox'] ?? null,
                    'keterangan' => $s['keterangan'] ?: null,
                ]
            );
        }
    }
}
