<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccidentLog extends Model
{
    protected $fillable = [
        'station_id',
        'tanggal',
        'clock_time',
        'track_id',
        'track_code',
        'km_position',
        'train_a_no_ka',
        'train_a_nama',
        'train_b_no_ka',
        'train_b_nama',
        'detail',
        'detected_at',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'detected_at' => 'datetime',
        'km_position' => 'float',
    ];

    public function station()
    {
        return $this->belongsTo(Station::class);
    }

    public function track()
    {
        return $this->belongsTo(Track::class);
    }
}
