<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores collisions the client-side simulation detects: two different
     * trains rendered on the same track at overlapping positions at the
     * same simulated clock time (see detectCollisions() in
     * public/js/simulation.js). This is a *simulation rendering* conflict
     * — usually caused by the schedule data assigning two services to the
     * same track with overlapping dwell windows — not a real-world
     * incident, but it needs a dispatcher/schedule correction.
     */
    public function up(): void
    {
        Schema::create('accident_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->nullable()->constrained('stations')->nullOnDelete();
            $table->date('tanggal')->comment('Schedule date the simulation was showing when detected');
            $table->string('clock_time', 5)->comment('Simulated clock time HH:MM the collision was detected at');
            $table->foreignId('track_id')->nullable()->constrained('tracks')->nullOnDelete();
            $table->string('track_code', 10)->nullable();
            $table->decimal('km_position', 7, 3)->nullable();
            $table->string('train_a_no_ka', 30)->nullable();
            $table->string('train_a_nama')->nullable();
            $table->string('train_b_no_ka', 30)->nullable();
            $table->string('train_b_nama')->nullable();
            $table->text('detail')->nullable();
            $table->timestamp('detected_at')->nullable()->comment('Browser-side wall-clock timestamp when the collision was flagged');
            $table->timestamps();

            $table->index(['tanggal', 'track_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accident_logs');
    }
};
