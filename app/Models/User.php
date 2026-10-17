<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

   class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nama',
        'alamat',
        'no_ktp',
        'no_hp',
        'no_rm',
        'role',
        'id_poli',
        'email',
        'password',

    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function poli()
    {
        return $this->belongsTo(Poli::class, 'id_poli');
    }

    public function jadwalPeriksa()
    {
        return $this->hasMany(JadwalPeriksa::class, 'id_dokter');
    }

    // Pendaftaran milik pasien ini (riwayat pemeriksaan menempel pada pendaftaran).
    public function daftarPolis()
    {
        return $this->hasMany(DaftarPoli::class, 'id_pasien');
    }

    // Pendaftaran pasien pada jadwal dokter ini.
    public function antreanPasien()
    {
        return $this->hasManyThrough(DaftarPoli::class, JadwalPeriksa::class, 'id_dokter', 'id_jadwal');
    }

    // Format nomor rekam medis: YYYYMM-NNN, NNN = id pasien (nol di depan hingga 3 digit).
    // Id unik, sehingga nomor tidak mungkin bentrok walau dua pasien dibuat bersamaan.
    public static function formatNoRm(int $id): string
    {
        return date('Ym') . '-' . str_pad((string) $id, 3, '0', STR_PAD_LEFT);
    }

    // Satu-satunya jalur pembuatan pasien baru: role dipaksa 'pasien' dan no_rm dibuat otomatis.
    public static function buatPasien(array $data): self
    {
        return DB::transaction(function () use ($data) {
            $pasien = static::create(array_merge($data, ['role' => 'pasien', 'no_rm' => null]));
            $pasien->update(['no_rm' => static::formatNoRm($pasien->id)]);

            return $pasien;
        });
    }

}
