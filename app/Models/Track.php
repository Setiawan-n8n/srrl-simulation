<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Track extends Model
{
    use HasFactory;

    protected $fillable = [
        'station_id',
        'code',
        'name',
        'jenis',
        'sort_order',
        'diagram_path',
        'km_start',
        'km_end',
        'peron_km_start',
        'peron_km_end',
        'panjang_jalur_m',
        'panjang_peron_m',
        'keterangan',
    ];

    protected $casts = [
        'diagram_path' => 'array',
        'km_start' => 'decimal:3',
        'km_end' => 'decimal:3',
        'peron_km_start' => 'decimal:3',
        'peron_km_end' => 'decimal:3',
        'panjang_jalur_m' => 'decimal:1',
        'panjang_peron_m' => 'decimal:1',
    ];

    public function station()
    {
        return $this->belongsTo(Station::class);
    }

    public function signals()
    {
        return $this->hasMany(Signal::class);
    }

    public function schedules()
    {
        return $this->hasMany(TrainSchedule::class);
    }
}
