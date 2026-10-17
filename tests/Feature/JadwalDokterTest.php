<?php

namespace Tests\Feature;

use App\Models\JadwalPeriksa;
use App\Models\Poli;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

// DatabaseTransactions: data uji di-rollback, database asli tidak disentuh.
class JadwalDokterTest extends TestCase
{
    use DatabaseTransactions;

    private Poli $poli;

    protected function setUp(): void
    {
        parent::setUp();
        $this->poli = Poli::create(['nama_poli' => 'Poli Uji']);
    }

    private function dokter(): User
    {
        return User::create([
            'nama' => 'Dokter', 'email' => uniqid('dr') . '@test.local', 'password' => 'secret',
            'role' => 'dokter', 'id_poli' => $this->poli->id,
        ]);
    }

    private function jadwal(User $dokter, string $hari = 'Senin', string $mulai = '08:00', string $selesai = '10:00'): JadwalPeriksa
    {
        return JadwalPeriksa::create([
            'id_dokter' => $dokter->id, 'hari' => $hari, 'jam_mulai' => $mulai, 'jam_selesai' => $selesai,
        ]);
    }

    private function simpan(User $dokter, array $data)
    {
        return $this->actingAs($dokter)->post('/dokter/jadwal-periksa', $data);
    }

    // ---------- Hak akses ----------

    public function test_dokter_tidak_bisa_mengakses_jadwal_dokter_lain(): void
    {
        $pemilik = $this->dokter();
        $penyusup = $this->dokter();
        $jadwal = $this->jadwal($pemilik);

        $this->actingAs($penyusup)->get("/dokter/jadwal-periksa/{$jadwal->id}/edit")->assertNotFound();
        $this->actingAs($penyusup)->put("/dokter/jadwal-periksa/{$jadwal->id}", [
            'hari' => 'Selasa', 'jam_mulai' => '09:00', 'jam_selesai' => '11:00',
        ])->assertNotFound();
        $this->actingAs($penyusup)->delete("/dokter/jadwal-periksa/{$jadwal->id}")->assertNotFound();

        $jadwal->refresh();
        $this->assertSame('Senin', $jadwal->hari);
        $this->assertSame(1, JadwalPeriksa::whereKey($jadwal->id)->count());
    }

    public function test_daftar_jadwal_hanya_menampilkan_milik_sendiri(): void
    {
        $a = $this->dokter();
        $b = $this->dokter();
        $this->jadwal($a, 'Senin', '08:00', '09:00');
        $this->jadwal($b, 'Kamis', '13:00', '14:00');

        $this->actingAs($a)->get('/dokter/jadwal-periksa')->assertOk()->assertSee('Senin')->assertDontSee('Kamis');
    }

    public function test_id_dokter_dari_request_diabaikan_saat_menyimpan(): void
    {
        $saya = $this->dokter();
        $lain = $this->dokter();

        $this->simpan($saya, [
            'id_dokter' => $lain->id, 'hari' => 'Rabu', 'jam_mulai' => '08:00', 'jam_selesai' => '09:00',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, JadwalPeriksa::where('id_dokter', $saya->id)->count());
        $this->assertSame(0, JadwalPeriksa::where('id_dokter', $lain->id)->count());
    }

    public function test_id_dokter_dari_request_diabaikan_saat_update(): void
    {
        $saya = $this->dokter();
        $lain = $this->dokter();
        $jadwal = $this->jadwal($saya);

        $this->actingAs($saya)->put("/dokter/jadwal-periksa/{$jadwal->id}", [
            'id_dokter' => $lain->id, 'hari' => 'Selasa', 'jam_mulai' => '09:00', 'jam_selesai' => '11:00',
        ])->assertSessionHasNoErrors();

        $this->assertSame($saya->id, $jadwal->fresh()->id_dokter);
    }

    // ---------- Validasi hari dan jam ----------

    public function test_hari_di_luar_pilihan_ditolak(): void
    {
        $dokter = $this->dokter();

        $this->simpan($dokter, ['hari' => 'Monday', 'jam_mulai' => '08:00', 'jam_selesai' => '09:00'])
            ->assertSessionHasErrors('hari');
        $this->simpan($dokter, ['hari' => '', 'jam_mulai' => '08:00', 'jam_selesai' => '09:00'])
            ->assertSessionHasErrors('hari');
        $this->assertSame(0, JadwalPeriksa::where('id_dokter', $dokter->id)->count());
    }

    public function test_format_jam_tidak_valid_ditolak(): void
    {
        $dokter = $this->dokter();

        $this->simpan($dokter, ['hari' => 'Senin', 'jam_mulai' => 'abc', 'jam_selesai' => '25:00'])
            ->assertSessionHasErrors(['jam_mulai', 'jam_selesai']);
        $this->assertSame(0, JadwalPeriksa::where('id_dokter', $dokter->id)->count());
    }

    public function test_jam_selesai_harus_lebih_besar_dari_jam_mulai(): void
    {
        $dokter = $this->dokter();

        $this->simpan($dokter, ['hari' => 'Senin', 'jam_mulai' => '10:00', 'jam_selesai' => '08:00'])
            ->assertSessionHasErrors('jam_selesai');
        $this->simpan($dokter, ['hari' => 'Senin', 'jam_mulai' => '10:00', 'jam_selesai' => '10:00'])
            ->assertSessionHasErrors('jam_selesai');
        $this->assertSame(0, JadwalPeriksa::where('id_dokter', $dokter->id)->count());
    }

    // ---------- Bentrok ----------

    public function test_jadwal_yang_tumpang_tindih_di_hari_sama_ditolak(): void
    {
        $dokter = $this->dokter();
        $this->jadwal($dokter, 'Senin', '08:00', '10:00');

        foreach ([['09:00', '11:00'], ['07:00', '08:30'], ['08:30', '09:30'], ['07:00', '12:00'], ['08:00', '10:00']] as [$m, $s]) {
            $this->simpan($dokter, ['hari' => 'Senin', 'jam_mulai' => $m, 'jam_selesai' => $s])
                ->assertSessionHasErrors('jam_mulai');
        }
        $this->assertSame(1, JadwalPeriksa::where('id_dokter', $dokter->id)->count());
    }

    public function test_beberapa_sesi_tidak_bentrok_pada_hari_sama_diperbolehkan(): void
    {
        $dokter = $this->dokter();
        $this->jadwal($dokter, 'Senin', '08:00', '10:00');

        // Menempel tepat di ujung sesi sebelumnya/sesudahnya tidak dianggap bentrok.
        $this->simpan($dokter, ['hari' => 'Senin', 'jam_mulai' => '10:00', 'jam_selesai' => '12:00'])->assertSessionHasNoErrors();
        $this->simpan($dokter, ['hari' => 'Senin', 'jam_mulai' => '06:00', 'jam_selesai' => '08:00'])->assertSessionHasNoErrors();
        $this->assertSame(3, JadwalPeriksa::where('id_dokter', $dokter->id)->count());
    }

    public function test_jam_sama_di_hari_berbeda_atau_dokter_berbeda_tidak_bentrok(): void
    {
        $dokter = $this->dokter();
        $lain = $this->dokter();
        $this->jadwal($lain, 'Senin', '08:00', '10:00');
        $this->jadwal($dokter, 'Senin', '08:00', '10:00');

        $this->simpan($dokter, ['hari' => 'Selasa', 'jam_mulai' => '08:00', 'jam_selesai' => '10:00'])->assertSessionHasNoErrors();
        $this->assertSame(2, JadwalPeriksa::where('id_dokter', $dokter->id)->count());
    }

    // ---------- Edit ----------

    public function test_edit_valid_berhasil_dan_tidak_bentrok_dengan_dirinya_sendiri(): void
    {
        $dokter = $this->dokter();
        $jadwal = $this->jadwal($dokter, 'Senin', '08:00', '10:00');

        // Menyimpan ulang rentang yang sama / sebagian tumpang tindih dengan dirinya sendiri harus lolos.
        $this->actingAs($dokter)->put("/dokter/jadwal-periksa/{$jadwal->id}", [
            'hari' => 'Senin', 'jam_mulai' => '08:00', 'jam_selesai' => '10:00',
        ])->assertSessionHasNoErrors();
        $this->actingAs($dokter)->put("/dokter/jadwal-periksa/{$jadwal->id}", [
            'hari' => 'Senin', 'jam_mulai' => '08:30:00', 'jam_selesai' => '11:00:00',
        ])->assertRedirect(route('jadwal-periksa.index'))->assertSessionHasNoErrors();

        $jadwal->refresh();
        $this->assertSame('08:30:00', $jadwal->jam_mulai);
        $this->assertSame('11:00:00', $jadwal->jam_selesai);
    }

    public function test_edit_yang_membuat_bentrok_dengan_jadwal_lain_ditolak(): void
    {
        $dokter = $this->dokter();
        $a = $this->jadwal($dokter, 'Senin', '08:00', '10:00');
        $b = $this->jadwal($dokter, 'Senin', '10:00', '12:00');

        $this->actingAs($dokter)->put("/dokter/jadwal-periksa/{$b->id}", [
            'hari' => 'Senin', 'jam_mulai' => '09:00', 'jam_selesai' => '12:00',
        ])->assertSessionHasErrors('jam_mulai');

        $this->assertSame('10:00:00', $b->fresh()->jam_mulai);
        $this->assertSame('08:00:00', $a->fresh()->jam_mulai);
    }

    public function test_edit_dengan_jam_tidak_valid_ditolak_dan_data_tidak_berubah(): void
    {
        $dokter = $this->dokter();
        $jadwal = $this->jadwal($dokter);

        $this->actingAs($dokter)->put("/dokter/jadwal-periksa/{$jadwal->id}", [
            'hari' => 'Senin', 'jam_mulai' => '12:00', 'jam_selesai' => '11:00',
        ])->assertSessionHasErrors('jam_selesai');

        $this->assertSame('08:00:00', $jadwal->fresh()->jam_mulai);
    }

    public function test_hapus_jadwal_milik_sendiri_berhasil(): void
    {
        $dokter = $this->dokter();
        $jadwal = $this->jadwal($dokter);

        $this->actingAs($dokter)->delete("/dokter/jadwal-periksa/{$jadwal->id}")->assertRedirect(route('jadwal-periksa.index'));
        $this->assertSame(0, JadwalPeriksa::whereKey($jadwal->id)->count());
    }

    public function test_halaman_form_jadwal_dapat_dibuka_oleh_pemilik(): void
    {
        $dokter = $this->dokter();
        $jadwal = $this->jadwal($dokter);

        $this->actingAs($dokter)->get('/dokter/jadwal-periksa/create')->assertOk();
        $this->actingAs($dokter)->get("/dokter/jadwal-periksa/{$jadwal->id}/edit")->assertOk();
    }
}
