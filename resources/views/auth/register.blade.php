<x-layouts.guest title="Register">
    <div class="card bg-white shadow-xl rounded-[24px] w-full max-w-[480px] border-none">
        <div class="card-body p-[40px]">

            {{-- Logo & Title --}}
            <div class="text-center mb-6">
                <img src="{{ asset('images/logo-bengkot.png') }}"
                    class="w-[65px] h-[65px] mx-auto mb-4 block">
                <h1 class="text-[1.6rem] font-bold text-[#1e2d6b] mb-1">Poliklinik</h1>
                <p class="text-[0.85rem] text-slate-400">Buat akun baru</p>
            </div>

            <form action="{{ route('register') }}" method="POST">
                @csrf

                <div class="form-control mb-4">
                    <label class="label pt-0 pb-1.5"><span class="text-[0.85rem] font-medium text-slate-700">Nama Lengkap</span></label>
                    <label class="input flex items-center gap-3 rounded-[12px] border-none bg-[#f8fafc] h-[45px] px-4">
                        <i class="fas fa-user text-slate-400 text-[0.85rem]"></i>
                        <input type="text" name="nama" placeholder="Masukkan nama lengkap..." class="grow bg-transparent text-slate-700 text-[0.88rem] focus:outline-none" required>
                    </label>
                </div>

                <div class="form-control mb-4">
                    <label class="label pt-0 pb-1.5"><span class="text-[0.85rem] font-medium text-slate-700">Email</span></label>
                    <label class="input flex items-center gap-3 rounded-[12px] border-none bg-[#f8fafc] h-[45px] px-4">
                        <i class="fas fa-envelope text-slate-400 text-[0.85rem]"></i>
                        <input type="email" name="email" placeholder="Masukkan email..." class="grow bg-transparent text-slate-700 text-[0.88rem] focus:outline-none" required>
                    </label>
                </div>

                <div class="form-control mb-4">
                    <label class="label pt-0 pb-1.5"><span class="text-[0.85rem] font-medium text-slate-700">Alamat</span></label>
                    <label class="input flex items-center gap-3 rounded-[12px] border-none bg-[#f8fafc] h-[45px] px-4">
                        <i class="fas fa-map-marker-alt text-slate-400 text-[0.85rem]"></i>
                        <input type="text" name="alamat" placeholder="Masukkan alamat..." class="grow bg-transparent text-slate-700 text-[0.88rem] focus:outline-none" required>
                    </label>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="form-control">
                        <label class="label pt-0 pb-1.5"><span class="text-[0.85rem] font-medium text-slate-700">No. HP</span></label>
                        <label class="input flex items-center gap-3 rounded-[12px] border-none bg-[#f8fafc] h-[45px] px-4">
                            <i class="fas fa-phone text-slate-400 text-[0.8rem]"></i>
                            <input type="number" name="no_hp" placeholder="No. HP..." class="grow bg-transparent text-slate-700 text-[0.85rem] focus:outline-none" required>
                        </label>
                    </div>
                    <div class="form-control">
                        <label class="label pt-0 pb-1.5"><span class="text-[0.85rem] font-medium text-slate-700">No. KTP</span></label>
                        <label class="input flex items-center gap-3 rounded-[12px] border-none bg-[#f8fafc] h-[45px] px-4">
                            <i class="fas fa-address-card text-slate-400 text-[0.8rem]"></i>
                            <input type="number" name="no_ktp" placeholder="No. KTP..." class="grow bg-transparent text-slate-700 text-[0.85rem] focus:outline-none" required>
                        </label>
                    </div>
                </div>

                <div class="form-control mb-4">
                    <label class="label pt-0 pb-1.5"><span class="text-[0.85rem] font-medium text-slate-700">Password</span></label>
                    <label class="input flex items-center gap-3 rounded-[12px] border-none bg-[#f8fafc] h-[45px] px-4">
                        <i class="fas fa-lock text-slate-400 text-[0.8rem]"></i>
                        <input type="password" name="password" id="password_reg" placeholder="Password..." class="grow bg-transparent text-slate-700 text-[0.85rem] focus:outline-none" required>
                        <i class="fas fa-eye text-slate-400 text-[0.8rem] cursor-pointer" onclick="togglePassword('password_reg', 'toggle_reg')"></i>
                    </label>
                </div>

                <div class="form-control mb-8">
                    <label class="label pt-0 pb-1.5"><span class="text-[0.85rem] font-medium text-slate-700">Konfirmasi Password</span></label>
                    <label class="input flex items-center gap-3 rounded-[12px] border-none bg-[#f8fafc] h-[45px] px-4">
                        <i class="fas fa-lock text-slate-400 text-[0.8rem]"></i>
                        <input type="password" name="password_confirmation" id="password_confirm" placeholder="Ulangi password..." class="grow bg-transparent text-slate-700 text-[0.85rem] focus:outline-none" required>
                    </label>
                </div>

                <button type="submit" class="w-full h-[48px] bg-[#2d4499] hover:bg-[#1e2d6b] text-white rounded-[12px] font-semibold flex items-center justify-center gap-2 transition-all">
                    <i class="fas fa-user-plus"></i> Register
                </button>
            </form>

            <p class="text-center mt-6 text-[0.85rem] text-slate-400">
                Sudah punya akun? <a href="{{ route('login') }}" class="text-[#2d4499] font-bold">Login</a>
            </p>
        </div>
    </div>
</x-layouts.guest>