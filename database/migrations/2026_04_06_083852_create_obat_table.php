<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obat', function (Blueprint $table) {
            $table->id();
            $table->string('nama_obat', 255);
            $table->string('kemasan', 35)->nullable();
            $table->integer('harga');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obat');
    }
};