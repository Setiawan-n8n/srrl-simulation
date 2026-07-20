<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackAdjacency extends Model
{
    protected $fillable = [
        'station_id',
        'track_a_id',
        'track_b_id',
        'side',
        'source_note',
    ];

    public function station()
    {
        return $this->belongsTo(Station::class);
    }

    public function trackA()
    {
        return $this->belongsTo(Track::class, 'track_a_id');
    }

    public function trackB()
    {
        return $this->belongsTo(Track::class, 'track_b_id');
    }
}
