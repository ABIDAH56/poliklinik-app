<?php

namespace Database\Seeders;

use App\Models\Obat;
use Illuminate\Database\Seeder;

class ObatSeeder extends Seeder
{
    /**
     * Data contoh obat. Memakai firstOrCreate berdasarkan nama_obat,
     * jadi aman dijalankan berulang kali (tidak membuat data ganda).
     */
    public function run(): void
    {
        $obats = [
            ['nama_obat' => 'Paracetamol 500mg', 'kemasan' => 'strip', 'harga' => 5000,  'stok' => 100],
            ['nama_obat' => 'Amoxicillin 500mg', 'kemasan' => 'strip', 'harga' => 12000, 'stok' => 50],
            ['nama_obat' => 'OBH Combi 100ml',   'kemasan' => 'botol', 'harga' => 18000, 'stok' => 30],
            ['nama_obat' => 'Vitamin C 50mg',    'kemasan' => 'box',   'harga' => 25000, 'stok' => 8],
        ];

        foreach ($obats as $obat) {
            Obat::firstOrCreate(['nama_obat' => $obat['nama_obat']], $obat);
        }
    }
}
