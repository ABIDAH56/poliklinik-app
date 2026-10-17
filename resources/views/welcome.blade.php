<x-layouts.app>
    {{-- Kode di bawah ini akan otomatis masuk ke bagian {{ $slot }} di layout --}}
    
    <div class="mb-8">
        <h2 class="text-2xl font-extrabold text-slate-800 mb-1">
            Selamat Datang, {{ auth()->user()->name ?? 'Dokter' }} 👋
        </h2>
        <p class="text-sm text-slate-400">
            {{ now()->translatedFormat('l, d F Y') }} - Berikut ringkasan aktivitas praktik Anda hari ini.
        </p>
    </div>

</x-layouts.app>