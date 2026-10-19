<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Menguji migration unique no_rm pada database SQLite di memori.
 * Database MySQL asli tidak disentuh sama sekali (tidak ada migrate/rollback ke MySQL).
 */
class UniqueNoRmMigrationTest extends TestCase
{
    // Migration yang diuji dirollback secara spesifik, supaya tes tidak bergantung pada urutan migration terbaru.
    private const MIGRATION_UNIQUE = 'database/migrations/2026_10_17_000000_add_unique_no_rm_to_users_table.php';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.sqlite_mem' => [
                'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
            ],
            'database.default' => 'sqlite_mem',
        ]);

        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'sqlite_mem', '--force' => true]));
    }

    private function pasien(?string $noRm): int
    {
        return DB::table('users')->insertGetId([
            'nama' => 'P', 'email' => uniqid('p') . '@test.local', 'password' => 'x', 'role' => 'pasien', 'no_rm' => $noRm,
        ]);
    }

    public function test_database_menolak_no_rm_duplikat(): void
    {
        $this->pasien('202610-010');

        $this->expectException(QueryException::class);
        $this->pasien('202610-010');
    }

    public function test_banyak_pengguna_tanpa_no_rm_tetap_diperbolehkan(): void
    {
        foreach (range(1, 3) as $_) {
            $this->pasien(null);
        }
        DB::table('users')->insert(['nama' => 'Admin', 'email' => 'a@test.local', 'password' => 'x', 'role' => 'admin']);

        $this->assertSame(4, DB::table('users')->whereNull('no_rm')->count());
    }

    public function test_pembuatan_pasien_tetap_menghasilkan_no_rm_unik_dengan_constraint_aktif(): void
    {
        $nomor = [];
        for ($i = 0; $i < 6; $i++) {
            $nomor[] = User::buatPasien([
                'nama' => 'P', 'email' => uniqid('p') . '@test.local', 'password' => 'secret',
            ])->no_rm;
            User::create(['nama' => 'D', 'email' => uniqid('d') . '@test.local', 'password' => 'secret', 'role' => 'dokter']);
        }

        $this->assertCount(6, array_unique($nomor));
    }

    public function test_migration_berhenti_jika_ada_no_rm_kembar_dan_tidak_mengubah_data(): void
    {
        // Lepas constraint (rollback migration unique no_rm saja, bukan migration terbaru), buat data kembar, lalu coba pasang lagi.
        $this->assertSame(0, Artisan::call('migrate:rollback', ['--database' => 'sqlite_mem', '--path' => self::MIGRATION_UNIQUE, '--force' => true]));
        $this->pasien('202610-020');
        $this->pasien('202610-020');

        try {
            Artisan::call('migrate', ['--database' => 'sqlite_mem', '--force' => true]);
            $this->fail('Migration seharusnya berhenti karena ada no_rm kembar.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('202610-020', $e->getMessage());
        }

        $this->assertSame(2, DB::table('users')->where('no_rm', '202610-020')->count());
        $this->assertFalse(collect(Schema::getIndexes('users'))->contains('name', 'users_no_rm_unique'));
    }

    public function test_migration_bisa_dibatalkan_dan_dipasang_ulang_tanpa_kehilangan_data(): void
    {
        $id = $this->pasien('202610-030');

        $this->assertSame(0, Artisan::call('migrate:rollback', ['--database' => 'sqlite_mem', '--path' => self::MIGRATION_UNIQUE, '--force' => true]));
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'sqlite_mem', '--force' => true]));

        $this->assertSame('202610-030', DB::table('users')->where('id', $id)->value('no_rm'));
        $this->assertTrue(collect(Schema::getIndexes('users'))->contains(fn ($i) => $i['name'] === 'users_no_rm_unique' && $i['unique']));
    }
}
