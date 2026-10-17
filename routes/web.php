<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\PoliController as AdminPoliController;
use App\Http\Controllers\Pasien\PoliController as PasienPoliController;
use App\Http\Controllers\Admin\DokterController;
use App\Http\Controllers\Admin\PasienController;
use App\Http\Controllers\Admin\ObatController;
use App\Http\Controllers\Dokter\JadwalPeriksaController;
use App\Http\Controllers\Dokter\PeriksaPasienController;
use App\Http\Controllers\Dokter\RiwayatPasienController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegister']);
Route::post('/register', [AuthController::class, 'register'])->name('register');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', function () {
        return view('admin.dashboard');
    })->name('admin.dashboard');

    Route::resource('polis', AdminPoliController::class);
    Route::resource('dokter', DokterController::class);
    Route::resource('pasien', PasienController::class);
    Route::resource('obat', ObatController::class);
    Route::post('obat/{id}/tambah-stok', [ObatController::class, 'tambahStok'])->name('obat.tambahStok');
    Route::post('obat/{id}/kurangi-stok', [ObatController::class, 'kurangiStok'])->name('obat.kurangiStok');
});

Route::middleware(['auth', 'role:dokter'])->prefix('dokter')->group(function () {
    Route::get('/dashboard', function () {
        return view('dokter.dashboard');
    })->name('dokter.dashboard');
    Route::resource('jadwal-periksa', JadwalPeriksaController::class)->except('show');

    Route::get('/periksa-pasien', [PeriksaPasienController::class, 'index'])->name('periksa-pasien.index');
    Route::post('/periksa-pasien', [PeriksaPasienController::class, 'store'])->name('periksa-pasien.store');
    Route::get('/periksa-pasien/{id}', [PeriksaPasienController::class, 'create'])->name('periksa-pasien.create');

    Route::get('/riwayat-pasien', [RiwayatPasienController::class, 'index'])->name('riwayat-pasien.index');
    Route::get('/riwayat-pasien/{id}', [RiwayatPasienController::class, 'show'])->name('riwayat-pasien.show');

});

Route::middleware(['auth', 'role:pasien'])->prefix('pasien')->group(function () {
    Route::get('/dashboard', function () {
        $jadwals = \App\Models\JadwalPeriksa::tersedia()->with('dokter.poli')->get();
        return view('pasien.dashboard', compact('jadwals'));
    })->name('pasien.dashboard');

    Route::get('/daftar', [PasienPoliController::class, 'get'])->name('pasien.daftar');
    Route::post('/daftar', [PasienPoliController::class, 'submit'])->name('pasien.daftar.submit');
});