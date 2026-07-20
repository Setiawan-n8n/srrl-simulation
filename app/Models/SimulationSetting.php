<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimulationSetting extends Model
{
    protected $fillable = [
        'approach_minutes',
        'dwell_static_minutes',
        'arrival_only_dwell_minutes',
    ];

    protected $casts = [
        'approach_minutes' => 'integer',
        'dwell_static_minutes' => 'integer',
        'arrival_only_dwell_minutes' => 'integer',
    ];

    /**
     * Singleton row (there is only ever one). The 2026_07_19_100002
     * migration already seeds it, but firstOrCreate() here is a safety net
     * in case that row was ever removed manually.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'approach_minutes' => 4,
            'dwell_static_minutes' => 3,
            'arrival_only_dwell_minutes' => 15,
        ]);
    }
}
