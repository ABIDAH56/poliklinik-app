<?php

namespace App\Http\Controllers\Dokter;

use App\Exceptions\PemeriksaanDitolak;
use App\Http\Controllers\Controller;
use App\Models\DaftarPoli;
use App\Models\DetailPeriksa;
use App\Models\Obat;
use App\Models\Periksa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PeriksaPasienController extends Controller
{
    // Biaya jasa pemeriksaan, ditambahkan ke total harga obat.
    public const BIAYA_PEMERIKSAAN = 150000;

    public function index()
    {
        $dokterId = Auth::id();

        $daftarPasien = DaftarPoli::with(['pasien', 'jadwalPeriksa', 'periksas'])
            ->whereHas('jadwalPeriksa', function ($query) use ($dokterId) {
                $query->where('id_dokter', $dokterId);
            })
            ->orderBy('no_antrian')
            ->get();

        return view('dokter.periksa-pasien.index', compact('daftarPasien'));
    }

    public function create($id)
    {
        $daftarPoli = $this->antreanMilikDokter($id)->firstOrFail();

        if ($daftarPoli->periksas()->exists()) {
            return redirect()->route('periksa-pasien.index')
                ->with('error', 'Pasien ini sudah diperiksa.');
        }

        $obats = Obat::all();
        $biayaPemeriksaan = self::BIAYA_PEMERIKSAAN;

        return view('dokter.periksa-pasien.create', compact('obats', 'id', 'biayaPemeriksaan'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_daftar_poli' => 'required|integer',
            'obat_json' => 'nullable|string',
            'catatan' => 'nullable|string',
        ]);

        // Daftar obat boleh kosong. Harga dan total tidak diambil dari browser.
        $obatIds = [];
        if ($request->filled('obat_json')) {
            $decoded = json_decode($request->obat_json, true);
            if (! is_array($decoded)) {
                return $this->gagal('Data obat tidak valid.');
            }
            foreach ($decoded as $id) {
                if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
                    return $this->gagal('Data obat tidak valid.');
                }
                $obatIds[] = (int) $id;
            }
            $obatIds = array_values(array_unique($obatIds));
            sort($obatIds); // urutan kunci konsisten agar tidak terjadi deadlock
        }

        $antrean = $this->antreanMilikDokter($request->id_daftar_poli)->firstOrFail();

        try {
            DB::transaction(function () use ($request, $antrean, $obatIds) {
                // Kunci baris antrean: dua request bersamaan tidak bisa sama-sama lolos cek "sudah diperiksa".
                $antrean = DaftarPoli::whereKey($antrean->id)->lockForUpdate()->firstOrFail();
                if ($antrean->periksas()->exists()) {
                    throw new PemeriksaanDitolak('Pasien ini sudah diperiksa.');
                }

                $obats = Obat::whereIn('id', $obatIds)->orderBy('id')->lockForUpdate()->get();
                if ($obats->count() !== count($obatIds)) {
                    throw new PemeriksaanDitolak('Ada obat yang tidak ditemukan.');
                }
                foreach ($obats as $obat) {
                    if ($obat->stok < 1) {
                        throw new PemeriksaanDitolak("Stok obat \"{$obat->nama_obat}\" tidak mencukupi. Resep tidak dapat disimpan.");
                    }
                }

                $periksa = Periksa::create([
                    'id_daftar_poli' => $antrean->id,
                    'tgl_periksa' => now(),
                    'catatan' => $request->catatan,
                    'biaya_periksa' => $obats->sum('harga') + self::BIAYA_PEMERIKSAAN,
                ]);

                foreach ($obats as $obat) {
                    $obat->decrement('stok', 1);
                    DetailPeriksa::create([
                        'id_periksa' => $periksa->id,
                        'id_obat' => $obat->id,
                    ]);
                }
            });
        } catch (PemeriksaanDitolak $e) {
            return $this->gagal($e->getMessage());
        }

        return redirect()->route('periksa-pasien.index')
            ->with('success', 'Data periksa berhasil disimpan.');
    }

    // Antrean hanya yang jadwalnya milik dokter yang sedang login.
    private function antreanMilikDokter($id)
    {
        return DaftarPoli::whereKey($id)->whereHas('jadwalPeriksa', function ($query) {
            $query->where('id_dokter', Auth::id());
        });
    }

    private function gagal(string $pesan)
    {
        return redirect()->back()->withInput()->with('error', $pesan);
    }
}
