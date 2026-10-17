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

// Berjalan pada database khusus pengujian (dijaga oleh Tests\TestCase), data di-rollback.
class HapusObatTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create([
            'nama' => 'Admin', 'email' => uniqid('admin') . '@test.local', 'password' => 'secret', 'role' => 'admin',
        ]);
    }

    private function obat(string $nama = 'Obat Uji'): Obat
    {
        return Obat::create(['nama_obat' => $nama, 'kemasan' => 'strip', 'harga' => 1000, 'stok' => 5]);
    }

    private function resepDengan(Obat $obat): array
    {
        $poli = Poli::create(['nama_poli' => 'Poli Uji']);
        $dokter = User::create([
            'nama' => 'Dokter', 'email' => uniqid('dr') . '@test.local', 'password' => 'secret', 'role' => 'dokter', 'id_poli' => $poli->id,
        ]);
        $pasien = User::buatPasien(['nama' => 'Pasien', 'email' => uniqid('p') . '@test.local', 'password' => 'secret']);
        $jadwal = JadwalPeriksa::create(['id_dokter' => $dokter->id, 'hari' => 'Senin', 'jam_mulai' => '08:00', 'jam_selesai' => '09:00']);
        $antrean = DaftarPoli::create(['id_pasien' => $pasien->id, 'id_jadwal' => $jadwal->id, 'keluhan' => 'x', 'no_antrian' => 1]);
        $periksa = Periksa::create(['id_daftar_poli' => $antrean->id, 'tgl_periksa' => now(), 'catatan' => 'c', 'biaya_periksa' => 151000]);
        $detail = DetailPeriksa::create(['id_periksa' => $periksa->id, 'id_obat' => $obat->id]);

        return [$periksa, $detail];
    }

    public function test_obat_yang_belum_pernah_dipakai_boleh_dihapus(): void
    {
        $obat = $this->obat();

        $this->actingAs($this->admin)->delete("/admin/obat/{$obat->id}")
            ->assertRedirect(route('obat.index'))
            ->assertSessionHas('type', 'success')
            ->assertSessionHas('message', 'Data Obat Berhasil di Hapus');

        $this->assertSame(0, Obat::whereKey($obat->id)->count());
    }

    public function test_obat_yang_dipakai_riwayat_pemeriksaan_ditolak_dan_riwayat_utuh(): void
    {
        $obat = $this->obat('Obat Resep');
        [$periksa, $detail] = $this->resepDengan($obat);

        $this->actingAs($this->admin)->delete("/admin/obat/{$obat->id}")
            ->assertRedirect(route('obat.index'))
            ->assertSessionHas('type', 'error')
            ->assertSessionHas('message', fn ($m) => str_contains($m, 'Obat Resep') && str_contains($m, 'riwayat pemeriksaan'));

        $this->assertSame(1, Obat::whereKey($obat->id)->count());
        $this->assertSame(1, DetailPeriksa::whereKey($detail->id)->count());
        $this->assertSame(1, Periksa::whereKey($periksa->id)->count());
        $this->assertSame(5, $obat->fresh()->stok);
    }

    public function test_obat_lain_yang_tidak_dipakai_tetap_boleh_dihapus_saat_obat_dipakai_ada(): void
    {
        $dipakai = $this->obat('Dipakai');
        $kosong = $this->obat('Kosong');
        $this->resepDengan($dipakai);

        $this->actingAs($this->admin)->delete("/admin/obat/{$kosong->id}")->assertSessionHas('type', 'success');

        $this->assertSame(0, Obat::whereKey($kosong->id)->count());
        $this->assertSame(1, Obat::whereKey($dipakai->id)->count());
    }

    public function test_pesan_penolakan_tampil_sekali_di_halaman_obat(): void
    {
        $obat = $this->obat('Obat Resep');
        $this->resepDengan($obat);

        $r = $this->actingAs($this->admin)->followingRedirects()->delete("/admin/obat/{$obat->id}");

        $r->assertOk();
        $this->assertSame(1, substr_count($r->getContent(), 'tidak dapat dihapus karena sudah dipakai pada riwayat pemeriksaan'));
        $r->assertDontSee('Data Obat Berhasil di Hapus');
    }

    public function test_hapus_obat_yang_tidak_ada_mengembalikan_404(): void
    {
        $this->actingAs($this->admin)->delete('/admin/obat/999999999')->assertNotFound();
    }

    public function test_relasi_obat_dan_detail_periksa_tetap_berfungsi(): void
    {
        $obat = $this->obat();
        [, $detail] = $this->resepDengan($obat);

        $this->assertTrue($obat->detailPeriksas->contains($detail));
    }
}
