<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Poli;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PoliController extends Controller
{
    public function index()
    {
        $polis = Poli::all();
        return view('admin.polis.index', compact('polis'));
    }

    public function create()
    {
        return view('admin.polis.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_poli' => 'required',
            'keterangan' => 'nullable',
        ]);

        Poli::create($validated);
        return redirect()->route('polis.index')->with('success', 'Poli berhasil di tambahkan')->with('type', 'success');
    }

    public function edit($id)
    {
        $poli = Poli::findOrFail($id);
        return view('admin.polis.edit', compact('poli'));
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'nama_poli' => 'required',
            'keterangan' => 'nullable',
        ]);

        $poli = Poli::findOrFail($id);
        $poli->update($validated);
        return redirect()->route('polis.index')->with('success', 'Poli berhasil di update');
    }

    public function destroy($id)
    {
        $poli = Poli::findOrFail($id);

        // users.id_poli memakai ON DELETE CASCADE: menghapus poli yang masih dipakai akan ikut
        // menghapus dokter beserta jadwal dan riwayat pemeriksaannya. Karena itu ditolak di sini.
        $dihapus = DB::transaction(function () use ($id) {
            $poli = Poli::lockForUpdate()->findOrFail($id);
            if ($poli->dokters()->exists()) {
                return false;
            }
            $poli->delete();
            return true;
        });

        if (! $dihapus) {
            return redirect()->route('polis.index')
                ->with('error', "Poli \"{$poli->nama_poli}\" tidak dapat dihapus karena masih dipakai oleh dokter. "
                    . 'Pindahkan dokter tersebut ke poli lain terlebih dahulu.');
        }

        return redirect()->route('polis.index')->with('success', 'Poli Berhasil di hapus !');
    }
}
