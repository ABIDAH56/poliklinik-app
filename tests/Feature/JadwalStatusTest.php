<?php

namespace Tests\Feature;

use App\Models\DaftarPoli;
use App\Models\JadwalPeriksa;
use App\Models\Poli;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

// Memakai DatabaseTransactions (bukan RefreshDatabase) agar data asli tidak terhapus.
class JadwalStatusTest extends TestCase
{
    use DatabaseTransactions;

    private Poli $poli;
    private User $dokter;
    private User $pasien;
    private JadwalPeriksa $jadwal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->poli = Poli::create(['nama_poli' => 'Poli Status ' . uniqid()]);
        $this->dokter = User::create([
            'nama' => 'DokterStatus' . uniqid(), 'email' => uniqid('dr') . '@test.local', 'password' => 'secret',
            'role' => 'dokter', 'id_poli' => $this->poli->id,
        ]);
        $this->pasien = User::create([
            'nama' => 'Pasien', 'email' => uniqid('ps') . '@test.local', 'password' => 'secret', 'role' => 'pasien',
        ]);
        $this->jadwal = JadwalPeriksa::create([
            'id_dokter' => $this->dokter->id, 'hari' => 'Senin', 'jam_mulai' => '08:00', 'jam_selesai' => '10:00',
        ]);
    }

    private function opsiJadwal(): string
    {
        return 'value="' . $this->jadwal->id . '" data-poli';
    }

    private function nonaktifkan(): void
    {
        $this->jadwal->update(['status' => false]);
    }

    private function ubah(array $data)
    {
        return $this->actingAs($this->dokter)->put("/dokter/jadwal-periksa/{$this->jadwal->id}", $data + [
            'hari' => 'Senin', 'jam_mulai' => '08:00', 'jam_selesai' => '10:00', 'status' => 1,
        ]);
    }

    public function test_jadwal_baru_berstatus_aktif_secara_default(): void
    {
        $this->assertTrue($this->jadwal->fresh()->status);
    }

    public function test_jadwal_nonaktif_tidak_muncul_di_dashboard_dan_form_pendaftaran_pasien(): void
    {
        $this->actingAs($this->pasien)->get('/pasien/dashboard')->assertOk()->assertSee($this->dokter->nama);

        $this->nonaktifkan();

        $this->actingAs($this->pasien)->get('/pasien/dashboard')->assertOk()->assertDontSee($this->dokter->nama);
        $this->actingAs($this->pasien)->get('/pasien/daftar')->assertOk()->assertDontSee($this->opsiJadwal(), false);
    }

    public function test_pendaftaran_langsung_ke_jadwal_nonaktif_ditolak(): void
    {
        $this->nonaktifkan();

        $this->actingAs($this->pasien)->post('/pasien/daftar', [
            'id_poli' => $this->poli->id, 'id_jadwal' => $this->jadwal->id, 'keluhan' => 'x',
        ])->assertSessionHasErrors('id_jadwal');

        $this->assertSame(0, DaftarPoli::where('id_jadwal', $this->jadwal->id)->count());
    }

    public function test_jadwal_yang_diaktifkan_lagi_muncul_dan_bisa_didaftar(): void
    {
        $this->nonaktifkan();
        $this->actingAs($this->pasien)->get('/pasien/daftar')->assertDontSee($this->opsiJadwal(), false);

        $this->ubah(['status' => 1])->assertSessionHasNoErrors()->assertRedirect(route('jadwal-periksa.index'));
        $this->assertTrue($this->jadwal->fresh()->status);

        $this->actingAs($this->pasien)->get('/pasien/dashboard')->assertSee($this->dokter->nama);
        $this->actingAs($this->pasien)->get('/pasien/daftar')->assertSee($this->opsiJadwal(), false);
        $this->actingAs($this->pasien)->post('/pasien/daftar', [
            'id_poli' => $this->poli->id, 'id_jadwal' => $this->jadwal->id, 'keluhan' => 'x',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, DaftarPoli::where('id_jadwal', $this->jadwal->id)->count());
    }

    public function test_status_wajib_diisi_dan_harus_berupa_boolean(): void
    {
        foreach (['', 'abc', '2'] as $nilai) {
            $this->ubah(['status' => $nilai])->assertSessionHasErrors('status');
        }
        $this->assertTrue($this->jadwal->fresh()->status);
    }

    public function test_status_bisa_diubah_pada_jadwal_berpendaftar_tetapi_hari_dan_jam_tidak(): void
    {
        DaftarPoli::create([
            'id_pasien' => $this->pasien->id, 'id_jadwal' => $this->jadwal->id, 'keluhan' => 'x', 'no_antrian' => 1,
        ]);

        // Status berubah, hari dan jam tetap.
        $this->ubah(['status' => 0])->assertSessionHasNoErrors()->assertRedirect(route('jadwal-periksa.index'));
        $jadwal = $this->jadwal->fresh();
        $this->assertFalse($jadwal->status);
        $this->assertSame(['Senin', '08:00:00', '10:00:00'], [$jadwal->hari, $jadwal->jam_mulai, $jadwal->jam_selesai]);

        // Hari atau jam tidak boleh berubah walau status ikut dikirim; status tidak ikut tersimpan.
        foreach ([['hari' => 'Selasa'], ['jam_mulai' => '09:00'], ['jam_selesai' => '11:00']] as $perubahan) {
            $this->ubah($perubahan + ['status' => 1])->assertSessionHasErrors('hari');
        }
        $jadwal = $this->jadwal->fresh();
        $this->assertFalse($jadwal->status);
        $this->assertSame(['Senin', '08:00:00', '10:00:00'], [$jadwal->hari, $jadwal->jam_mulai, $jadwal->jam_selesai]);
    }
}
