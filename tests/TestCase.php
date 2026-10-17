<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    // Database kerja yang tidak boleh disentuh test dalam kondisi apa pun.
    private const DATABASE_KERJA = 'poliklinik_db';

    /**
     * Dijalankan saat aplikasi dibuat, sebelum trait test (RefreshDatabase, DatabaseTransactions, dst.)
     * menyentuh database. Test dihentikan jika koneksi aktif bukan database khusus pengujian.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $default = $app['config']->get('database.default');
        $dikonfigurasi = (string) $app['config']->get("database.connections.{$default}.database");
        $aktif = $dikonfigurasi;

        if ($app['config']->get("database.connections.{$default}.driver") === 'mysql') {
            // Tanyakan ke server database mana yang benar-benar terhubung, bukan hanya membaca konfigurasi.
            $aktif = (string) ($app['db']->connection()->selectOne('SELECT DATABASE() AS nama')->nama ?? '');
        }

        if ($aktif === self::DATABASE_KERJA || $dikonfigurasi === self::DATABASE_KERJA
            || ! (str_ends_with($aktif, '_test') || $aktif === ':memory:')) {
            throw new RuntimeException(
                "Test dihentikan: koneksi aktif mengarah ke database \"{$aktif}\" (konfigurasi: \"{$dikonfigurasi}\"). "
                . 'Test hanya boleh berjalan pada database khusus pengujian (nama berakhiran "_test", mis. poliklinik_test). '
                . 'Periksa DB_DATABASE di phpunit.xml dan .env.testing.'
            );
        }

        return $app;
    }
}
