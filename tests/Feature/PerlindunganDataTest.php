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
class PerlindunganDataTest extends TestCase
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

    private function jadwal(User $dokter, string $hari = 'Senin', string $mulai = '08:00', string $selesai = '10:00'): JadwalPeriksa
    {
        return JadwalPeriksa::create([
            'id_dokter' => $dokter->id, 'hari' => $hari, 'jam_mulai' => $mulai, 'jam_selesai' => $selesai,
        ]);
    }

    private function pendaftar(JadwalPeriksa $jadwal): DaftarPoli
    {
        return DaftarPoli::create([
            'id_pasien' => $this->user('pasien')->id, 'id_jadwal' => $jadwal->id, 'keluhan' => 'x', 'no_antrian' => 1,
        ]);
    }

    // ---------- A. Hapus jadwal ----------

    public function test_jadwal_kosong_boleh_dihapus(): void
    {
        $dokter = $this->user('dokter', true);
        $jadwal = $this->jadwal($dokter);

        $this->actingAs($dokter)->delete("/dokter/jadwal-periksa/{$jadwal->id}")
            ->assertRedirect(route('jadwal-periksa.index'))->assertSessionHas('message')->assertSessionMissing('error');

        $this->assertSame(0, JadwalPeriksa::whereKey($jadwal->id)->count());
    }

    public function test_jadwal_dengan_pendaftar_tidak_boleh_dihapus(): void
    {
        $dokter = $this->user('dokter', true);
        $jadwal = $this->jadwal($dokter);
        $antrean = $this->pendaftar($jadwal);

        $this->actingAs($dokter)->delete("/dokter/jadwal-periksa/{$jadwal->id}")
            ->assertRedirect(route('jadwal-periksa.index'))->assertSessionHas('error');

        $this->assertSame(1, JadwalPeriksa::whereKey($jadwal->id)->count());
        $this->assertSame(1, DaftarPoli::whereKey($antrean->id)->count());
    }

    public function test_jadwal_dengan_riwayat_pemeriksaan_tidak_boleh_dihapus_dan_riwayat_utuh(): void
    {
        $dokter = $this->user('dokter', true);
        $jadwal = $this->jadwal($dokter);
        $antrean = $this->pendaftar($jadwal);
        $obat = Obat::create(['nama_obat' => 'Obat', 'kemasan' => 'strip', 'harga' => 1000, 'stok' => 5]);
        $periksa = Periksa::create(['id_daftar_poli' => $antrean->id, 'tgl_periksa' => now(), 'catatan' => 'c', 'biaya_periksa' => 151000]);
        DetailPeriksa::create(['id_periksa' => $periksa->id, 'id_obat' => $obat->id]);

        $this->actingAs($dokter)->delete("/dokter/jadwal-periksa/{$jadwal->id}")->assertSessionHas('error');

        $this->assertSame(1, JadwalPeriksa::whereKey($jadwal->id)->count());
        $this->assertSame(1, Periksa::whereKey($periksa->id)->count());
        $this->assertSame(1, DetailPeriksa::where('id_periksa', $periksa->id)->count());
    }

    // ---------- B. Edit jadwal ----------

    public function test_jadwal_tanpa_pendaftar_tetap_bisa_diubah(): void
    {
        $dokter = $this->user('dokter', true);
        $jadwal = $this->jadwal($dokter);

        $this->actingAs($dokter)->put("/dokter/jadwal-periksa/{$jadwal->id}", [
            'hari' => 'Rabu', 'jam_mulai' => '13:00', 'jam_selesai' => '15:00', 'status' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect(route('jadwal-periksa.index'));

        $jadwal->refresh();
        $this->assertSame('Rabu', $jadwal->hari);
        $this->assertSame('13:00:00', $jadwal->jam_mulai);
        $this->assertSame('15:00:00', $jadwal->jam_selesai);
    }

    public function test_jadwal_dengan_pendaftar_tidak_bisa_diubah_hari_atau_jamnya(): void
    {
        $dokter = $this->user('dokter', true);
        $jadwal = $this->jadwal($dokter);
        $this->pendaftar($jadwal);

        foreach ([
            ['hari' => 'Selasa', 'jam_mulai' => '08:00', 'jam_selesai' => '10:00', 'status' => 1],
            ['hari' => 'Senin', 'jam_mulai' => '09:00', 'jam_selesai' => '10:00', 'status' => 1],
            ['hari' => 'Senin', 'jam_mulai' => '08:00', 'jam_selesai' => '11:00', 'status' => 1],
        ] as $data) {
            $this->actingAs($dokter)->put("/dokter/jadwal-periksa/{$jadwal->id}", $data)->assertSessionHasErrors('hari');
        }

        $jadwal->refresh();
        $this->assertSame(['Senin', '08:00:00', '10:00:00'], [$jadwal->hari, $jadwal->jam_mulai, $jadwal->jam_selesai]);
    }

    public function test_menyimpan_jadwal_berpendaftar_tanpa_perubahan_tidak_error(): void
    {
        $dokter = $this->user('dokter', true);
        $jadwal = $this->jadwal($dokter);
        $this->pendaftar($jadwal);

        // Form edit mengirim ulang nilai yang sama (format H:i:s dari database).
        $this->actingAs($dokter)->put("/dokter/jadwal-periksa/{$jadwal->id}", [
            'hari' => 'Senin', 'jam_mulai' => '08:00:00', 'jam_selesai' => '10:00:00', 'status' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect(route('jadwal-periksa.index'));
    }

    public function test_halaman_edit_menampilkan_peringatan_jika_ada_pendaftar(): void
    {
        $dokter = $this->user('dokter', true);
        $kosong = $this->jadwal($dokter, 'Senin', '08:00', '09:00');
        $terisi = $this->jadwal($dokter, 'Selasa', '08:00', '09:00');
        $this->pendaftar($terisi);

        $this->actingAs($dokter)->get("/dokter/jadwal-periksa/{$terisi->id}/edit")->assertOk()->assertSee('sudah memiliki pendaftar');
        $this->actingAs($dokter)->get("/dokter/jadwal-periksa/{$kosong->id}/edit")->assertOk()->assertDontSee('sudah memiliki pendaftar');
    }

    // ---------- C. Hapus poli ----------

    public function test_poli_yang_dipakai_dokter_tidak_boleh_dihapus(): void
    {
        $admin = $this->user('admin');
        $dokter = $this->user('dokter', true);
        $jadwal = $this->jadwal($dokter);

        $this->actingAs($admin)->delete("/admin/polis/{$this->poli->id}")
            ->assertRedirect(route('polis.index'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Pindahkan dokter'));

        $this->assertSame(1, Poli::whereKey($this->poli->id)->count());
        $this->assertSame(1, User::whereKey($dokter->id)->count());
        $this->assertSame(1, JadwalPeriksa::whereKey($jadwal->id)->count());
    }

    public function test_pesan_penolakan_hapus_poli_tampil_sekali(): void
    {
        $admin = $this->user('admin');
        $this->user('dokter', true);

        $this->actingAs($admin)->delete("/admin/polis/{$this->poli->id}");
        $html = $this->actingAs($admin)->get('/admin/polis')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'masih dipakai oleh dokter'));
    }

    public function test_poli_yang_tidak_dipakai_tetap_bisa_dihapus(): void
    {
        $admin = $this->user('admin');
        $kosong = Poli::create(['nama_poli' => 'Kosong']);

        $this->actingAs($admin)->delete("/admin/polis/{$kosong->id}")
            ->assertRedirect(route('polis.index'))->assertSessionHas('success')->assertSessionMissing('error');

        $this->assertSame(0, Poli::whereKey($kosong->id)->count());
    }

    public function test_poli_bisa_dihapus_setelah_dokter_dipindahkan(): void
    {
        $admin = $this->user('admin');
        $dokter = $this->user('dokter', true);
        $lain = Poli::create(['nama_poli' => 'Poli Lain']);

        $dokter->update(['id_poli' => $lain->id]);

        $this->actingAs($admin)->delete("/admin/polis/{$this->poli->id}")->assertSessionHas('success');
        $this->assertSame(0, Poli::whereKey($this->poli->id)->count());
        $this->assertSame($lain->id, $dokter->fresh()->id_poli);
    }

    // ---------- D. Relasi obat ----------

    public function test_relasi_obat_ke_detail_periksa_berfungsi(): void
    {
        $dokter = $this->user('dokter', true);
        $antrean = $this->pendaftar($this->jadwal($dokter));
        $obat = Obat::create(['nama_obat' => 'Obat', 'kemasan' => 'strip', 'harga' => 1000, 'stok' => 5]);
        $periksa = Periksa::create(['id_daftar_poli' => $antrean->id, 'tgl_periksa' => now(), 'catatan' => 'c', 'biaya_periksa' => 1]);
        $detail = DetailPeriksa::create(['id_periksa' => $periksa->id, 'id_obat' => $obat->id]);

        $this->assertTrue($obat->detailPeriksas->contains($detail));
        $this->assertTrue($detail->obat->is($obat));
    }

    // ---------- E. Urutan hari ----------

    public function test_jadwal_diurutkan_dari_senin_ke_minggu(): void
    {
        $dokter = $this->user('dokter', true);
        foreach (['Minggu', 'Jumat', 'Kamis', 'Selasa', 'Sabtu', 'Rabu', 'Senin'] as $hari) {
            $this->jadwal($dokter, $hari, '08:00', '09:00');
        }
        $this->jadwal($dokter, 'Senin', '06:00', '07:00');

        $urut = JadwalPeriksa::where('id_dokter', $dokter->id)->urutHari()->get();
        $this->assertSame(
            ['Senin', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'],
            $urut->pluck('hari')->all()
        );
        $this->assertSame('06:00:00', $urut->first()->jam_mulai); // jam awal lebih dulu pada hari yang sama

        $this->actingAs($dokter)->get('/dokter/jadwal-periksa')->assertOk()
            ->assertSeeInOrder(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']);
    }

    public function test_jadwal_untuk_pasien_juga_urut_senin_ke_minggu(): void
    {
        $dokter = $this->user('dokter', true);
        foreach (['Sabtu', 'Selasa', 'Jumat'] as $hari) {
            $this->jadwal($dokter, $hari, '08:00', '09:00');
        }

        $hari = JadwalPeriksa::tersedia()->where('id_dokter', $dokter->id)->pluck('hari')->all();
        $this->assertSame(['Selasa', 'Jumat', 'Sabtu'], $hari);
    }
}
