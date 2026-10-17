<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daftar_poli', function (Blueprint $table) {
            $table->id();
            // Menghubungkan ke tabel users (untuk Pasien)
            $table->foreignId('id_pasien')->constrained('users')->cascadeOnDelete();
            // Menghubungkan ke tabel jadwal_periksa
            $table->foreignId('id_jadwal')->constrained('jadwal_periksa')->cascadeOnDelete();
            $table->text('keluhan');
            $table->integer('no_antrian');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daftar_poli');
    }
};