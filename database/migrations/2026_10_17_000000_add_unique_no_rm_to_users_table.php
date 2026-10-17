<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nomor rekam medis harus unik. Kolom tetap nullable: admin dan dokter tidak punya no_rm,
     * dan beberapa nilai NULL diperbolehkan oleh unique index.
     *
     * Tidak mengubah atau menghapus data. Jika sudah ada nilai kembar, migration berhenti
     * sebelum mengubah skema agar data dirapikan secara manual terlebih dahulu.
     */
    public function up(): void
    {
        $kembar = DB::table('users')
            ->select('no_rm', DB::raw('COUNT(*) as jumlah'))
            ->whereNotNull('no_rm')
            ->groupBy('no_rm')
            ->having('jumlah', '>', 1)
            ->pluck('jumlah', 'no_rm');

        if ($kembar->isNotEmpty()) {
            throw new RuntimeException(
                'Migration dibatalkan: ada no_rm kembar (' . $kembar->keys()->implode(', ') . '). '
                . 'Rapikan data tersebut secara manual lalu jalankan ulang.'
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('no_rm', 'users_no_rm_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_no_rm_unique');
        });
    }
};
