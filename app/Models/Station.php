<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Station extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'side',
        'is_own_station',
        'km_position',
        'arah_barat_label',
        'arah_timur_label',
        'sort_order',
        'diagram_svg_path',
        'diagram_viewbox',
        'keterangan',
    ];

    protected $casts = [
        'is_own_station' => 'boolean',
    ];

    public function scheduleAsOrigin()
    {
        return $this->hasMany(TrainSchedule::class, 'relasi_asal_id');
    }

    public function scheduleAsDestination()
    {
        return $this->hasMany(TrainSchedule::class, 'relasi_tujuan_id');
    }

    /**
     * Jalur/sinyal/wesel/jadwal milik emplasemen stasiun ini (kalau
     * is_own_station = true).
     */
    public function tracks()
    {
        return $this->hasMany(Track::class);
    }

    public function signals()
    {
        return $this->hasMany(Signal::class);
    }

    public function wesels()
    {
        return $this->hasMany(Wesel::class);
    }

    public function trackAdjacencies()
    {
        return $this->hasMany(TrackAdjacency::class);
    }

    public function schedules()
    {
        return $this->hasMany(TrainSchedule::class);
    }

    public function scopeSimulasi($query)
    {
        return $query->where('is_own_station', true)->orderBy('sort_order');
    }
}
