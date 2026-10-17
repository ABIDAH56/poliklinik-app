<?php

namespace Tests\Feature;

use App\Models\DaftarPoli;
use App\Models\DetailPeriksa;
use App\Models\JadwalPeriksa;
use App\Models\Obat;
use App\Models\Periksa;
use App\Models\Poli;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

// DatabaseTransactions: data uji di-rollback, database asli tidak disentuh.
class HapusAkunDanNoRmTest extends TestCase
{
    use DatabaseTransactions;

    private Poli $poli;

    protected function setUp(): void
    {
        parent::setUp();
        $this->poli = Poli::create(['nama_poli' => 'Poli Uji']);
    }

    private function user(string $role, bool $denganPoli = false): User
    {
        return User::create([
            'nama' => ucfirst($role), 'email' => uniqid($role) . '@test.local', 'password' => 'secret',
            'role' => $role, 'id_poli' => $denganPoli ? $this->poli->id : null,
        ]);
    }

    private function jadwal(User $dokter): JadwalPeriksa
    {
        return JadwalPeriksa::create(['id_dokter' => $dokter->id, 'hari' => 'Senin', 'jam_mulai' => '08:00', 'jam_selesai' => '10:00']);
    }

    private function antrean(User $pasien, JadwalPeriksa $jadwal): DaftarPoli
    {
        return DaftarPoli::create(['id_pasien' => $pasien->id, 'id_jadwal' => $jadwal->id, 'keluhan' => 'x', 'no_antrian' => 1]);
    }

    private function dataPasien(array $extra = []): array
    {
        $n = random_int(100000, 999999);

        return $extra + [
            'nama' => 'Pasien Uji', 'alamat' => 'Jl. Uji', 'no_ktp' => (string) $n . random_int(100, 999),
            'no_hp' => '0812' . $n, 'email' => "p{$n}@test.local", 'password' => 'rahasia123',
        ];
    }

    // ---------- Hapus dokter ----------

    public function test_dokter_tanpa_data_terkait_boleh_dihapus(): void
    {
        $admin = $this->user('admin');
        $dokter = $this->user('dokter', true);

        $this->actingAs($admin)->delete("/admin/dokter/{$dokter->id}")
            ->assertRedirect(route('dokter.index'))->assertSessionMissing('error');

        $this->assertSame(0, User::whereKey($dokter->id)->count());
    }

    public function test_dokter_yang_punya_jadwal_tidak_boleh_dihapus(): void
    {
        $admin = $this->user('admin');
        $dokter = $this->user('dokter', true);
        $jadwal = $this->jadwal($dokter);

        $this->actingAs($admin)->delete("/admin/dokter/{$dokter->id}")
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'jadwal periksa'));

        $this->assertSame(1, User::whereKey($dokter->id)->count());
        $this->assertSame(1, JadwalPeriksa::whereKey($jadwal->id)->count());
    }

    public function test_dokter_dengan_pendaftaran_dan_riwayat_tidak_boleh_dihapus_dan_data_utuh(): void
    {
        $admin = $this->user('admin');
        $dokter = $this->user('dokter', true);
        $antrean = $this->antrean($this->user('pasien'), $this->jadwal($dokter));
        $obat = Obat::create(['nama_obat' => 'Obat', 'kemasan' => 'strip', 'harga' => 1000, 'stok' => 5]);
        $periksa = Periksa::create(['id_daftar_poli' => $antrean->id, 'tgl_periksa' => now(), 'catatan' => 'c', 'biaya_periksa' => 151000]);
        DetailPeriksa::create(['id_periksa' => $periksa->id, 'id_obat' => $obat->id]);

        $this->actingAs($admin)->delete("/admin/dokter/{$dokter->id}")
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'riwayat pemeriksaan'));

        $this->assertSame(1, User::whereKey($dokter->id)->count());
        $this->assertSame(1, DaftarPoli::whereKey($antrean->id)->count());
        $this->assertSame(1, Periksa::whereKey($periksa->id)->count());
        $this->assertSame(1, DetailPeriksa::where('id_periksa', $periksa->id)->count());
    }

    // ---------- Hapus pasien ----------

    public function test_pasien_tanpa_pendaftaran_boleh_dihapus(): void
    {
        $admin = $this->user('admin');
        $pasien = $this->user('pasien');

        $this->actingAs($admin)->delete("/admin/pasien/{$pasien->id}")
            ->assertRedirect(route('pasien.index'))->assertSessionMissing('error');

        $this->assertSame(0, User::whereKey($pasien->id)->count());
    }

    public function test_pasien_dengan_pendaftaran_atau_riwayat_tidak_boleh_dihapus(): void
    {
        $admin = $this->user('admin');
        $pasien = $this->user('pasien');
        $antrean = $this->antrean($pasien, $this->jadwal($this->user('dokter', true)));
        $periksa = Periksa::create(['id_daftar_poli' => $antrean->id, 'tgl_periksa' => now(), 'catatan' => 'c', 'biaya_periksa' => 150000]);

        $this->actingAs($admin)->delete("/admin/pasien/{$pasien->id}")
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'pendaftaran atau riwayat pemeriksaan'));

        $this->assertSame(1, User::whereKey($pasien->id)->count());
        $this->assertSame(1, DaftarPoli::whereKey($antrean->id)->count());
        $this->assertSame(1, Periksa::whereKey($periksa->id)->count());
    }

    public function test_pasien_dengan_pendaftaran_belum_diperiksa_juga_ditolak(): void
    {
        $admin = $this->user('admin');
        $pasien = $this->user('pasien');
        $this->antrean($pasien, $this->jadwal($this->user('dokter', true)));

        $this->actingAs($admin)->delete("/admin/pasien/{$pasien->id}")->assertSessionHas('error');
        $this->assertSame(1, User::whereKey($pasien->id)->count());
    }

    public function test_pesan_penolakan_tampil_sekali_di_halaman_daftar(): void
    {
        $admin = $this->user('admin');
        $pasien = $this->user('pasien');
        $this->antrean($pasien, $this->jadwal($this->user('dokter', true)));

        $this->actingAs($admin)->delete("/admin/pasien/{$pasien->id}");
        $html = $this->actingAs($admin)->get('/admin/pasien')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'tidak dapat dihapus karena sudah memiliki pendaftaran'));
    }

    // ---------- Jalur hapus tidak boleh menjangkau role lain ----------

    public function test_rute_hapus_dokter_dan_pasien_tidak_bisa_dipakai_untuk_role_lain(): void
    {
        $admin = $this->user('admin');
        $adminLain = $this->user('admin');
        $dokter = $this->user('dokter', true);
        $pasien = $this->user('pasien');

        $this->actingAs($admin)->delete("/admin/dokter/{$adminLain->id}")->assertNotFound();
        $this->actingAs($admin)->delete("/admin/dokter/{$pasien->id}")->assertNotFound();
        $this->actingAs($admin)->delete("/admin/pasien/{$dokter->id}")->assertNotFound();
        $this->actingAs($admin)->delete("/admin/pasien/{$adminLain->id}")->assertNotFound();
        $this->actingAs($admin)->get("/admin/dokter/{$pasien->id}/edit")->assertNotFound();
        $this->actingAs($admin)->get("/admin/pasien/{$dokter->id}/edit")->assertNotFound();

        $this->assertSame(3, User::whereIn('id', [$adminLain->id, $dokter->id, $pasien->id])->count());
    }

    // ---------- Nomor rekam medis ----------

    private function assertNoRmValid(User $pasien): void
    {
        $this->assertMatchesRegularExpression('/^\d{6}-\d{3,}$/', (string) $pasien->no_rm);
        $this->assertSame(date('Ym') . '-' . str_pad((string) $pasien->id, 3, '0', STR_PAD_LEFT), $pasien->no_rm);
    }

    public function test_registrasi_pasien_mendapat_no_rm_otomatis(): void
    {
        $data = $this->dataPasien();

        $this->post('/register', $data)->assertRedirect(route('login'))->assertSessionHasNoErrors();

        $pasien = User::where('email', $data['email'])->firstOrFail();
        $this->assertSame('pasien', $pasien->role);
        $this->assertNoRmValid($pasien);
    }

    public function test_pasien_buatan_admin_mendapat_no_rm_otomatis(): void
    {
        $admin = $this->user('admin');
        $data = $this->dataPasien();

        $this->actingAs($admin)->post('/admin/pasien', $data)->assertRedirect(route('pasien.index'))->assertSessionHasNoErrors();

        $this->assertNoRmValid(User::where('email', $data['email'])->firstOrFail());
    }

    public function test_no_rm_unik_walau_dibuat_berurutan_lewat_dua_jalur_dan_diselingi_user_lain(): void
    {
        $admin = $this->user('admin');
        $emails = [];

        for ($i = 0; $i < 4; $i++) {
            $d = $this->dataPasien();
            $emails[] = $d['email'];
            $i % 2 === 0
                ? $this->post('/register', $d)->assertSessionHasNoErrors()
                : $this->actingAs($admin)->post('/admin/pasien', $d)->assertSessionHasNoErrors();
            $this->user('dokter'); // menyisipkan user lain agar id tidak berurutan antar pasien
        }

        $noRm = User::whereIn('email', $emails)->pluck('no_rm')->all();
        $this->assertCount(4, $noRm);
        $this->assertCount(4, array_unique($noRm));
        User::whereIn('email', $emails)->get()->each(fn ($p) => $this->assertNoRmValid($p));
    }

    public function test_no_rm_dan_role_tidak_bisa_ditentukan_dari_luar(): void
    {
        $pasien = User::buatPasien([
            'nama' => 'X', 'email' => uniqid('x') . '@test.local', 'password' => 'secret',
            'role' => 'admin', 'no_rm' => 'PALSU-001',
        ]);

        $this->assertSame('pasien', $pasien->role);
        $this->assertNoRmValid($pasien);
    }

    public function test_edit_pasien_tidak_mengubah_no_rm_yang_sudah_ada(): void
    {
        $admin = $this->user('admin');
        $pasien = User::buatPasien([
            'nama' => 'Lama', 'email' => uniqid('lama') . '@test.local', 'password' => 'secret',
        ]);
        $noRmAsli = $pasien->no_rm;

        $this->actingAs($admin)->put("/admin/pasien/{$pasien->id}", [
            'nama' => 'Baru', 'alamat' => 'Jl. Baru', 'no_ktp' => '1234567890', 'no_hp' => '0811',
            'email' => $pasien->email, 'no_rm' => 'COBA-UBAH',
        ])->assertSessionHasNoErrors();

        $pasien->refresh();
        $this->assertSame('Baru', $pasien->nama);
        $this->assertSame($noRmAsli, $pasien->no_rm);
    }

    public function test_pasien_lama_tanpa_no_rm_tidak_diubah_otomatis(): void
    {
        $lama = $this->user('pasien'); // dibuat langsung (seperti data seeder), no_rm null
        $this->post('/register', $this->dataPasien())->assertSessionHasNoErrors();

        $this->assertNull($lama->fresh()->no_rm);
    }
}
