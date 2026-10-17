<x-layouts.app title="Pasien Dashboard">
    <h1 class="ml-4 mb-4">Halo Selamat Datang Pasien</h1>

    <div class="px-4">
        <h2 class="text-lg font-semibold mb-2">Jadwal Periksa Tersedia</h2>
        @forelse ($jadwals as $jadwal)
            @if ($loop->first)
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr><th>Poli</th><th>Dokter</th><th>Hari</th><th>Jam</th></tr>
                    </thead>
                    <tbody>
            @endif
                        <tr>
                            <td>{{ $jadwal->dokter->poli->nama_poli ?? '-' }}</td>
                            <td>Dr. {{ $jadwal->dokter->nama }}</td>
                            <td>{{ $jadwal->hari }}</td>
                            <td>{{ substr($jadwal->jam_mulai, 0, 5) }} - {{ substr($jadwal->jam_selesai, 0, 5) }}</td>
                        </tr>
            @if ($loop->last)
                    </tbody>
                </table>
            </div>
            @endif
        @empty
            <p class="text-sm text-slate-500">Belum ada jadwal periksa yang tersedia.</p>
        @endforelse

        <a href="{{ route('pasien.daftar') }}" class="btn btn-primary mt-4">Daftar Poli</a>
    </div>
</x-layouts.app>
