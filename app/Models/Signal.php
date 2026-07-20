<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Signal extends Model
{
    use HasFactory;

    protected $fillable = [
        'station_id',
        'code',
        'track_id',
        'side',
        'jenis',
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

    public function track()
    {
        return $this->belongsTo(Track::class);
    }
}
