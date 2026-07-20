<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'station_id',
        'tanggal',
        'urutan',
        'train_id',
        'no_ka',
        'nama_ka',
        'relasi_asal_id',
        'relasi_tujuan_id',
        'relasi_raw',
        'jam_datang',
        'jam_datang_ket',
        'jam_berangkat',
        'jam_berangkat_ket',
        'track_id',
        'catatan',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'jam_datang' => 'datetime:H:i',
        'jam_berangkat' => 'datetime:H:i',
    ];

    public function station()
    {
        return $this->belongsTo(Station::class);
    }

    public function train()
    {
        return $this->belongsTo(Train::class);
    }

    public function track()
    {
        return $this->belongsTo(Track::class);
    }

    public function asal()
    {
        return $this->belongsTo(Station::class, 'relasi_asal_id');
    }

    public function tujuan()
    {
        return $this->belongsTo(Station::class, 'relasi_tujuan_id');
    }
}
