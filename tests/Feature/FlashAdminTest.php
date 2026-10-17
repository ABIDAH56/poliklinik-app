<?php

namespace Tests\Feature;

use App\Models\DaftarPoli;
use App\Models\JadwalPeriksa;
use App\Models\Poli;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

// DatabaseTransactions: data uji di-rollback, database asli tidak disentuh.
class FlashAdminTest extends TestCase
{
    use DatabaseTransactions;

    private Poli $poli;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->poli = Poli::create(['nama_poli' => 'Poli Uji']);
        $this->admin = $this->user('admin');
    }

    private function user(string $role, bool $denganPoli = false): User
    {
        return User::create([
            'nama' => ucfirst($role) . ' Uji', 'email' => uniqid($role) . '@test.local', 'password' => 'secret',
            'role' => $role, 'id_poli' => $denganPoli ? $this->poli->id : null,
        ]);
    }

    private function data(array $extra = []): array
    {
        $n = random_int(100000, 999999);

        return $extra + [
            'nama' => 'Nama Uji', 'alamat' => 'Jl. Uji', 'no_ktp' => '99' . $n, 'no_hp' => '0812' . $n,
            'email' => "u{$n}@test.local", 'password' => 'rahasia123',
        ];
    }

    private function assertTampilSekali($response, string $pesan): void
    {
        $response->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), $pesan), "Pesan \"{$pesan}\" harus tampil tepat sekali.");
    }

    // ---------- Dokter ----------

    public function test_flash_sukses_tambah_dokter_tampil_sekali(): void
    {
        $r = $this->actingAs($this->admin)->followingRedirects()
            ->post('/admin/dokter', $this->data(['id_poli' => (string) $this->poli->id]));

        $this->assertTampilSekali($r, 'Data Dokter Berhasil di tambahkan');
    }

    public function test_flash_sukses_ubah_dokter_tampil_sekali(): void
    {
        $dokter = $this->user('dokter', true);

        $r = $this->actingAs($this->admin)->followingRedirects()
            ->put("/admin/dokter/{$dokter->id}", $this->data(['id_poli' => (string) $this->poli->id]));

        $this->assertTampilSekali($r, 'Data Dokter Berhasil di ubah');
    }

    public function test_flash_sukses_hapus_dokter_tampil_sekali(): void
    {
        $dokter = $this->user('dokter', true);

        $r = $this->actingAs($this->admin)->followingRedirects()->delete("/admin/dokter/{$dokter->id}");

        $this->assertTampilSekali($r, 'Data Dokter Berhasil dihapus');
    }

    public function test_flash_error_hapus_dokter_bertugas_tampil_sekali_dan_tanpa_pesan_sukses(): void
    {
        $dokter = $this->user('dokter', true);
        JadwalPeriksa::create(['id_dokter' => $dokter->id, 'hari' => 'Senin', 'jam_mulai' => '08:00', 'jam_selesai' => '09:00']);

        $r = $this->actingAs($this->admin)->followingRedirects()->delete("/admin/dokter/{$dokter->id}");

        $this->assertTampilSekali($r, 'tidak dapat dihapus karena masih memiliki jadwal periksa');
        $r->assertDontSee('Data Dokter Berhasil dihapus');
    }

    public function test_flash_tidak_muncul_lagi_setelah_halaman_dimuat_ulang(): void
    {
        $dokter = $this->user('dokter', true);
        $this->actingAs($this->admin)->delete("/admin/dokter/{$dokter->id}");

        $this->actingAs($this->admin)->get('/admin/dokter')->assertSee('Data Dokter Berhasil dihapus');
        $this->actingAs($this->admin)->get('/admin/dokter')->assertDontSee('Data Dokter Berhasil dihapus');
    }

    // ---------- Pasien ----------

    public function test_flash_sukses_tambah_pasien_tampil_sekali(): void
    {
        $r = $this->actingAs($this->admin)->followingRedirects()->post('/admin/pasien', $this->data());

        $this->assertTampilSekali($r, 'Data Pasien berhasil di Tambah');
    }

    public function test_flash_sukses_ubah_pasien_tampil_sekali(): void
    {
        $pasien = $this->user('pasien');

        $r = $this->actingAs($this->admin)->followingRedirects()->put("/admin/pasien/{$pasien->id}", $this->data());

        $this->assertTampilSekali($r, 'Data Pasien Berhasil di Update');
    }

    public function test_flash_sukses_hapus_pasien_tampil_sekali(): void
    {
        $pasien = $this->user('pasien');

        $r = $this->actingAs($this->admin)->followingRedirects()->delete("/admin/pasien/{$pasien->id}");

        $this->assertTampilSekali($r, 'Data Pasien Berhasil Di Hapus');
    }

    public function test_flash_error_hapus_pasien_berpendaftaran_tampil_sekali_dan_tanpa_pesan_sukses(): void
    {
        $pasien = $this->user('pasien');
        $jadwal = JadwalPeriksa::create([
            'id_dokter' => $this->user('dokter', true)->id, 'hari' => 'Senin', 'jam_mulai' => '08:00', 'jam_selesai' => '09:00',
        ]);
        DaftarPoli::create(['id_pasien' => $pasien->id, 'id_jadwal' => $jadwal->id, 'keluhan' => 'x', 'no_antrian' => 1]);

        $r = $this->actingAs($this->admin)->followingRedirects()->delete("/admin/pasien/{$pasien->id}");

        $this->assertTampilSekali($r, 'tidak dapat dihapus karena sudah memiliki pendaftaran');
        $r->assertDontSee('Data Pasien Berhasil Di Hapus');
    }

    // ---------- Halaman lain yang sudah punya penampil pesan sendiri tidak boleh dobel ----------

    public function test_poli_tetap_menampilkan_pesan_sekali(): void
    {
        $r = $this->actingAs($this->admin)->followingRedirects()
            ->post('/admin/polis', ['nama_poli' => 'Poli Baru', 'keterangan' => 'x']);

        $this->assertTampilSekali($r, 'Poli berhasil di tambahkan');
    }

    public function test_obat_tetap_menampilkan_pesan_sekali(): void
    {
        $r = $this->actingAs($this->admin)->followingRedirects()
            ->post('/admin/obat', ['nama_obat' => 'Obat Uji', 'kemasan' => 'strip', 'harga' => 1000, 'stok' => 5]);

        $this->assertTampilSekali($r, 'Data Obat Berhasil dibuat');
    }
}
