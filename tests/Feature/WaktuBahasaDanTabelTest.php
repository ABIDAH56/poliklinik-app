<?php

namespace Tests\Feature;

use App\Models\DaftarPoli;
use App\Models\JadwalPeriksa;
use App\Models\Periksa;
use App\Models\Poli;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Berjalan pada poliklinik_test (dijaga Tests\TestCase), data di-rollback.
class WaktuBahasaDanTabelTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $role, ?int $idPoli = null): User
    {
        return User::create([
            'nama' => ucfirst($role), 'email' => uniqid($role) . '@test.local', 'password' => 'secret', 'role' => $role, 'id_poli' => $idPoli,
        ]);
    }

    private function periksaPada(string $waktuUtc): array
    {
        $dokter = $this->user('dokter', Poli::create(['nama_poli' => 'Poli Uji'])->id);
        $pasien = User::buatPasien(['nama' => 'Pasien Waktu', 'email' => uniqid('p') . '@test.local', 'password' => 'secret']);
        $jadwal = JadwalPeriksa::create(['id_dokter' => $dokter->id, 'hari' => 'Senin', 'jam_mulai' => '08:00', 'jam_selesai' => '09:00']);
        $antrean = DaftarPoli::create(['id_pasien' => $pasien->id, 'id_jadwal' => $jadwal->id, 'keluhan' => 'x', 'no_antrian' => 1]);

        return [$dokter, Periksa::create(['id_daftar_poli' => $antrean->id, 'tgl_periksa' => $waktuUtc, 'catatan' => null, 'biaya_periksa' => 150000])];
    }

    // ---------- Waktu: disimpan UTC, ditampilkan WIB ----------

    public function test_waktu_pemeriksaan_ditampilkan_dalam_wib_tanpa_mengubah_data_tersimpan(): void
    {
        // 17:30 UTC = 00:30 WIB hari berikutnya: tanggal harus ikut bergeser pada tampilan.
        [$dokter, $periksa] = $this->periksaPada('2026-10-17 17:30:00');

        $this->actingAs($dokter)->get("/dokter/riwayat-pasien/{$periksa->id}")->assertOk()->assertSee('18/10/2026 00:30 WIB');
        $this->actingAs($dokter)->get('/dokter/riwayat-pasien')->assertOk()->assertSee('18/10/2026')->assertDontSee('17/10/2026');

        $this->assertSame('2026-10-17 17:30:00', Periksa::whereKey($periksa->id)->value('tgl_periksa'));
        $this->assertSame('2026-10-18 00:30:00', $periksa->fresh()->tgl_periksa_lokal->format('Y-m-d H:i:s'));
    }

    public function test_waktu_siang_hari_bergeser_tujuh_jam(): void
    {
        [$dokter, $periksa] = $this->periksaPada('2026-10-17 05:00:00');

        $this->actingAs($dokter)->get("/dokter/riwayat-pasien/{$periksa->id}")->assertSee('17/10/2026 12:00 WIB');
    }

    // ---------- Pesan validasi berbahasa Indonesia ----------

    public function test_pesan_validasi_berbahasa_indonesia(): void
    {
        $admin = $this->user('admin');

        $r = $this->actingAs($admin)->post('/admin/obat', ['nama_obat' => str_repeat('a', 256), 'kemasan' => str_repeat('k', 36), 'harga' => 'x', 'stok' => '']);

        $r->assertSessionHasErrors(['nama_obat', 'kemasan', 'harga', 'stok']);
        $e = session('errors');
        $this->assertSame('Nama obat tidak boleh lebih dari 255 karakter.', $e->first('nama_obat'));
        $this->assertSame('Kemasan tidak boleh lebih dari 35 karakter.', $e->first('kemasan'));
        $this->assertSame('Harga harus berupa bilangan bulat.', $e->first('harga'));
        $this->assertSame('Stok wajib diisi.', $e->first('stok'));
    }

    // ---------- Skema: tabel yang dibutuhkan instalasi baru ----------

    public function test_migration_membuat_tabel_sessions_dan_password_reset_tokens(): void
    {
        $this->assertTrue(Schema::hasTable('sessions'));
        $this->assertTrue(Schema::hasColumns('sessions', ['id', 'user_id', 'ip_address', 'user_agent', 'payload', 'last_activity']));
        $this->assertTrue(Schema::hasTable('password_reset_tokens'));
        $this->assertTrue(Schema::hasColumns('password_reset_tokens', ['email', 'token', 'created_at']));
    }
}
