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
use Tests\TestCase;

/**
 * Alur utama dari awal sampai akhir lewat route nyata (login/logout sungguhan, bukan actingAs):
 * admin menyiapkan data -> pasien registrasi -> dokter membuat jadwal -> pasien mendaftar ->
 * dokter memeriksa + resep + stok + biaya -> riwayat -> proteksi hapus -> logout.
 * Berjalan pada poliklinik_test (dijaga Tests\TestCase), data di-rollback.
 */
class AlurUtamaTest extends TestCase
{
    use DatabaseTransactions;

    private const PASSWORD = 'rahasia123';

    private function masuk(string $email): void
    {
        $this->post('/login', ['email' => $email, 'password' => self::PASSWORD]);
    }

    private function keluar(): void
    {
        $this->post('/logout')->assertRedirect(route('login'));
    }

    public function test_alur_lengkap_dari_login_sampai_riwayat(): void
    {
        // ---------- Admin: login, hak akses, siapkan data ----------
        User::create(['nama' => 'Admin', 'email' => 'admin@alur.test', 'password' => self::PASSWORD, 'role' => 'admin']);

        $this->post('/login', ['email' => 'admin@alur.test', 'password' => 'salah'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'admin@alur.test', 'password' => self::PASSWORD])->assertRedirect(route('admin.dashboard'));
        $this->get('/admin/dokter')->assertOk();
        $this->get('/dokter/dashboard')->assertForbidden();            // role lain ditolak
        $this->get('/pasien/dashboard')->assertForbidden();

        $this->post('/admin/polis', ['nama_poli' => 'Poli Gigi', 'keterangan' => 'Gigi dan mulut'])->assertSessionHas('success');
        $poli = Poli::where('nama_poli', 'Poli Gigi')->firstOrFail();

        $this->post('/admin/dokter', [
            'nama' => 'drg. Sari', 'alamat' => 'Jl. Mawar 1', 'no_ktp' => '3201010101010001', 'no_hp' => '081200000001',
            'id_poli' => (string) $poli->id, 'email' => 'dokter@alur.test', 'password' => self::PASSWORD,
        ])->assertSessionHas('success');
        $dokter = User::where('email', 'dokter@alur.test')->firstOrFail();
        $this->assertSame('dokter', $dokter->role);
        $this->assertSame($poli->id, $dokter->id_poli);

        $this->post('/admin/obat', ['nama_obat' => 'Amoxicillin', 'kemasan' => 'strip', 'harga' => 20000, 'stok' => 5])
            ->assertSessionHas('type', 'success');
        $obat = Obat::where('nama_obat', 'Amoxicillin')->firstOrFail();
        $this->assertSame('strip', $obat->kemasan);

        $this->post('/admin/obat/' . $obat->id . '/tambah-stok', ['jumlah' => 3])->assertSessionHas('type', 'success');
        $this->post('/admin/obat/' . $obat->id . '/kurangi-stok', ['jumlah' => 1])->assertSessionHas('type', 'success');
        $this->assertSame(7, $obat->fresh()->stok);
        $this->post('/admin/obat/' . $obat->id . '/kurangi-stok', ['jumlah' => 100])->assertSessionHas('type', 'error');
        $this->assertSame(7, $obat->fresh()->stok);
        $this->keluar();
        $this->get('/admin/dashboard')->assertRedirect(route('login'));   // setelah logout dialihkan ke login

        // ---------- Pasien: registrasi + no_rm + login ----------
        $this->post('/register', [
            'nama' => 'Budi', 'alamat' => 'Jl. Melati 2', 'no_ktp' => '3201010101010002', 'no_hp' => '081200000002',
            'email' => 'pasien@alur.test', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD,
        ])->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $pasien = User::where('email', 'pasien@alur.test')->firstOrFail();
        $this->assertSame('pasien', $pasien->role);
        $this->assertSame(User::formatNoRm($pasien->id), $pasien->no_rm);

        // ---------- Dokter: login + buat jadwal ----------
        $this->masuk('dokter@alur.test');
        $this->post('/dokter/jadwal-periksa', ['hari' => 'Senin', 'jam_mulai' => '08:00', 'jam_selesai' => '12:00', 'status' => 1])
            ->assertSessionHasNoErrors()->assertSessionHas('type', 'success');
        $jadwal = JadwalPeriksa::where('id_dokter', $dokter->id)->firstOrFail();
        $this->post('/dokter/jadwal-periksa', ['hari' => 'Senin', 'jam_mulai' => '11:00', 'jam_selesai' => '13:00', 'status' => 1])
            ->assertSessionHasErrors('jam_mulai');                         // bentrok ditolak
        $this->keluar();

        // ---------- Pasien: login, lihat jadwal, daftar ----------
        $this->masuk('pasien@alur.test');
        $this->get('/pasien/dashboard')->assertOk()->assertSee('Poli Gigi');
        $this->get('/pasien/daftar')->assertOk()->assertSee('value="' . $jadwal->id . '" data-poli="' . $poli->id . '"', false);
        $this->post('/pasien/daftar', ['id_poli' => $poli->id, 'id_jadwal' => $jadwal->id, 'keluhan' => 'Sakit gigi geraham'])
            ->assertSessionHas('type', 'success');
        $this->post('/pasien/daftar', ['id_poli' => $poli->id, 'id_jadwal' => $jadwal->id, 'keluhan' => 'lagi'])
            ->assertSessionHasErrors('id_jadwal');                         // pendaftaran ganda ditolak
        $antrean = DaftarPoli::where('id_pasien', $pasien->id)->firstOrFail();
        $this->assertSame(1, $antrean->no_antrian);
        $this->assertSame(1, DaftarPoli::where('id_jadwal', $jadwal->id)->count());
        $this->keluar();

        // ---------- Dokter: antrean, periksa, resep, stok, biaya ----------
        $this->masuk('dokter@alur.test');
        $this->get('/dokter/periksa-pasien')->assertOk()->assertSee('Budi')->assertSee('Sakit gigi geraham');
        $this->get('/dokter/periksa-pasien/' . $antrean->id)->assertOk()->assertSee('150.000');

        $this->post('/dokter/periksa-pasien', [
            'id_daftar_poli' => $antrean->id, 'obat_json' => json_encode([$obat->id]), 'catatan' => '', 'biaya_periksa' => 1,
        ])->assertRedirect(route('periksa-pasien.index'))->assertSessionHas('success');

        $periksa = Periksa::where('id_daftar_poli', $antrean->id)->firstOrFail();
        $this->assertNull($periksa->catatan);                                   // catatan kosong diterima
        $this->assertSame(20000 + PeriksaPasienController::BIAYA_PEMERIKSAAN, $periksa->biaya_periksa);
        $this->assertSame(6, $obat->fresh()->stok);                             // 7 -> 6
        $this->assertSame(1, DetailPeriksa::where('id_periksa', $periksa->id)->where('id_obat', $obat->id)->count());
        $this->post('/dokter/periksa-pasien', ['id_daftar_poli' => $antrean->id, 'obat_json' => '[]'])
            ->assertSessionHas('error');                                        // tidak bisa diperiksa dua kali
        $this->assertSame(1, Periksa::where('id_daftar_poli', $antrean->id)->count());
        $this->assertSame(6, $obat->fresh()->stok);

        // ---------- Riwayat ----------
        $this->get('/dokter/riwayat-pasien')->assertOk()->assertSee('Budi')->assertSee('170.000');
        $this->get('/dokter/riwayat-pasien/' . $periksa->id)->assertOk()
            ->assertSee('Tidak ada catatan')->assertSee('Amoxicillin')->assertSee('WIB');
        $this->keluar();

        // ---------- Admin: data berriwayat tidak bisa terhapus ----------
        $this->masuk('admin@alur.test');
        $this->delete('/admin/obat/' . $obat->id)->assertSessionHas('type', 'error');
        $this->delete('/admin/dokter/' . $dokter->id)->assertSessionHas('error');
        $this->delete('/admin/pasien/' . $pasien->id)->assertSessionHas('error');
        $this->delete('/admin/polis/' . $poli->id)->assertSessionHas('error');
        $this->assertSame(1, Obat::whereKey($obat->id)->count());
        $this->assertSame(1, User::whereKey($dokter->id)->count());
        $this->assertSame(1, User::whereKey($pasien->id)->count());
        $this->assertSame(1, Poli::whereKey($poli->id)->count());
        $this->assertSame(1, Periksa::whereKey($periksa->id)->count());
        $this->assertSame(1, DetailPeriksa::where('id_periksa', $periksa->id)->count());
        $this->assertSame(User::formatNoRm($pasien->id), $pasien->fresh()->no_rm);
        $this->keluar();
    }
}
