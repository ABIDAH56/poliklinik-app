<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detail_periksa', function (Blueprint $table) {
            $table->unsignedInteger('harga')->default(0)->after('id_obat');
        });

        // Data lama: isi dengan harga obat saat ini (harga asli saat pemeriksaan tidak tercatat).
        // Subquery berkorelasi agar portabel (MySQL dan SQLite).
        DB::statement('UPDATE detail_periksa SET harga = (SELECT obat.harga FROM obat WHERE obat.id = detail_periksa.id_obat)');
    }

    public function down(): void
    {
        Schema::table('detail_periksa', function (Blueprint $table) {
            $table->dropColumn('harga');
        });
    }
};
