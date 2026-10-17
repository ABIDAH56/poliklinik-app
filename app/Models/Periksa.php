<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Periksa extends Model
{
    protected $table = 'periksa';

    protected $fillable = [
        'id_daftar_poli',
        'tgl_periksa',
        'catatan',
        'biaya_periksa',
    ];

    // Waktu pemeriksaan untuk ditampilkan (WIB). Nilai di database tidak diubah.
    public function getTglPeriksaLokalAttribute(): Carbon
    {
        return Carbon::parse($this->attributes['tgl_periksa'], config('app.timezone'))
            ->setTimezone(config('app.display_timezone'));
    }

    public function daftarPoli()
    {
        return $this->belongsTo(DaftarPoli::class, 'id_daftar_poli');
    }

    public function detailPeriksas()
    {
        return $this->hasMany(DetailPeriksa::class, 'id_periksa');
    }
}
