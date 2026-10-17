<x-layouts.guest title="Login">
    <div class="card bg-white shadow-xl rounded-[24px] w-full max-w-[420px] border-none">
        <div class="card-body p-[40px]">

            {{-- Logo & Title --}}
            <div class="text-center mb-8">
                <img src="{{ asset('image/logo-bengkot.png') }}"
                    class="w-[65px] h-[65px] mx-auto mb-4 block">
                <h1 class="text-[1.6rem] font-bold text-[#1e2d6b] mb-1">Poliklinik</h1>
                <p class="text-[0.85rem] text-slate-400">Masuk ke akun Anda</p>
            </div>

            {{-- Tambahkan ini tepat di atas tag <form> --}}
@if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded-lg mb-4 text-sm">
        {{ session('error') }}
    </div>
@endif

@if ($errors->any())
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-2 rounded-lg mb-4 text-sm">
        <ul class="list-disc list-inside">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif


    

            <form action="{{ route('login') }}" method="POST">
                @csrf

                {{-- Email --}}
                <div class="form-control mb-5">
                    <label class="label pt-0 pb-1.5">
                        <span class="text-[0.85rem] font-medium text-slate-700">Email</span>
                    </label>
                    <label class="input flex items-center gap-3 rounded-[12px] border-none bg-[#f8fafc] h-[48px] px-4">
                        <i class="fas fa-envelope text-slate-400 text-[0.85rem]"></i>
                        <input type="email" name="email" value="{{ old('email') }}" placeholder="Masukkan email..."
                            class="grow bg-transparent text-slate-700 text-[0.9rem] focus:outline-none" required>
                    </label>
                </div>

                {{-- Password --}}
                <div class="form-control mb-8">
                    <label class="label pt-0 pb-1.5">
                        <span class="text-[0.85rem] font-medium text-slate-700">Password</span>
                    </label>
                    <label class="input flex items-center gap-3 rounded-[12px] border-none bg-[#f8fafc] h-[48px] px-4">
                        <i class="fas fa-lock text-slate-400 text-[0.85rem]"></i>
                        <input type="password" name="password" id="password_login" placeholder="Masukkan password..."
                            class="grow bg-transparent text-slate-700 text-[0.9rem] focus:outline-none" required>
                        <i class="fas fa-eye text-slate-400 text-[0.85rem] cursor-pointer" id="toggle_login"
                            onclick="togglePassword('password_login', 'toggle_login')"></i>
                    </label>
                </div>

                {{-- Submit Button --}}
                <button type="submit" class="w-full h-[48px] bg-[#2d4499] hover:bg-[#1e2d6b] text-white rounded-[12px] font-semibold flex items-center justify-center gap-2 transition-all">
                    <i class="fas fa-right-to-bracket"></i>
                    Login
                </button>
            </form>

            <p class="text-center mt-6 text-[0.85rem] text-slate-400">
                Belum punya akun? <a href="{{ route('register') }}" class="text-[#2d4499] font-bold">Register</a>
            </p>

        </div>
    </div>
</x-layouts.guest>