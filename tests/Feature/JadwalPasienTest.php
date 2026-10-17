<?php

namespace Tests\Feature;

use App\Models\DaftarPoli;
use App\Models\JadwalPeriksa;
use App\Models\Poli;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

// Memakai DatabaseTransactions (bukan RefreshDatabase) agar data asli tidak terhapus.
class JadwalPasienTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $role, ?int $idPoli = null): User
    {
        return User::create([
            'nama' => ucfirst($role), 'email' => uniqid($role) . '@test.local',
            'password' => 'secret', 'role' => $role, 'id_poli' => $idPoli,
        ]);
    }

    private function jadwal(User $dokter): JadwalPeriksa
    {
        return JadwalPeriksa::create([
            'id_dokter' => $dokter->id, 'hari' => 'Senin', 'jam_mulai' => '08:00', 'jam_selesai' => '10:00',
        ]);
    }

    public function test_jadwal_dokter_dengan_poli_muncul_di_halaman_daftar_dan_dashboard(): void
    {
        $poli = Poli::create(['nama_poli' => 'Poli Uji']);
        $jadwal = $this->jadwal($this->user('dokter', $poli->id));
        $pasien = $this->user('pasien');

        $daftar = $this->actingAs($pasien)->get('/pasien/daftar')->assertOk();
        $daftar->assertSee('value="' . $jadwal->id . '" data-poli="' . $poli->id . '"', false);
        $this->actingAs($pasien)->get('/pasien/dashboard')->assertOk()->assertSee('Poli Uji');
    }

    public function test_jadwal_dokter_tanpa_poli_tidak_ditampilkan_dan_ditolak(): void
    {
        $poli = Poli::create(['nama_poli' => 'Poli Uji']);
        $jadwal = $this->jadwal($this->user('dokter'));
        $pasien = $this->user('pasien');

        $this->actingAs($pasien)->get('/pasien/daftar')
            ->assertOk()->assertDontSee('value="' . $jadwal->id . '" data-poli', false);

        $this->actingAs($pasien)->post('/pasien/daftar', [
            'id_pasien' => $pasien->id, 'id_poli' => $poli->id, 'id_jadwal' => $jadwal->id, 'keluhan' => 'x',
        ])->assertSessionHasErrors('id_jadwal');
        $this->assertSame(0, DaftarPoli::where('id_jadwal', $jadwal->id)->count());
    }

    public function test_pendaftaran_valid_memberi_nomor_antrian_berurutan(): void
    {
        $poli = Poli::create(['nama_poli' => 'Poli Uji']);
        $jadwal = $this->jadwal($this->user('dokter', $poli->id));
        $p1 = $this->user('pasien');
        $p2 = $this->user('pasien');

        foreach ([$p1, $p2] as $p) {
            $this->actingAs($p)->post('/pasien/daftar', [
                'id_pasien' => $p->id, 'id_poli' => $poli->id, 'id_jadwal' => $jadwal->id, 'keluhan' => 'x',
            ])->assertSessionHasNoErrors();
        }
        $this->assertSame([1, 2], DaftarPoli::where('id_jadwal', $jadwal->id)->orderBy('no_antrian')->pluck('no_antrian')->all());
    }

    public function test_jadwal_tidak_bisa_didaftarkan_ke_poli_yang_salah(): void
    {
        $poliA = Poli::create(['nama_poli' => 'A']);
        $poliB = Poli::create(['nama_poli' => 'B']);
        $jadwal = $this->jadwal($this->user('dokter', $poliA->id));
        $pasien = $this->user('pasien');

        $this->actingAs($pasien)->post('/pasien/daftar', [
            'id_pasien' => $pasien->id, 'id_poli' => $poliB->id, 'id_jadwal' => $jadwal->id, 'keluhan' => 'x',
        ])->assertSessionHasErrors('id_jadwal');
    }
}
