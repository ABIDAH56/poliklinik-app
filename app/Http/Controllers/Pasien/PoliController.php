<?php

namespace App\Http\Controllers\Pasien;

use App\Http\Controllers\Controller;
use App\Models\DaftarPoli;
use App\Models\JadwalPeriksa;
use App\Models\Poli;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PoliController extends Controller
{
    public function get()
    {
        $user = Auth::user();
        $polis = Poli::all();
        $jadwal = JadwalPeriksa::tersedia()->with('dokter', 'dokter.poli')->get();

        return view('pasien.daftar', [
            'user' => $user,
            'polis' => $polis,
            'jadwals' => $jadwal,
        ]);
    }

    public function submit(Request $request)
    {
        // Identitas pasien selalu dari sesi login, bukan dari input form.
        $request->validate([
            'id_poli' => 'required|exists:poli,id',
            'id_jadwal' => 'required|exists:jadwal_periksa,id',
            'keluhan' => 'nullable|string',
        ]);

        $idPasien = Auth::id();

        $hasil = DB::transaction(function () use ($request, $idPasien) {
            // Kunci baris jadwal supaya pendaftaran bersamaan pada jadwal yang sama diproses berurutan
            // (mencegah pendaftaran ganda dan nomor antrean kembar).
            $jadwal = JadwalPeriksa::tersedia()
                ->whereKey($request->id_jadwal)
                ->whereHas('dokter', fn ($q) => $q->where('id_poli', $request->id_poli))
                ->lockForUpdate()
                ->first();

            if (! $jadwal) {
                return 'Jadwal tidak tersedia atau tidak sesuai dengan poli yang dipilih.';
            }

            // Pendaftaran yang belum diperiksa pada jadwal yang sama dianggap masih aktif.
            $sudahTerdaftar = DaftarPoli::where('id_pasien', $idPasien)
                ->where('id_jadwal', $jadwal->id)
                ->whereDoesntHave('periksas')
                ->exists();

            if ($sudahTerdaftar) {
                return 'Anda sudah terdaftar pada jadwal ini dan belum diperiksa.';
            }

            DaftarPoli::create([
                'id_pasien' => $idPasien,
                'id_jadwal' => $jadwal->id,
                'keluhan' => $request->keluhan,
                'no_antrian' => DaftarPoli::where('id_jadwal', $jadwal->id)->count() + 1,
            ]);

            return null;
        });

        if ($hasil !== null) {
            return redirect()->back()->withInput()->withErrors(['id_jadwal' => $hasil]);
        }

        return redirect()->back()->with('message', 'Berhasil Mendaftar ke Poli')->with('type', 'success');
    }
}
