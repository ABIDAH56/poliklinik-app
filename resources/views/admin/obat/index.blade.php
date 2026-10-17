<x-layouts.app title="Data Obat">

    {{-- Alert Notifikasi --}}
    @if(session('message'))
        <div class="mb-4 px-4 py-3 rounded-xl text-sm font-semibold flex items-center gap-2
            {{ session('type') == 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' }}">
            <i class="fas {{ session('type') == 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' }}"></i>
            {{ session('message') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-slate-800">Data Obat</h2>
        <a href="{{ route('obat.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5
                  bg-primary hover:bg-primary/90
                  text-white text-sm font-semibold
                  rounded-xl transition">
            <i class="fas fa-plus text-xs"></i>
            Tambah Obat
        </a>
    </div>

    {{-- Card --}}
    <div class="card bg-base-100 shadow-md rounded-2 border">
        <div class="card-body p-0">
            <div class="overflow-x-auto">
                <table class="table w-full">

                    {{-- Table Head --}}
                    <thead class="bg-slate-50 text-slate-500 uppercase text-xs tracking-wider">
                        <tr>
                            <th class="px-6 py-4">Nama Obat</th>
                            <th class="px-6 py-4">Kemasan</th>
                            <th class="px-6 py-4">Harga</th>
                            <th class="px-6 py-4">Stok</th>
                            <th class="px-6 py-4 text-right">Aksi</th>
                        </tr>
                    </thead>

                    {{-- Table Body --}}
                    <tbody class="text-sm text-slate-700">
                        @forelse($obats as $obat)
                        <tr class="border-t border-slate-100 hover:bg-slate-50 transition">

                            <td class="px-6 py-4 font-semibold text-slate-800">
                                {{ $obat->nama_obat }}
                            </td>

                            <td class="px-6 py-4">
                                <span class="inline-block px-3 py-1 text-xs font-semibold
                                             rounded-full bg-green-100 text-green-600">
                                    {{ $obat->kemasan ?? '-' }}
                                </span>
                            </td>

                            <td class="px-6 py-4 font-semibold text-slate-800">
                                Rp {{ number_format($obat->harga, 0, ',', '.') }}
                            </td>

                            {{-- Kolom Stok --}}
                            <td class="px-6 py-4">
                                @if($obat->isStokHabis())
                                    <span class="inline-flex items-center gap-1 px-3 py-1 text-xs font-semibold
                                                 rounded-full bg-red-100 text-red-600">
                                        <i class="fas fa-circle-xmark text-xs"></i> Habis (0)
                                    </span>
                                @elseif($obat->isStokMenipis())
                                    <span class="inline-flex items-center gap-1 px-3 py-1 text-xs font-semibold
                                                 rounded-full bg-amber-100 text-amber-600">
                                        <i class="fas fa-triangle-exclamation text-xs"></i> Menipis ({{ $obat->stok }})
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-3 py-1 text-xs font-semibold
                                                 rounded-full bg-green-100 text-green-600">
                                        <i class="fas fa-circle-check text-xs"></i> {{ $obat->stok }}
                                    </span>
                                @endif
                            </td>

                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2 flex-wrap">

                                    {{-- Tambah Stok --}}
                                    <button onclick="document.getElementById('modal-tambah-{{ $obat->id }}').showModal()"
                                        class="inline-flex items-center gap-1 px-3 py-2
                                               bg-green-500 hover:bg-green-600
                                               text-white text-xs font-semibold
                                               rounded-lg transition">
                                        <i class="fas fa-plus text-xs"></i> Stok
                                    </button>

                                    {{-- Kurangi Stok --}}
                                    <button onclick="document.getElementById('modal-kurangi-{{ $obat->id }}').showModal()"
                                        class="inline-flex items-center gap-1 px-3 py-2
                                               bg-blue-500 hover:bg-blue-600
                                               text-white text-xs font-semibold
                                               rounded-lg transition">
                                        <i class="fas fa-minus text-xs"></i> Stok
                                    </button>

                                    {{-- Edit --}}
                                    <a href="{{ route('obat.edit', $obat->id) }}"
                                        class="inline-flex items-center gap-1 px-3 py-2
                                               bg-amber-500 hover:bg-amber-600
                                               text-white text-xs font-semibold
                                               rounded-lg transition">
                                        <i class="fas fa-pen text-xs"></i> Edit
                                    </a>

                                    {{-- Delete --}}
                                    <form action="{{ route('obat.destroy', $obat->id) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            onclick="return confirm('Yakin ingin menghapus obat ini?')"
                                            class="inline-flex items-center gap-1 px-3 py-2
                                                   bg-red-500 hover:bg-red-600
                                                   text-white text-xs font-semibold
                                                   rounded-lg transition">
                                            <i class="fas fa-trash text-xs"></i> Hapus
                                        </button>
                                    </form>

                                </div>
                            </td>
                        </tr>

                        {{-- ===== Modal Tambah Stok ===== --}}
                        <dialog id="modal-tambah-{{ $obat->id }}" class="modal modal-bottom sm:modal-middle">
                            <div class="modal-box p-0 overflow-hidden rounded-2xl">

                                {{-- Header Modal --}}
                                <div class="bg-green-500 px-6 py-4 flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-white/20 flex items-center justify-center">
                                        <i class="fas fa-plus text-white text-sm"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-white text-base">Tambah Stok</h3>
                                        <p class="text-green-100 text-xs">{{ $obat->nama_obat }}</p>
                                    </div>
                                </div>

                                {{-- Body Modal --}}
                                <div class="px-6 py-5">

                                    {{-- Info Stok Sekarang --}}
                                    <div class="flex items-center gap-3 bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 mb-5">
                                        <i class="fas fa-boxes-stacked text-slate-400 text-lg"></i>
                                        <div>
                                            <p class="text-xs text-slate-400">Stok saat ini</p>
                                            <p class="font-bold text-slate-700 text-sm">{{ $obat->stok }} unit</p>
                                        </div>
                                    </div>

                                    <form action="{{ route('obat.tambahStok', $obat->id) }}" method="POST">
                                        @csrf
                                        <div class="mb-5">
                                            <label class="block text-sm font-semibold text-slate-700 mb-1">
                                                Jumlah Tambah
                                            </label>
                                            <input type="number" name="jumlah" min="1"
                                                class="input input-bordered w-full focus:border-green-400 focus:outline-none"
                                                placeholder="Contoh: 10" required>
                                            <p class="text-xs text-slate-400 mt-1">Masukkan jumlah stok yang ingin ditambahkan</p>
                                        </div>

                                        <div class="flex gap-3">
                                            <button type="button"
                                                onclick="document.getElementById('modal-tambah-{{ $obat->id }}').close()"
                                                class="flex-1 py-2.5 rounded-xl border border-slate-200 text-slate-600
                                                       text-sm font-semibold hover:bg-slate-50 transition">
                                                Batal
                                            </button>
                                            <button type="submit"
                                                class="flex-1 py-2.5 rounded-xl bg-green-500 hover:bg-green-600
                                                       text-white text-sm font-semibold transition">
                                                <i class="fas fa-plus mr-1"></i> Tambah Stok
                                            </button>
                                        </div>
                                    </form>
                                </div>

                            </div>
                            <form method="dialog" class="modal-backdrop">
                                <button>close</button>
                            </form>
                        </dialog>

                        {{-- ===== Modal Kurangi Stok ===== --}}
                        <dialog id="modal-kurangi-{{ $obat->id }}" class="modal modal-bottom sm:modal-middle">
                            <div class="modal-box p-0 overflow-hidden rounded-2xl">

                                {{-- Header Modal --}}
                                <div class="bg-blue-500 px-6 py-4 flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-white/20 flex items-center justify-center">
                                        <i class="fas fa-minus text-white text-sm"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-white text-base">Kurangi Stok</h3>
                                        <p class="text-blue-100 text-xs">{{ $obat->nama_obat }}</p>
                                    </div>
                                </div>

                                {{-- Body Modal --}}
                                <div class="px-6 py-5">

                                    {{-- Info Stok Sekarang --}}
                                    <div class="flex items-center gap-3 bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 mb-5">
                                        <i class="fas fa-boxes-stacked text-slate-400 text-lg"></i>
                                        <div>
                                            <p class="text-xs text-slate-400">Stok saat ini</p>
                                            <p class="font-bold text-slate-700 text-sm">{{ $obat->stok }} unit</p>
                                        </div>
                                    </div>

                                    @if($obat->isStokHabis())
                                        {{-- Warning stok habis --}}
                                        <div class="flex items-center gap-2 bg-red-50 border border-red-200 rounded-xl px-4 py-3 mb-4">
                                            <i class="fas fa-circle-exclamation text-red-500"></i>
                                            <p class="text-sm text-red-600 font-semibold">Stok sudah habis, tidak bisa dikurangi!</p>
                                        </div>
                                        <button type="button"
                                            onclick="document.getElementById('modal-kurangi-{{ $obat->id }}').close()"
                                            class="w-full py-2.5 rounded-xl border border-slate-200 text-slate-600
                                                   text-sm font-semibold hover:bg-slate-50 transition">
                                            Tutup
                                        </button>
                                    @else
                                        <form action="{{ route('obat.kurangiStok', $obat->id) }}" method="POST">
                                            @csrf
                                            <div class="mb-5">
                                                <label class="block text-sm font-semibold text-slate-700 mb-1">
                                                    Jumlah Kurangi
                                                </label>
                                                <input type="number" name="jumlah" min="1" max="{{ $obat->stok }}"
                                                    class="input input-bordered w-full focus:border-blue-400 focus:outline-none"
                                                    placeholder="Contoh: 5" required>
                                                <p class="text-xs text-slate-400 mt-1">Maksimal pengurangan: {{ $obat->stok }} unit</p>
                                            </div>

                                            <div class="flex gap-3">
                                                <button type="button"
                                                    onclick="document.getElementById('modal-kurangi-{{ $obat->id }}').close()"
                                                    class="flex-1 py-2.5 rounded-xl border border-slate-200 text-slate-600
                                                           text-sm font-semibold hover:bg-slate-50 transition">
                                                    Batal
                                                </button>
                                                <button type="submit"
                                                    class="flex-1 py-2.5 rounded-xl bg-blue-500 hover:bg-blue-600
                                                           text-white text-sm font-semibold transition">
                                                    <i class="fas fa-minus mr-1"></i> Kurangi Stok
                                                </button>
                                            </div>
                                        </form>
                                    @endif

                                </div>
                            </div>
                            <form method="dialog" class="modal-backdrop">
                                <button>close</button>
                            </form>
                        </dialog>

                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-12 text-slate-400">
                                <i class="fas fa-inbox text-3xl mb-3 block"></i>
                                Belum ada data obat
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</x-layouts.app>