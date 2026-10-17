<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Obat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ObatController extends Controller
{
    public function index()
    {
        $obats = Obat::all();
        return view('admin.obat.index', compact('obats'));
    }

    public function create()
    {
        return view('admin.obat.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_obat' => 'required|string|max:255',
            'kemasan'   => 'nullable|string|max:35',
            'harga'     => 'required|integer',
            'stok'      => 'required|integer|min:0',
        ]);

        Obat::create([
            'nama_obat' => $request->nama_obat,
            'kemasan'   => $request->kemasan,
            'harga'     => $request->harga,
            'stok'      => $request->stok,
        ]);

        return redirect()->route('obat.index')
            ->with('message', 'Data Obat Berhasil dibuat')
            ->with('type', 'success');
    }

    public function edit(string $id)
    {
        $obat = Obat::findOrFail($id);
        return view('admin.obat.edit')->with([
            'obat' => $obat
        ]);
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'nama_obat' => 'required|string|max:255',
            'kemasan'   => 'nullable|string|max:35',
            'harga'     => 'required|integer',
            'stok'      => 'required|integer|min:0',
        ]);

        $obat = Obat::findOrFail($id);
        $obat->update([
            'nama_obat' => $request->nama_obat,
            'kemasan'   => $request->kemasan,
            'harga'     => $request->harga,
            'stok'      => $request->stok,
        ]);

        return redirect()->route('obat.index')
            ->with('message', 'Data Obat berhasil di edit')
            ->with('type', 'success');
    }

    public function destroy(string $id)
    {
        $obat = Obat::findOrFail($id);

        // detail_periksa.id_obat memakai ON DELETE CASCADE: menghapus obat yang pernah diresepkan akan
        // menghilangkan daftar obat pada riwayat pemeriksaan. Karena itu ditolak di server.
        // Baris obat dikunci agar penyimpanan pemeriksaan yang berjalan bersamaan (yang juga mengunci
        // baris obat) tidak bisa menyelinap di antara pengecekan dan penghapusan.
        $dihapus = DB::transaction(function () use ($id) {
            $terkunci = Obat::lockForUpdate()->findOrFail($id);
            if ($terkunci->detailPeriksas()->exists()) {
                return false;
            }
            $terkunci->delete();
            return true;
        });

        if (! $dihapus) {
            return redirect()->route('obat.index')
                ->with('message', "Obat \"{$obat->nama_obat}\" tidak dapat dihapus karena sudah dipakai pada riwayat pemeriksaan. Kelola stoknya saja.")
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