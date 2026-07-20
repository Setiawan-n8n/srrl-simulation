<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wesel extends Model
{
    use HasFactory;

    protected $fillable = [
        'station_id',
        'code',
        'track_from_id',
        'track_to_id',
        'side',
        'posisi_km',
        'pos_x',
        'pos_y',
        'keterangan',
    ];

    protected $casts = [
        'posisi_km' => 'float',
    ];

    public function station()
    {
        return $this->belongsTo(Station::class);
    }

    public function trackFrom()
    {
        return $this->belongsTo(Track::class, 'track_from_id');
    }

    public function trackTo()
    {
        return $this->belongsTo(Track::class, 'track_to_id');
    }
}
