<?php

namespace Tests\Feature;

use App\Models\DaftarPoli;
use App\Models\JadwalPeriksa;
use App\Models\Obat;
use App\Models\Periksa;
use App\Models\Poli;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regresi untuk empat kolom yang dulu berbeda antara migration dan database kerja:
 * periksa.catatan, periksa.tgl_periksa, obat.kemasan, obat.nama_obat.
 * Berjalan pada poliklinik_test, yang skemanya dibangun dari migration.
 */
class SkemaDanValidasiObatPeriksaTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->user('admin');
    }

    private function user(string $role, ?int $idPoli = null): User
    {
        return User::create([
            'nama' => ucfirst($role), 'email' => uniqid($role) . '@test.local', 'password' => 'secret',
            'role' => $role, 'id_poli' => $idPoli,
        ]);
    }

    private function kolom(string $tabel, string $nama): array
    {
        return collect(Schema::getColumns($tabel))->firstWhere('name', $nama);
    }

    /** @return array{0: User, 1: DaftarPoli} */
    private function antreanBaru(?User $dokter = null): array
    {
        $dokter ??= $this->user('dokter', Poli::create(['nama_poli' => 'Poli Uji'])->id);
        $jadwal = JadwalPeriksa::create(['id_dokter' => $dokter->id, 'hari' => 'Senin', 'jam_mulai' => '08:00', 'jam_selesai' => '09:00']);
        $pasien = User::buatPasien(['nama' => 'Pasien', 'email' => uniqid('p') . '@test.local', 'password' => 'secret']);

        return [$dokter, DaftarPoli::create(['id_pasien' => $pasien->id, 'id_jadwal' => $jadwal->id, 'keluhan' => 'x', 'no_antrian' => 1])];
    }

    private function dataObat(array $extra = []): array
    {
        return $extra + ['nama_obat' => 'Obat Uji', 'kemasan' => 'strip', 'harga' => 1000, 'stok' => 5];
    }

    // ---------- Skema hasil migration sesuai definisi yang ditetapkan ----------

    public function test_skema_hasil_migration_sesuai_empat_definisi(): void
    {
        $namaObat = $this->kolom('obat', 'nama_obat');
        $kemasan = $this->kolom('obat', 'kemasan');
        $catatan = $this->kolom('periksa', 'catatan');
        $tgl = $this->kolom('periksa', 'tgl_periksa');

        $this->assertSame('varchar(255)', $namaObat['type']);
        $this->assertFalse($namaObat['nullable']);
        $this->assertSame('varchar(35)', $kemasan['type']);
        $this->assertTrue($kemasan['nullable']);
        $this->assertSame('text', $catatan['type_name']);
        $this->assertTrue($catatan['nullable']);
        $this->assertSame('datetime', $tgl['type_name']);
        $this->assertFalse($tgl['nullable']);
    }

    // ---------- 1. Pemeriksaan dengan catatan kosong ----------

    public function test_pemeriksaan_dapat_disimpan_dengan_catatan_kosong(): void
    {
        foreach ([['catatan' => ''], ['catatan' => '   '], []] as $variasi) {
            [$dokter, $antrean] = $this->antreanBaru();

            $this->actingAs($dokter)->post('/dokter/periksa-pasien', $variasi + [
                'id_daftar_poli' => $antrean->id, 'obat_json' => '[]',
            ])->assertSessionHas('success')->assertSessionMissing('error');

            $this->assertNull(Periksa::where('id_daftar_poli', $antrean->id)->firstOrFail()->catatan);
        }
    }

    public function test_riwayat_menampilkan_pemeriksaan_tanpa_catatan(): void
    {
        [$dokter, $antrean] = $this->antreanBaru();
        $this->actingAs($dokter)->post('/dokter/periksa-pasien', ['id_daftar_poli' => $antrean->id, 'obat_json' => '[]', 'catatan' => '']);
        $periksa = Periksa::where('id_daftar_poli', $antrean->id)->firstOrFail();

        $this->actingAs($dokter)->get("/dokter/riwayat-pasien/{$periksa->id}")->assertOk()->assertSee('Tidak ada catatan');
    }

    // ---------- 2. Kemasan obat opsional ----------

    public function test_kemasan_boleh_kosong_saat_membuat_obat(): void
    {
        foreach ([['kemasan' => ''], ['kemasan' => '   '], []] as $i => $variasi) {
            $nama = "Obat Tanpa Kemasan {$i}";
            $data = $this->dataObat(['nama_obat' => $nama]);
            unset($data['kemasan']);

            $this->actingAs($this->admin)->post('/admin/obat', $variasi + $data)
                ->assertSessionHasNoErrors()->assertSessionHas('type', 'success');

            $this->assertNull(Obat::where('nama_obat', $nama)->firstOrFail()->kemasan);
        }
    }

    public function test_kemasan_boleh_dikosongkan_saat_mengedit_obat(): void
    {
        $obat = Obat::create($this->dataObat(['kemasan' => 'botol']));

        $this->actingAs($this->admin)->put("/admin/obat/{$obat->id}", $this->dataObat(['kemasan' => '']))
            ->assertSessionHasNoErrors()->assertSessionHas('type', 'success');

        $this->assertNull($obat->fresh()->kemasan);
        $this->actingAs($this->admin)->get('/admin/obat')->assertOk();
        $this->actingAs($this->admin)->get("/admin/obat/{$obat->id}/edit")->assertOk();
    }

    // ---------- 3. Jam pada tgl_periksa tersimpan ----------

    public function test_jam_pada_tgl_periksa_tersimpan_dan_tidak_hilang(): void
    {
        [$dokter, $antrean] = $this->antreanBaru();
        $this->travelTo('2026-10-17 15:30:45');

        $this->actingAs($dokter)->post('/dokter/periksa-pasien', ['id_daftar_poli' => $antrean->id, 'obat_json' => '[]'])
            ->assertSessionHas('success');

        $this->assertSame('2026-10-17 15:30:45', Periksa::where('id_daftar_poli', $antrean->id)->value('tgl_periksa'));
    }

    public function test_riwayat_pada_hari_yang_sama_terurut_menurut_jam(): void
    {
        [$dokter, $pagi] = $this->antreanBaru();
        [, $sore] = $this->antreanBaru($dokter);

        $this->travelTo('2026-10-17 09:00:00');
        $this->actingAs($dokter)->post('/dokter/periksa-pasien', ['id_daftar_poli' => $pagi->id, 'obat_json' => '[]']);
        $this->travelTo('2026-10-17 15:00:00');
        $this->actingAs($dokter)->post('/dokter/periksa-pasien', ['id_daftar_poli' => $sore->id, 'obat_json' => '[]']);

        $urut = Periksa::whereIn('id_daftar_poli', [$pagi->id, $sore->id])->orderBy('tgl_periksa', 'desc')->pluck('id_daftar_poli')->all();

        $this->assertSame([$sore->id, $pagi->id], $urut);
    }

    // ---------- 4. nama_obat dibatasi 255 oleh validasi ----------

    public function test_nama_obat_lebih_dari_255_karakter_ditolak_validasi_bukan_error_database(): void
    {
        $panjang = str_repeat('a', 256);
        $obat = Obat::create($this->dataObat(['nama_obat' => 'Asli']));
        $sebelum = Obat::count();

        $this->actingAs($this->admin)->post('/admin/obat', $this->dataObat(['nama_obat' => $panjang]))
            ->assertSessionHasErrors('nama_obat');
        $this->actingAs($this->admin)->put("/admin/obat/{$obat->id}", $this->dataObat(['nama_obat' => $panjang]))
            ->assertSessionHasErrors('nama_obat');

        $this->assertSame($sebelum, Obat::count());
        $this->assertSame('Asli', $obat->fresh()->nama_obat);
    }

    public function test_nama_obat_tepat_255_karakter_diterima(): void
    {
        $nama255 = str_repeat('b', 255);

        $this->actingAs($this->admin)->post('/admin/obat', $this->dataObat(['nama_obat' => $nama255]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Obat::where('nama_obat', $nama255)->count());
    }

    public function test_nama_obat_wajib_diisi(): void
    {
        $this->actingAs($this->admin)->post('/admin/obat', $this->dataObat(['nama_obat' => '']))
            ->assertSessionHasErrors('nama_obat');
    }

    // ---------- 5. kemasan dibatasi 35 oleh validasi ----------

    public function test_kemasan_lebih_dari_35_karakter_ditolak_validasi(): void
    {
        $panjang = str_repeat('k', 36);
        $obat = Obat::create($this->dataObat(['kemasan' => 'strip']));
        $sebelum = Obat::count();

        $this->actingAs($this->admin)->post('/admin/obat', $this->dataObat(['kemasan' => $panjang]))
            ->assertSessionHasErrors('kemasan');
        $this->actingAs($this->admin)->put("/admin/obat/{$obat->id}", $this->dataObat(['kemasan' => $panjang]))
            ->assertSessionHasErrors('kemasan');

        $this->assertSame($sebelum, Obat::count());
        $this->assertSame('strip', $obat->fresh()->kemasan);
    }

    public function test_kemasan_tepat_35_karakter_diterima(): void
    {
        $kemasan35 = str_repeat('k', 35);

        $this->actingAs($this->admin)->post('/admin/obat', $this->dataObat(['kemasan' => $kemasan35]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Obat::where('kemasan', $kemasan35)->count());
    }
}
