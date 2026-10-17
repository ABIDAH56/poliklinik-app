<?php

namespace Tests\Feature;

use App\Http\Controllers\Dokter\PeriksaPasienController;
use App\Models\DaftarPoli;
use App\Models\DetailPeriksa;
use App\Models\JadwalPeriksa;
use App\Models\Obat;
use App\Models\Periksa;
use App\Models\Poli;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\TestCase;

// DatabaseTransactions (bukan RefreshDatabase): semua data uji di-rollback, database asli tidak direset.
class PendaftaranDanPeriksaTest extends TestCase
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
            'nama' => ucfirst($role), 'email' => uniqid($role) . '@test.local',
            'password' => 'secret', 'role' => $role, 'id_poli' => $denganPoli ? $this->poli->id : null,
        ]);
    }

    private function jadwal(User $dokter): JadwalPeriksa
    {
        return JadwalPeriksa::create([
            'id_dokter' => $dokter->id, 'hari' => 'Senin', 'jam_mulai' => '08:00', 'jam_selesai' => '10:00',
        ]);
    }

    private function antrean(User $pasien, JadwalPeriksa $jadwal, int $no = 1): DaftarPoli
    {
        return DaftarPoli::create([
            'id_pasien' => $pasien->id, 'id_jadwal' => $jadwal->id, 'keluhan' => 'x', 'no_antrian' => $no,
        ]);
    }

    private function obat(int $stok, int $harga = 10000): Obat
    {
        return Obat::create(['nama_obat' => 'Obat ' . uniqid(), 'kemasan' => 'strip', 'harga' => $harga, 'stok' => $stok]);
    }

    private function daftar(User $pasien, JadwalPeriksa $jadwal, array $extra = [])
    {
        return $this->actingAs($pasien)->post('/pasien/daftar', $extra + [
            'id_poli' => $this->poli->id, 'id_jadwal' => $jadwal->id, 'keluhan' => 'sakit',
        ]);
    }

    private function periksa(User $dokter, DaftarPoli $antrean, array $obatIds = [], array $extra = [])
    {
        return $this->actingAs($dokter)->post('/dokter/periksa-pasien', $extra + [
            'id_daftar_poli' => $antrean->id, 'obat_json' => json_encode($obatIds), 'catatan' => 'ok',
        ]);
    }

    // ---------- Pendaftaran pasien ----------

    public function test_pendaftaran_memakai_pasien_yang_login_bukan_input_form(): void
    {
        $jadwal = $this->jadwal($this->user('dokter', true));
        $pasien = $this->user('pasien');
        $korban = $this->user('pasien');

        $this->daftar($pasien, $jadwal, ['id_pasien' => $korban->id])->assertSessionHasNoErrors();

        $this->assertSame(1, DaftarPoli::where('id_pasien', $pasien->id)->count());
        $this->assertSame(0, DaftarPoli::where('id_pasien', $korban->id)->count());
    }

    public function test_pendaftaran_ganda_pada_jadwal_yang_sama_ditolak(): void
    {
        $jadwal = $this->jadwal($this->user('dokter', true));
        $pasien = $this->user('pasien');

        $this->daftar($pasien, $jadwal)->assertSessionHasNoErrors();
        $this->daftar($pasien, $jadwal)->assertSessionHasErrors('id_jadwal');

        $this->assertSame(1, DaftarPoli::where('id_jadwal', $jadwal->id)->count());
    }

    public function test_pasien_boleh_daftar_lagi_setelah_pendaftaran_sebelumnya_selesai_diperiksa(): void
    {
        $dokter = $this->user('dokter', true);
        $jadwal = $this->jadwal($dokter);
        $pasien = $this->user('pasien');

        $this->daftar($pasien, $jadwal);
        $this->periksa($dokter, DaftarPoli::where('id_pasien', $pasien->id)->first());
        $this->daftar($pasien, $jadwal)->assertSessionHasNoErrors();

        $this->assertSame([1, 2], DaftarPoli::where('id_jadwal', $jadwal->id)->orderBy('no_antrian')->pluck('no_antrian')->all());
    }

    public function test_pasien_berbeda_pada_jadwal_sama_mendapat_nomor_berurutan(): void
    {
        $jadwal = $this->jadwal($this->user('dokter', true));
        foreach ([$this->user('pasien'), $this->user('pasien')] as $p) {
            $this->daftar($p, $jadwal)->assertSessionHasNoErrors();
        }
        $this->assertSame([1, 2], DaftarPoli::where('id_jadwal', $jadwal->id)->orderBy('no_antrian')->pluck('no_antrian')->all());
    }

    // ---------- Pemeriksaan ----------

    public function test_pemeriksaan_dengan_obat_menyimpan_semuanya_dan_mengurangi_stok(): void
    {
        $dokter = $this->user('dokter', true);
        $antrean = $this->antrean($this->user('pasien'), $this->jadwal($dokter));
        $a = $this->obat(5, 10000);
        $b = $this->obat(3, 25000);

        // Nilai biaya dari browser diabaikan; total dihitung server.
        $this->periksa($dokter, $antrean, [$a->id, $b->id], ['biaya_periksa' => 1])
            ->assertRedirect(route('periksa-pasien.index'))->assertSessionHas('success');

        $periksa = Periksa::where('id_daftar_poli', $antrean->id)->firstOrFail();
        $this->assertSame(10000 + 25000 + PeriksaPasienController::BIAYA_PEMERIKSAAN, $periksa->biaya_periksa);
        $this->assertSame(2, DetailPeriksa::where('id_periksa', $periksa->id)->count());
        $this->assertSame(4, $a->fresh()->stok);
        $this->assertSame(2, $b->fresh()->stok);
    }

    public function test_pemeriksaan_tanpa_obat_berhasil_dengan_biaya_pemeriksaan_saja(): void
    {
        $dokter = $this->user('dokter', true);
        $antrean = $this->antrean($this->user('pasien'), $this->jadwal($dokter));

        foreach ([[], null] as $kasus) {
            $payload = ['id_daftar_poli' => $antrean->id, 'catatan' => 'ok'];
            if ($kasus !== null) {
                $payload['obat_json'] = json_encode($kasus);
            }
            $this->actingAs($dokter)->post('/dokter/periksa-pasien', $payload)->assertSessionHas('success');
            $periksa = Periksa::where('id_daftar_poli', $antrean->id)->firstOrFail();
            $this->assertSame(PeriksaPasienController::BIAYA_PEMERIKSAAN, $periksa->biaya_periksa);
            $periksa->delete();
        }
    }

    public function test_dokter_tidak_bisa_memeriksa_antrean_dokter_lain(): void
    {
        $dokterA = $this->user('dokter', true);
        $dokterB = $this->user('dokter', true);
        $antrean = $this->antrean($this->user('pasien'), $this->jadwal($dokterA));
        $obat = $this->obat(5);

        $this->periksa($dokterB, $antrean, [$obat->id])->assertNotFound();
        $this->actingAs($dokterB)->get('/dokter/periksa-pasien/' . $antrean->id)->assertNotFound();

        $this->assertSame(0, Periksa::where('id_daftar_poli', $antrean->id)->count());
        $this->assertSame(5, $obat->fresh()->stok);
    }

    public function test_pemeriksaan_yang_sudah_selesai_tidak_bisa_disimpan_ulang(): void
    {
        $dokter = $this->user('dokter', true);
        $antrean = $this->antrean($this->user('pasien'), $this->jadwal($dokter));
        $obat = $this->obat(5);

        $this->periksa($dokter, $antrean, [$obat->id])->assertSessionHas('success');
        $this->periksa($dokter, $antrean, [$obat->id])->assertSessionHas('error');

        $this->assertSame(1, Periksa::where('id_daftar_poli', $antrean->id)->count());
        $this->assertSame(4, $obat->fresh()->stok);
        $this->actingAs($dokter)->get('/dokter/periksa-pasien/' . $antrean->id)->assertRedirect(route('periksa-pasien.index'));
    }

    public function test_stok_tidak_bisa_negatif_atau_dipakai_dua_kali(): void
    {
        $dokter = $this->user('dokter', true);
        $jadwal = $this->jadwal($dokter);
        $a1 = $this->antrean($this->user('pasien'), $jadwal, 1);
        $a2 = $this->antrean($this->user('pasien'), $jadwal, 2);
        $obat = $this->obat(1);

        $this->periksa($dokter, $a1, [$obat->id])->assertSessionHas('success');
        $this->periksa($dokter, $a2, [$obat->id])->assertSessionHas('error');

        $this->assertSame(0, $obat->fresh()->stok);
        $this->assertSame(0, Periksa::where('id_daftar_poli', $a2->id)->count());
    }

    public function test_obat_yang_sama_dikirim_berulang_hanya_mengurangi_stok_sekali(): void
    {
        $dokter = $this->user('dokter', true);
        $antrean = $this->antrean($this->user('pasien'), $this->jadwal($dokter));
        $obat = $this->obat(1, 10000);

        $this->periksa($dokter, $antrean, [$obat->id, $obat->id, (string) $obat->id])->assertSessionHas('success');

        $this->assertSame(0, $obat->fresh()->stok);
        $this->assertSame(1, DetailPeriksa::where('id_obat', $obat->id)->count());
        $this->assertSame(10000 + PeriksaPasienController::BIAYA_PEMERIKSAAN, Periksa::where('id_daftar_poli', $antrean->id)->value('biaya_periksa'));
    }

    public function test_obat_json_tidak_valid_atau_obat_tidak_ada_ditolak_tanpa_perubahan(): void
    {
        $dokter = $this->user('dokter', true);
        $antrean = $this->antrean($this->user('pasien'), $this->jadwal($dokter));
        $obat = $this->obat(5);

        $this->periksa($dokter, $antrean, [], ['obat_json' => 'bukan-json'])->assertSessionHas('error');
        $this->periksa($dokter, $antrean, [], ['obat_json' => '{"a":1}'])->assertSessionHas('error');
        $this->periksa($dokter, $antrean, [], ['obat_json' => '[[1]]'])->assertSessionHas('error');
        $this->periksa($dokter, $antrean, [999999999])->assertSessionHas('error');

        $this->assertSame(0, Periksa::where('id_daftar_poli', $antrean->id)->count());
        $this->assertSame(5, $obat->fresh()->stok);
    }

    public function test_kegagalan_di_tengah_penyimpanan_tidak_meninggalkan_perubahan_parsial(): void
    {
        $dokter = $this->user('dokter', true);
        $antrean = $this->antrean($this->user('pasien'), $this->jadwal($dokter));
        $a = $this->obat(5);
        $b = $this->obat(5);

        // Simulasi kegagalan saat menyimpan detail obat kedua.
        $dibuat = 0;
        DetailPeriksa::creating(function () use (&$dibuat) {
            if (++$dibuat === 2) {
                throw new RuntimeException('simulasi gagal');
            }
        });

        $this->periksa($dokter, $antrean, [$a->id, $b->id])->assertStatus(500);

        $this->assertSame(0, Periksa::where('id_daftar_poli', $antrean->id)->count());
        $this->assertSame(0, DetailPeriksa::whereIn('id_obat', [$a->id, $b->id])->count());
        $this->assertSame(5, $a->fresh()->stok);
        $this->assertSame(5, $b->fresh()->stok);
    }
}
