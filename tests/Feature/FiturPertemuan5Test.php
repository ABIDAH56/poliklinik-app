<?php

namespace Tests\Feature;

use App\Models\DaftarPoli;
use App\Models\JadwalPeriksa;
use App\Models\Obat;
use App\Models\Periksa;
use App\Models\Poli;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

// Tes fitur Tugas Pertemuan 5: riwayat pendaftaran, tanggal periksa, dan stok obat pada form periksa.
class FiturPertemuan5Test extends TestCase
{
    use DatabaseTransactions;

    private Poli $poli;

    protected function setUp(): void
    {
        parent::setUp();
        $this->poli = Poli::create(['nama_poli' => 'Poli Uji']);
    }

    private function user(string $role): User
    {
        return User::create([
            'nama' => ucfirst($role) . uniqid(), 'email' => uniqid($role) . '@test.local',
            'password' => 'secret', 'role' => $role, 'id_poli' => $role === 'dokter' ? $this->poli->id : null,
        ]);
    }

    private function antrean(User $dokter, User $pasien, string $keluhan = 'sakit'): DaftarPoli
    {
        $jadwal = JadwalPeriksa::create([
            'id_dokter' => $dokter->id, 'hari' => 'Senin', 'jam_mulai' => '08:00', 'jam_selesai' => '10:00',
        ]);

        return DaftarPoli::create([
            'id_pasien' => $pasien->id, 'id_jadwal' => $jadwal->id, 'keluhan' => $keluhan, 'no_antrian' => 1,
        ]);
    }

    private function obat(int $stok): Obat
    {
        return Obat::create(['nama_obat' => 'Obat ' . uniqid(), 'kemasan' => 'strip', 'harga' => 10000, 'stok' => $stok]);
    }

    public function test_riwayat_pendaftaran_hanya_menampilkan_milik_pasien_yang_login(): void
    {
        $dokter = $this->user('dokter');
        $pasienA = $this->user('pasien');
        $pasienB = $this->user('pasien');
        $this->antrean($dokter, $pasienA, 'keluhan-milik-A');
        $this->antrean($dokter, $pasienB, 'keluhan-milik-B');

        $this->actingAs($pasienA)->get('/pasien/daftar')
            ->assertOk()
            ->assertSee('Riwayat Pendaftaran Saya')
            ->assertSee('keluhan-milik-A')
            ->assertDontSee('keluhan-milik-B');
    }

    public function test_tanggal_periksa_disimpan_dalam_zona_waktu_aplikasi(): void
    {
        $dokter = $this->user('dokter');
        $antrean = $this->antrean($dokter, $this->user('pasien'));

        $this->actingAs($dokter)->post('/dokter/periksa-pasien', [
            'id_daftar_poli' => $antrean->id, 'obat_json' => '[]', 'tgl_periksa' => '2026-03-10T09:30',
        ])->assertSessionHasNoErrors();

        $diharapkan = Carbon::parse('2026-03-10 09:30', config('app.display_timezone'))
            ->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
        $periksa = Periksa::where('id_daftar_poli', $antrean->id)->firstOrFail();

        $this->assertSame($diharapkan, $periksa->getRawOriginal('tgl_periksa'));
    }

    public function test_tanggal_periksa_masa_depan_ditolak_tanpa_mengubah_stok(): void
    {
        $dokter = $this->user('dokter');
        $antrean = $this->antrean($dokter, $this->user('pasien'));
        $obat = $this->obat(5);

        $this->actingAs($dokter)->post('/dokter/periksa-pasien', [
            'id_daftar_poli' => $antrean->id, 'obat_json' => json_encode([$obat->id]), 'tgl_periksa' => '2099-01-01T00:00',
        ])->assertSessionHas('error');

        $this->assertSame(0, Periksa::where('id_daftar_poli', $antrean->id)->count());
        $this->assertSame(5, $obat->fresh()->stok);
    }

    public function test_form_periksa_menampilkan_stok_dan_menonaktifkan_obat_habis(): void
    {
        $dokter = $this->user('dokter');
        $antrean = $this->antrean($dokter, $this->user('pasien'));
        $ada = $this->obat(7);
        $habis = $this->obat(0);

        $html = $this->actingAs($dokter)->get('/dokter/periksa-pasien/' . $antrean->id)
            ->assertOk()->assertSee('stok: 7')->assertSee('stok habis')->getContent();

        $this->assertMatchesRegularExpression('/<option value="' . $habis->id . '"[^>]*\bdisabled\b/', $html);
        $this->assertDoesNotMatchRegularExpression('/<option value="' . $ada->id . '"[^>]*\bdisabled\b/', $html);
    }
}
