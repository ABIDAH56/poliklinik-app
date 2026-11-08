<x-layouts.app title="Detail Obat">

    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('obat.index') }}" class="flex items-center justify-center w-9 h-9 rounded-lg
                  bg-slate-100 hover:bg-slate-200
                  text-slate-600 transition">
            <i class="fas fa-arrow-left text-sm"></i>
        </a>

        <h2 class="text-2xl font-bold text-slate-800">
            Detail Obat
        </h2>
    </div>

    {{-- Data Obat --}}
    <div class="card bg-base-100 shadow-md rounded-2xl border border-slate-200 mb-6">
        <div class="card-body p-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                <div>
                    <p class="text-slate-400 mb-1">Nama Obat</p>
                    <p class="font-semibold text-slate-800 text-base">{{ $obat->nama_obat }}</p>
                </div>
                <div>
                    <p class="text-slate-400 mb-1">Kemasan</p>
                    <p class="font-semibold text-slate-800">{{ $obat->kemasan }}</p>
                </div>
                <div>
                    <p class="text-slate-400 mb-1">Harga</p>
                    <p class="font-semibold text-slate-800">Rp {{ number_format($obat->harga, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-slate-400 mb-1">Stok</p>
                    <p class="font-semibold text-slate-800">{{ $obat->stok }} unit</p>
                </div>
                <div class="md:col-span-2">
                    <p class="text-slate-400 mb-1">Dipakai di Resep</p>
                    <p class="font-semibold text-slate-800">
                        {{ $obat->detail_periksas_count }} kali
                        @if($obat->detail_periksas_count > 0)
                            <span class="ml-2 text-xs font-normal text-amber-600">
                                (obat ini tidak dapat dihapus karena sudah tercatat di riwayat pemeriksaan)
                            </span>
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex gap-3 mt-8">
                <a href="{{ route('obat.edit', $obat->id) }}"
                    class="px-6 py-2.5 rounded-lg bg-amber-500 hover:bg-amber-600
                           text-white font-semibold text-sm transition">
                    <i class="fas fa-pen mr-1"></i> Edit
                </a>
                <a href="{{ route('obat.index') }}"
                    class="px-6 py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200
                           text-slate-600 font-semibold text-sm transition">
                    Kembali
                </a>
            </div>
        </div>
    </div>

    {{-- Riwayat pemakaian: data dari relasi obat -> detail_periksa -> periksa -> daftar_poli -> pasien --}}
    <div class="card bg-base-100 shadow-md rounded-2xl border border-slate-200">
        <div class="card-body p-0">
            <h3 class="px-6 pt-5 font-bold text-slate-700">Riwayat Pemakaian Terakhir</h3>
            <div class="overflow-x-auto">
                <table class="table w-full">
                    <thead class="bg-slate-50 text-slate-500 uppercase text-xs tracking-wider">
                        <tr>
                            <th class="px-6 py-4">Tanggal Periksa</th>
                            <th class="px-6 py-4">Pasien</th>
                            <th class="px-6 py-4">Harga Saat Diresepkan</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-slate-700">
                        @forelse($pemakaian as $detail)
                        <tr class="border-t border-slate-100">
                            <td class="px-6 py-4">
                                {{ $detail->periksa?->tgl_periksa_lokal->format('d-m-Y H:i') ?? '-' }}
                            </td>
                            <td class="px-6 py-4">
                                {{ $detail->periksa?->daftarPoli?->pasien?->nama ?? '-' }}
                            </td>
                            <td class="px-6 py-4">
                                Rp {{ number_format($detail->harga, 0, ',', '.') }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center py-10 text-slate-400">
                                Obat ini belum pernah diresepkan
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</x-layouts.app>
