<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JadwalPeriksa extends Model
{
    protected $table = 'jadwal_periksa';

    protected $fillable = [
        'id_dokter',
        'hari',
        'jam_mulai',
        'jam_selesai',
    ];

    // Urutan hari yang didukung aplikasi (sama dengan enum kolom hari).
    public const HARI = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

    // Urut menurut hari (Senin..Minggu), lalu jam mulai. Bukan urutan alfabet.
    public function scopeUrutHari($query)
    {
        $daftar = implode(',', array_map(fn ($h) => "'" . $h . "'", self::HARI));
        return $query->orderByRaw("FIELD(hari, {$daftar})")->orderBy('jam_mulai');
    }

    // Jadwal yang bisa didaftarkan pasien: dokternya ada dan sudah ditugaskan ke sebuah poli.
    public function scopeTersedia($query)
    {
        return $query->whereHas('dokter', fn ($q) => $q->where('role', 'dokter')->whereNotNull('id_poli'))
            ->urutHari();
    }

    public function dokter()
    {
        return $this->belongsTo(User::class, 'id_dokter');
    }

    public function daftarPolis()
    {
        return $this->hasMany(DaftarPoli::class, 'id_jadwal');
    }
}
