<?php

namespace App\Http\Controllers\Dokter;

use App\Http\Controllers\Controller;
use App\Models\JadwalPeriksa;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class JadwalPeriksaController extends Controller
{
    // Hari yang didukung (sama dengan enum kolom jadwal_periksa.hari).
    public const HARI = JadwalPeriksa::HARI;

    public function index(){
        $dokter = Auth::user();
        $jadwalPeriksas = JadwalPeriksa::where('id_dokter', $dokter->id)->urutHari()->get();
        return view('dokter.jadwal-periksa.index', compact('jadwalPeriksas'));
    }

    public function create(){
        return view('dokter.jadwal-periksa.create');
    }

    public function store(Request $request){
        $data = $this->validasi($request);

        JadwalPeriksa::create([
            'id_dokter' => Auth::id(),
            'hari' => $data['hari'],
            'jam_mulai' => $data['jam_mulai'],
            'jam_selesai' => $data['jam_selesai'],
        ]);

        return redirect()->route('jadwal-periksa.index')
            ->with('message', 'Data Berhasil di Simpan')
            ->with('type', 'success');
    }

    public function edit($id){
        $jadwalPeriksa = $this->jadwalMilikSaya($id);
        return view('dokter.jadwal-periksa.edit', compact('jadwalPeriksa'));
    }

    public function update(Request $request, string $id){
        // Ownership dicek lebih dulu (404 untuk jadwal dokter lain), lalu isian divalidasi.
        $this->jadwalMilikSaya($id);
        $data = $this->validasi($request, (int) $id);

        DB::transaction(function () use ($id, $data) {
            // Kunci baris jadwal: pendaftaran pasien juga mengunci baris ini, jadi tidak ada
            // pendaftaran yang "menyelinap" di antara pengecekan dan penyimpanan.
            $jadwalPeriksa = JadwalPeriksa::where('id_dokter', Auth::id())->lockForUpdate()->findOrFail($id);

            $berubah = $jadwalPeriksa->hari !== $data['hari']
                || $jadwalPeriksa->jam_mulai !== $data['jam_mulai']
                || $jadwalPeriksa->jam_selesai !== $data['jam_selesai'];

            if ($berubah && $jadwalPeriksa->daftarPolis()->exists()) {
                throw ValidationException::withMessages([
                    'hari' => 'Hari dan jam tidak dapat diubah karena jadwal ini sudah memiliki pendaftar. '
                        . 'Buat jadwal baru jika ingin jam yang berbeda.',
                ]);
            }

            if ($berubah) {
                $jadwalPeriksa->update($data);
            }
        });

        return redirect()->route('jadwal-periksa.index')
            ->with('message', 'Berhasil Melakukan Update Data')
            ->with('type', 'success');
    }

    public function destroy(string $id){
        $this->jadwalMilikSaya($id);

        $dihapus = DB::transaction(function () use ($id) {
            $jadwalPeriksa = JadwalPeriksa::where('id_dokter', Auth::id())->lockForUpdate()->findOrFail($id);

            // Pendaftar (dan riwayat pemeriksaan yang bergantung padanya) harus dilindungi.
            if ($jadwalPeriksa->daftarPolis()->exists()) {
                return false;
            }

            $jadwalPeriksa->delete();
            return true;
        });

        if (! $dihapus) {
            return redirect()->route('jadwal-periksa.index')
                ->with('error', 'Jadwal tidak dapat dihapus karena sudah memiliki pendaftar atau riwayat pemeriksaan.');
        }

        return redirect()->route('jadwal-periksa.index')
            ->with('message', 'Berhasil Melakukan Hapus Data')
            ->with('type', 'success');
    }

    // Hanya jadwal milik dokter yang login; jadwal dokter lain dijawab 404.
    private function jadwalMilikSaya($id): JadwalPeriksa
    {
        return JadwalPeriksa::where('id_dokter', Auth::id())->findOrFail($id);
    }

    /**
     * Validasi hari, format jam, urutan jam, dan bentrok dengan jadwal lain milik dokter yang sama.
     * Beberapa sesi pada hari yang sama diperbolehkan selama jamnya tidak saling tumpang tindih
     * (jam selesai sebuah sesi boleh sama dengan jam mulai sesi berikutnya).
     */
    private function validasi(Request $request, ?int $abaikanId = null): array
    {
        $data = $request->validate([
            'hari' => ['required', Rule::in(self::HARI)],
            'jam_mulai' => ['required', 'date_format:H:i,H:i:s'],
            'jam_selesai' => ['required', 'date_format:H:i,H:i:s'],
        ]);

        $mulai = Carbon::parse($data['jam_mulai'])->format('H:i:s');
        $selesai = Carbon::parse($data['jam_selesai'])->format('H:i:s');

        if ($selesai <= $mulai) {
            throw ValidationException::withMessages([
                'jam_selesai' => 'Jam selesai harus lebih besar dari jam mulai.',
            ]);
        }

        $bentrok = JadwalPeriksa::where('id_dokter', Auth::id())
            ->where('hari', $data['hari'])
            ->when($abaikanId, fn ($q) => $q->where('id', '!=', $abaikanId))
            ->where('jam_mulai', '<', $selesai)
            ->where('jam_selesai', '>', $mulai)
            ->first();

        if ($bentrok) {
            throw ValidationException::withMessages([
                'jam_mulai' => sprintf(
                    'Jadwal bentrok dengan jadwal %s %s - %s.',
                    $bentrok->hari,
                    substr($bentrok->jam_mulai, 0, 5),
                    substr($bentrok->jam_selesai, 0, 5)
                ),
            ]);
        }

        return ['hari' => $data['hari'], 'jam_mulai' => $mulai, 'jam_selesai' => $selesai];
    }
}
