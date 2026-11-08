<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Obat extends Model
{
    protected $table = 'obat';

    protected $fillable = [
        'nama_obat',
        'kemasan',
        'harga',
        'stok',
    ];

    protected $casts = [
        'harga' => 'integer',
        'stok'  => 'integer',
    ];

    /**
     * Relasi one-to-many: satu obat bisa dipakai di banyak resep (tabel detail_periksa, kolom id_obat).
     * Kebalikannya ada di DetailPeriksa::obat() (belongsTo).
     */
    public function detailPeriksas()
    {
        return $this->hasMany(DetailPeriksa::class, 'id_obat');
    }

    public function isStokHabis(): bool
    {
        return $this->stok <= 0;
    }

    public function isStokMenipis(): bool
    {
        return $this->stok > 0 && $this->stok <= 10;
    }
}