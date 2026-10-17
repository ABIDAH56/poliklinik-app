<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daftar_poli', function (Blueprint $table) {
            $table->integer('no_antrian')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('daftar_poli', function (Blueprint $table) {
            $table->integer('no_antrian')->change();
        });
    }
};