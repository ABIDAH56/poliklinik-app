<x-layouts.app title="Daftar Poli">

    <div class="flex items-center justify-center px-4">
        <div class="w-full max-w-3xl">
            <div class="card bg-base-100 shadow">
                <div class="card-body">

                    <h2 class="text-2xl font-bold text-center mb-6">
                        🏥 Pendaftaran Poli
                    </h2>

                    {{-- Toast Success --}}
                    @if (session('message'))
                    <div id="toastSuccess" class="toast toast-top toast-end z-50">
                        <div class="alert alert-success shadow-lg">
                            <span> {{ session('message') }}</span>
                        </div>
                    </div>
                    @endif

                    {{-- Error --}}
                    @if ($errors->any())
                    <div class="alert alert-error mb-4">
                        <ul class="list-disc pl-5">
                            @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    <form action="{{ route('pasien.daftar.submit') }}" method="POST">
                        @csrf

                        {{-- Nomor RM --}}
                        <div class="mb-4">
                            <label class="font-semibold block mb-1">
                                Nomor Rekam Medis
                            </label>

                            <input type="text" value="{{ $user->no_rm }}"
                                class="w-full border-2 rounded-lg p-2 bg-gray-100" disabled>
                        </div>

                        {{-- Pilih Poli --}}
                        <div class="mb-4">
                            <label class="font-semibold block mb-1">
                                Pilih Poli
                            </label>

                            <select name="id_poli" id="poliSelect" class="w-full border-2 rounded-lg p-2">
                                <option value="">-- Pilih Poli --</option>
                                @foreach ($polis as $poli)
                                <option value="{{ $poli->id }}">
                                    {{ $poli->nama_poli }}
                                </option>
                                @endforeach
                            </select>

                        </div>

                        {{-- Pilih Jadwal --}}
                        <div class="mb-4">
                            <label class="font-semibold block mb-1">
                                Pilih Jadwal Periksa
                            </label>

                            <select name="id_jadwal" id="jadwalSelect" class="w-full border-2 rounded-lg p-2">
                                <option value="">-- Pilih Jadwal --</option>

                                @foreach ($jadwals as $jadwal)
                                <option value="{{ $jadwal->id }}" data-poli="{{ $jadwal->dokter->id_poli }}">
                                    {{ $jadwal->hari }}
                                    {{ $jadwal->jam_mulai }} - {{ $jadwal->jam_selesai }}
                                    Dr. {{ $jadwal->dokter->nama ?? '--' }}
                                </option>
                                @endforeach

                            </select>
                        </div>

                        {{-- Keluhan --}}
                        <div class="mb-6">
                            <label class="font-semibold block mb-1">
                                Keluhan
                            </label>
                            <textarea name="keluhan" rows="3" class="w-full border-2 rounded-lg p-2"
                                placeholder="Tulis keluhan anda..."></textarea>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit"
                                class="px-8 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition">
                                Daftar Poli
                            </button>
                        </div>
                    </form>

                </div>
            </div>

            {{-- Riwayat Pendaftaran --}}
            <div class="card bg-base-100 shadow mt-6">
                <div class="card-body p-0">
                    <div class="px-6 py-4 border-b border-slate-200">
                        <h3 class="font-bold text-slate-800">Riwayat Pendaftaran Saya</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table table-zebra w-full">
                            <thead class="bg-slate-100 text-slate-500 text-xs uppercase tracking-wider">
                                <tr>
                                    <th class="px-6 py-4">No</th>
                                    <th class="px-6 py-4">Poli</th>
                                    <th class="px-6 py-4">Dokter</th>
                                    <th class="px-6 py-4">Jadwal</th>
                                    <th class="px-6 py-4">No Antrian</th>
                                    <th class="px-6 py-4">Keluhan</th>
                                    <th class="px-6 py-4">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($riwayat as $item)
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-6 py-4 text-slate-500">{{ $loop->iteration }}</td>
                                    <td class="px-6 py-4">{{ optional($item->jadwalPeriksa->dokter->poli)->nama_poli ?? '-' }}</td>
                                    <td class="px-6 py-4 font-semibold text-slate-800">{{ $item->jadwalPeriksa->dokter->nama ?? '-' }}</td>
                                    <td class="px-6 py-4 text-slate-500">{{ $item->jadwalPeriksa->hari }}, {{ $item->jadwalPeriksa->jam_mulai }} - {{ $item->jadwalPeriksa->jam_selesai }}</td>
                                    <td class="px-6 py-4 text-slate-500">{{ $item->no_antrian }}</td>
                                    <td class="px-6 py-4 text-slate-500">{{ $item->keluhan ?: '-' }}</td>
                                    <td class="px-6 py-4">
                                        @if ($item->periksas->isNotEmpty())
                                        <span class="badge bg-green-100 text-green-700 border border-green-200 rounded-lg px-3 py-1 text-xs font-semibold">Sudah Diperiksa</span>
                                        @else
                                        <span class="badge bg-amber-100 text-amber-700 border border-amber-200 rounded-lg px-3 py-1 text-xs font-semibold">Menunggu</span>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center py-10 text-slate-400">Belum ada pendaftaran</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener("DOMContentLoaded", function(){

    const poliSelect = document.getElementById("poliSelect")
    const jadwalSelect = document.getElementById("jadwalSelect")
    const jadwalOptions = jadwalSelect.querySelectorAll("option")

    poliSelect.addEventListener("change", function(){
        let poliId = this.value
        jadwalOptions.forEach(option => {
            if(option.value === ""){
                option.style.display = "block"
                return
            }
            if(option.dataset.poli === poliId){
                option.style.display = "block"
            }else{
                option.style.display = "none"
            }
        })
        jadwalSelect.value = ""
    })
        })
    </script>
    @endpush
</x-layouts.app>