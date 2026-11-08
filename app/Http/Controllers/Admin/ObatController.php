<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ObatRequest;
use App\Models\Obat;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ObatController extends Controller
{
    // READ: daftar semua obat + jumlah pemakaiannya di resep (detail_periksa)
    public function index()
    {
        $obats = Obat::withCount('detailPeriksas')->orderBy('nama_obat')->get();
        return view('admin.obat.index', compact('obats'));
    }

    // READ: detail satu obat beserta riwayat pemakaiannya
    public function show(string $id)
    {
        $obat = Obat::withCount('detailPeriksas')->findOrFail($id);

        // Eager load relasi detail_periksa -> periksa -> daftar_poli -> pasien (hindari N+1 query)
        $pemakaian = $obat->detailPeriksas()
            ->with('periksa.daftarPoli.pasien')
            ->latest()
            ->take(10)
            ->get();

        return view('admin.obat.show', compact('obat', 'pemakaian'));
    }

    public function create()
    {
        return view('admin.obat.create');
    }

    // CREATE: validasi dilakukan otomatis oleh ObatRequest sebelum method ini dijalankan
    public function store(ObatRequest $request)
    {
        Obat::create($request->validated());

        return redirect()->route('obat.index')
            ->with('message', 'Data Obat Berhasil dibuat')
            ->with('type', 'success');
    }

    // UPDATE (form): data lama dikirim ke view agar input terisi
    public function edit(string $id)
    {
        $obat = Obat::findOrFail($id);
        return view('admin.obat.edit', compact('obat'));
    }

    // UPDATE (simpan): aturan validasi sama dengan create (ObatRequest)
    public function update(ObatRequest $request, string $id)
    {
        $obat = Obat::findOrFail($id);
        $obat->update($request->validated());

        return redirect()->route('obat.index')
            ->with('message', 'Data Obat berhasil di edit')
            ->with('type', 'success');
    }

    // DELETE: obat yang sudah dipakai di resep (detail_periksa) tidak boleh dihapus
    public function destroy(string $id)
    {
        $obat = Obat::findOrFail($id);
        $pesanDitolak = "Obat \"{$obat->nama_obat}\" tidak dapat dihapus karena sudah dipakai pada riwayat pemeriksaan. Kelola stoknya saja.";

        try {
            // Lapis 1 (aplikasi): cek relasi dulu. Baris obat dikunci agar penyimpanan pemeriksaan
            // yang berjalan bersamaan tidak bisa menyelinap di antara pengecekan dan penghapusan.
            $dihapus = DB::transaction(function () use ($id) {
                $terkunci = Obat::lockForUpdate()->findOrFail($id);
                if ($terkunci->detailPeriksas()->exists()) {
                    return false;
                }
                $terkunci->delete();
                return true;
            });
        } catch (QueryException $e) {
            // Lapis 2 (database): FK detail_periksa.id_obat bersifat RESTRICT. Jika tetap terjadi
            // pelanggaran foreign key (SQLSTATE 23000), tampilkan pesan jelas, bukan error mentah.
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            $dihapus = false;
        }

        if (! $dihapus) {
            return redirect()->route('obat.index')
                ->with('message', $pesanDitolak)
                ->with('type', 'error');
        }

        return redirect()->route('obat.index')
            ->with('message', 'Data Obat Berhasil di Hapus')
            ->with('type', 'success');
    }

    // ── Manajemen Stok ──────────────────────────────────────

    public function tambahStok(Request $request, string $id)
    {
        $request->validate([
            'jumlah' => 'required|integer|min:1',
        ]);

        $obat = Obat::findOrFail($id);
        $obat->stok += $request->jumlah;
        $obat->save();

        return redirect()->route('obat.index')
            ->with('message', "Stok {$obat->nama_obat} berhasil ditambah sebanyak {$request->jumlah}")
            ->with('type', 'success');
    }

    public function kurangiStok(Request $request, string $id)
    {
        $request->validate([
            'jumlah' => 'required|integer|min:1',
        ]);

        $obat = Obat::findOrFail($id);

        // Validasi stok tidak boleh minus
        if ($request->jumlah > $obat->stok) {
            return redirect()->back()
                ->with('message', "Stok {$obat->nama_obat} tidak mencukupi! Stok tersisa: {$obat->stok}")
                ->with('type', 'error');
        }

        $obat->stok -= $request->jumlah;
        $obat->save();

        return redirect()->route('obat.index')
            ->with('message', "Stok {$obat->nama_obat} berhasil dikurangi sebanyak {$request->jumlah}")
            ->with('type', 'success');
    }
}