<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

// Berjalan pada poliklinik_test (dijaga Tests\TestCase), data di-rollback.
class FaviconTest extends TestCase
{
    use DatabaseTransactions;

    private function assertSatuFavicon($response): void
    {
        $html = $response->assertOk()->getContent();

        // Tepat satu deklarasi ikon, menunjuk ke favicon bawaan di public/.
        $this->assertSame(1, preg_match_all('/<link[^>]+rel="(?:shortcut )?icon"[^>]*>/i', $html, $m), 'Harus ada tepat satu <link rel="icon">');
        $this->assertMatchesRegularExpression('#href="https?://[^"/]+/favicon\.ico"#', $m[0][0]);
        $this->assertStringNotContainsString('apple-touch-icon', $html);
    }

    public function test_file_favicon_ada_tidak_kosong_dan_berformat_ico_valid(): void
    {
        $path = public_path('favicon.ico');
        $this->assertFileExists($path);

        $isi = file_get_contents($path);
        $this->assertGreaterThan(0, strlen($isi), 'favicon.ico tidak boleh kosong (0 byte)');

        // Header ICO: reserved=0, type=1 (icon), minimal satu gambar.
        ['reserved' => $reserved, 'type' => $tipe, 'jumlah' => $jumlah] = unpack('vreserved/vtype/vjumlah', substr($isi, 0, 6));
        $this->assertSame(0, $reserved);
        $this->assertSame(1, $tipe, 'Bukan file ICO');
        $this->assertGreaterThanOrEqual(1, $jumlah);

        // Setiap entri harus menunjuk ke data yang benar-benar ada di dalam file.
        for ($i = 0; $i < $jumlah; $i++) {
            $entri = unpack('Cw/Ch/Ccolors/Creserved/vplanes/vbpp/Vsize/Voffset', substr($isi, 6 + 16 * $i, 16));
            $this->assertGreaterThan(0, $entri['size']);
            $this->assertLessThanOrEqual(strlen($isi), $entri['offset'] + $entri['size'], "Gambar ke-{$i} melewati akhir file");
        }
    }

    public function test_halaman_tamu_memakai_favicon_yang_sama(): void
    {
        $this->assertSatuFavicon($this->get('/login'));
        $this->assertSatuFavicon($this->get('/register'));
    }

    public function test_halaman_setiap_role_memakai_favicon_yang_sama(): void
    {
        $halaman = [
            'admin' => ['/admin/dashboard', '/admin/obat', '/admin/dokter', '/admin/pasien', '/admin/polis'],
            'dokter' => ['/dokter/dashboard', '/dokter/jadwal-periksa', '/dokter/periksa-pasien', '/dokter/riwayat-pasien'],
            'pasien' => ['/pasien/dashboard', '/pasien/daftar'],
        ];

        foreach ($halaman as $role => $urls) {
            $user = User::create(['nama' => ucfirst($role), 'email' => uniqid($role) . '@test.local', 'password' => 'secret', 'role' => $role]);
            foreach ($urls as $url) {
                $this->assertSatuFavicon($this->actingAs($user)->get($url));
            }
        }
    }
}
