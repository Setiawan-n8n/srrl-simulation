<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            StationSeeder::class,
            TrackSeeder::class,
            SignalWeselSeeder::class,
            TrackAdjacencySeeder::class,
            TrainScheduleSeeder::class,
        ]);
    }
}
