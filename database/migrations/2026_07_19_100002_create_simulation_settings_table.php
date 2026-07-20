<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Singleton settings row (always id=1) for the timing assumptions the
     * simulation uses to animate trains -- see public/js/simulation.js.
     * Editable from the admin panel (App\Filament\Pages\SimulationSettings)
     * instead of being hardcoded, so an operator can tune them without a
     * code change if they don't match real operating conditions.
     */
    public function up(): void
    {
        Schema::create('simulation_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('approach_minutes')->default(4)
                ->comment('Minutes the entry/exit animation takes before arrival and after departure');
            $table->unsignedInteger('dwell_static_minutes')->default(3)
                ->comment('For departure-only rows: minutes before departure the train appears already parked');
            $table->unsignedInteger('arrival_only_dwell_minutes')->default(15)
                ->comment('Fallback: minutes an arrival-only row stays visible when no matching onward/shunting departure row is found on the same track. Default of 15 (not e.g. 45) is based on measuring real linked arrival->departure gaps in the SGU 2026-07-15 schedule, which are all <=30 minutes.');
            $table->timestamps();
        });

        DB::table('simulation_settings')->insert([
            'approach_minutes' => 4,
            'dwell_static_minutes' => 3,
            'arrival_only_dwell_minutes' => 15,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('simulation_settings');
    }
};
