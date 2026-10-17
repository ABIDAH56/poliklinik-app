<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Console\Prohibitable;
use Illuminate\Database\Console\Migrations\FreshCommand;
use Illuminate\Database\Console\Migrations\RefreshCommand;
use Illuminate\Database\Console\Migrations\ResetCommand;
use Illuminate\Database\Console\Migrations\RollbackCommand;
use Illuminate\Database\Console\WipeCommand;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use Tests\TestCase;

/**
 * Memastikan test terisolasi dari database kerja dan pengaman perintah destruktif bekerja.
 * Tidak ada perintah destruktif yang dijalankan di sini: hanya status "prohibited" yang dibaca.
 */
class IsolasiDatabaseTest extends TestCase
{
    private const PERINTAH = [FreshCommand::class, RefreshCommand::class, ResetCommand::class, RollbackCommand::class, WipeCommand::class];

    private function dilarang(string $kelas): bool
    {
        $p = (new ReflectionClass($kelas))->getProperty('prohibitedFromRunning');
        $p->setAccessible(true);

        return (bool) $p->getValue();
    }

    private function bootProvider(string $namaDatabase): void
    {
        config(['database.connections.mysql.database' => $namaDatabase]);
        (new AppServiceProvider($this->app))->boot();
    }

    public function test_test_berjalan_pada_database_pengujian_bukan_database_kerja(): void
    {
        $aktif = DB::connection()->selectOne('SELECT DATABASE() AS nama')->nama;

        $this->assertSame('poliklinik_test', $aktif);
        $this->assertNotSame('poliklinik_db', $aktif);
        $this->assertSame('poliklinik_test', config('database.connections.mysql.database'));
    }

    public function test_perintah_destruktif_diizinkan_hanya_pada_database_pengujian(): void
    {
        // Lingkungan test (poliklinik_test): tidak dilarang, sehingga test migrasi tetap bisa memakai rollback.
        foreach (self::PERINTAH as $kelas) {
            $this->assertFalse($this->dilarang($kelas), "{$kelas} seharusnya diizinkan pada database *_test");
        }
    }

    public function test_perintah_destruktif_dilarang_pada_database_kerja(): void
    {
        $asli = config('database.connections.mysql.database');

        try {
            $this->bootProvider('poliklinik_db');
            foreach (self::PERINTAH as $kelas) {
                $this->assertTrue($this->dilarang($kelas), "{$kelas} harus dilarang pada poliklinik_db");
            }
            // Database lain yang bukan "_test" juga dilarang.
            $this->bootProvider('database_apa_saja');
            $this->assertTrue($this->dilarang(FreshCommand::class));
        } finally {
            // Pulihkan status global agar test lain tidak terpengaruh.
            $this->bootProvider($asli);
        }

        foreach (self::PERINTAH as $kelas) {
            $this->assertFalse($this->dilarang($kelas));
        }
    }

    public function test_trait_prohibitable_dipakai_oleh_semua_perintah_yang_dijaga(): void
    {
        foreach (self::PERINTAH as $kelas) {
            $this->assertContains(Prohibitable::class, class_uses_recursive($kelas), "{$kelas} tidak memakai Prohibitable");
        }
    }
}
