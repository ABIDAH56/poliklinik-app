<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PasienController extends Controller
{
    public function index()
    {
        $pasiens = User::where('role', 'pasien')->with('poli')->get();
        return view('admin.pasien.index', compact('pasiens'));
    }

    public function create()
    {
        return view('admin.pasien.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:255',
            'alamat' => 'required|string',
            'no_ktp' => 'required|string|max:16|unique:users,no_ktp',
            'no_hp' => 'required|string|max:15',
            'email' => 'required|string|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        User::buatPasien([
            'nama' => $request->nama,
            'alamat' => $request->alamat,
            'no_ktp' => $request->no_ktp,
            'no_hp' => $request->no_hp,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('pasien.index')->with('success', 'Data Pasien berhasil di Tambah');
    }

    public function edit(User $pasien)
    {
        abort_unless($pasien->role === 'pasien', 404);
        return view('admin.pasien.edit', compact('pasien'));
    }

    public function update(Request $request, User $pasien)
    {
        abort_unless($pasien->role === 'pasien', 404);
        $request->validate([
            'nama' => 'required|string|max:255',
            'alamat' => 'required|string',
            'no_ktp' => 'required|string|max:16|unique:users,no_ktp,' . $pasien->id,
            'no_hp' => 'required|string|max:15',
            'email' => 'required|string|unique:users,email,' . $pasien->id,
            'password' => 'nullable|string|min:6',
        ]);

        $updateData = [
            'nama' => $request->nama,
            'alamat' => $request->alamat,
            'no_ktp' => $request->no_ktp,
            'no_hp' => $request->no_hp,
            'email' => $request->email,
        ];

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $pasien->update($updateData);

        return redirect()->route('pasien.index')
            ->with('success', 'Data Pasien Berhasil di Update');
    }

    public function destroy(User $pasien)
    {
        // Hanya akun pasien yang bisa dihapus lewat jalur ini (bukan admin/dokter).
        abort_unless($pasien->role === 'pasien', 404);

        // daftar_poli.id_pasien memakai ON DELETE CASCADE: menghapus pasien akan ikut menghapus
        // pendaftaran dan riwayat pemeriksaannya, sehingga ditolak di server.
        $dihapus = DB::transaction(function () use ($pasien) {
            $terkunci = User::where('role', 'pasien')->lockForUpdate()->findOrFail($pasien->id);
            if ($terkunci->daftarPolis()->exists()) {
                return false;
            }
            $terkunci->delete();
            return true;
        });

        if (! $dihapus) {
            return redirect()->route('pasien.index')
                ->with('error', "Pasien \"{$pasien->nama}\" tidak dapat dihapus karena sudah memiliki pendaftaran atau riwayat pemeriksaan.");
        }

        return redirect()->route('pasien.index')
            ->with('success', 'Data Pasien Berhasil Di Hapus');
    }
}
