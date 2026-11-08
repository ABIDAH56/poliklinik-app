<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Memperketat aturan tabel obat langsung di level database (UTS CRUD Obat):
 * - obat.kemasan wajib diisi (NOT NULL)
 * - obat.harga tidak boleh negatif (UNSIGNED)
 * - detail_periksa.id_obat: ON DELETE CASCADE diganti RESTRICT, supaya obat yang sudah
 *   diresepkan tidak bisa dihapus dan riwayat pemeriksaan tidak ikut hilang.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Data lama tanpa kemasan diisi '-' agar perubahan ke NOT NULL tidak gagal.
        DB::table('obat')->whereNull('kemasan')->update(['kemasan' => '-']);

        Schema::table('obat', function (Blueprint $table) {
            $table->string('kemasan', 35)->nullable(false)->change();
            $table->unsignedInteger('harga')->change();
        });

        // Relasi: satu obat dipakai di banyak detail_periksa (resep). RESTRICT = tolak hapus obat yang masih dirujuk.
        Schema::table('detail_periksa', function (Blueprint $table) {
            $table->dropForeign(['id_obat']);
            $table->foreign('id_obat')->references('id')->on('obat')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('detail_periksa', function (Blueprint $table) {
            $table->dropForeign(['id_obat']);
            $table->foreign('id_obat')->references('id')->on('obat')->cascadeOnDelete();
        });

        Schema::table('obat', function (Blueprint $table) {
            $table->string('kemasan', 35)->nullable()->change();
            $table->integer('harga')->change();
        });
    }
};
