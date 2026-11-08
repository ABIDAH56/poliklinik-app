<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi form tambah & edit obat (dipakai oleh ObatController@store dan @update).
 * Jika validasi gagal, Laravel otomatis kembali ke form dengan pesan error dan input lama (old()).
 */
class ObatRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Hak akses sudah dijaga middleware route (auth + role:admin).
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_obat' => 'required|string|max:255',       // wajib
            'kemasan'   => 'required|string|max:35',        // wajib, mis. strip / botol / box
            'harga'     => 'required|integer|min:0|max:4294967295', // angka bulat, tidak boleh negatif (kolom UNSIGNED INT)
            'stok'      => 'required|integer|min:0',        // stok tidak boleh negatif
        ];
    }

    public function attributes(): array
    {
        return [
            'nama_obat' => 'nama obat',
            'kemasan'   => 'kemasan',
            'harga'     => 'harga',
            'stok'      => 'stok',
        ];
    }
}
