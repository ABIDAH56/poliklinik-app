<?php

/*
 * ============================  DRAFT UNTUK DITINJAU - BELUM AKTIF  ============================
 * File ini sengaja berada di database/migrations_review/ (bukan database/migrations/), sehingga
 * `php artisan migrate` TIDAK menjalankannya. Pindahkan ke database/migrations/ hanya setelah disetujui.
 *
 * Tujuan: mengganti ON DELETE CASCADE menjadi RESTRICT pada relasi yang membawa riwayat medis,
 * sehingga MySQL menolak penghapusan baris induk yang masih punya data turunan (termasuk dari SQL langsung
 * atau phpMyAdmin), bukan hanya dari aplikasi.
 *
 *   users.id_poli                    -> poli            (menghapus poli tak lagi menghapus dokter)
 *   jadwal_periksa.id_dokter         -> users           (menghapus dokter tak lagi menghapus jadwal)
 *   daftar_poli.id_pasien            -> users           (menghapus pasien tak lagi menghapus pendaftaran)
 *   daftar_poli.id_jadwal            -> jadwal_periksa  (menghapus jadwal tak lagi menghapus pendaftaran)
 *   periksa.id_daftar_poli           -> daftar_poli     (menghapus pendaftaran tak lagi menghapus pemeriksaan)
 *   detail_periksa.id_obat           -> obat            (menghapus obat tak lagi menghapus resep)
 *
 * Tidak diubah: detail_periksa.id_periksa -> periksa tetap CASCADE (detail resep bagian dari pemeriksaan
 * itu sendiri; pemeriksaan sudah terlindungi oleh RESTRICT di atasnya). ON UPDATE tidak diubah.
 *
 * Keamanan:
 *  - Tidak mengubah/menghapus baris data. Hanya definisi foreign key.
 *  - Sebelum perubahan apa pun, dicek dulu tidak ada baris yatim (nilai FK tanpa induk); jika ada, berhenti.
 *  - Idempoten: FK yang sudah RESTRICT dilewati, jadi aman dijalankan ulang jika proses terhenti di tengah.
 *  - DDL MySQL tidak transaksional (auto-commit): jika gagal di tengah, sebagian FK sudah berubah.
 *    Karena idempoten, cukup jalankan ulang setelah penyebabnya diperbaiki.
 *  - down() mengembalikan seluruhnya ke ON DELETE CASCADE (kondisi asli).
 * ===============================================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** [tabel anak, kolom FK, tabel induk]. Nama constraint: {tabel}_{kolom}_foreign (sudah dicek di MySQL). */
    private array $relasi = [
        ['users', 'id_poli', 'poli'],
        ['jadwal_periksa', 'id_dokter', 'users'],
        ['daftar_poli', 'id_pasien', 'users'],
        ['daftar_poli', 'id_jadwal', 'jadwal_periksa'],
        ['periksa', 'id_daftar_poli', 'daftar_poli'],
        ['detail_periksa', 'id_obat', 'obat'],
    ];

    public function up(): void
    {
        $this->terapkan('restrict');
    }

    public function down(): void
    {
        $this->terapkan('cascade');
    }

    private function terapkan(string $aturan): void
    {
        $this->pastikanTidakAdaBarisYatim();

        foreach ($this->relasi as [$tabel, $kolom, $induk]) {
            $nama = "{$tabel}_{$kolom}_foreign";
            // Pada `migrate --pretend` query pengecekan tidak dieksekusi (hasilnya kosong). Agar SQL yang
            // ditampilkan sesuai kenyataan, anggap FK saat ini berkebalikan dengan target.
            $saatIni = DB::connection()->pretending()
                ? ($aturan === 'restrict' ? 'cascade' : 'restrict')
                : $this->aturanSaatIni($tabel, $kolom);

            if ($this->sudahSesuai($saatIni, $aturan)) {
                continue; // idempoten
            }

            Schema::table($tabel, function (Blueprint $table) use ($nama, $kolom, $induk, $aturan, $saatIni) {
                if ($saatIni !== null) {
                    $table->dropForeign($nama);
                }
                $table->foreign($kolom, $nama)->references('id')->on($induk)->onDelete($aturan);
            });
        }
    }

    private function aturanSaatIni(string $tabel, string $kolom): ?string
    {
        foreach (Schema::getForeignKeys($tabel) as $fk) {
            if ($fk['columns'] === [$kolom]) {
                return strtolower((string) $fk['on_delete']);
            }
        }

        return null;
    }

    // Di MySQL, NO ACTION dan RESTRICT berperilaku sama.
    private function sudahSesuai(?string $saatIni, string $aturan): bool
    {
        return $aturan === 'restrict'
            ? in_array($saatIni, ['restrict', 'no action'], true)
            : $saatIni === 'cascade';
    }

    private function pastikanTidakAdaBarisYatim(): void
    {
        foreach ($this->relasi as [$tabel, $kolom, $induk]) {
            $yatim = DB::table($tabel)
                ->whereNotNull($kolom)
                ->whereNotIn($kolom, DB::table($induk)->select('id'))
                ->count();

            if ($yatim > 0) {
                throw new RuntimeException(
                    "Migration dibatalkan sebelum mengubah apa pun: {$tabel}.{$kolom} memiliki {$yatim} baris tanpa induk di {$induk}. "
                    . 'Rapikan data tersebut terlebih dahulu.'
                );
            }
        }
    }
};
