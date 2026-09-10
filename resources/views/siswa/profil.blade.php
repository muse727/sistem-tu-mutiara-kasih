<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Profil Saya — {{ $student->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen pb-16" x-data="{ activeTab: 'info', showPasswordForm: false }">

    {{-- ===== HEADER ===== --}}
    <div class="bg-gradient-to-r from-sky-500 via-sky-600 to-blue-700 px-4 pt-10 pb-20">
        <div class="max-w-md mx-auto flex items-center gap-3">
            <a href="{{ route('siswa.dashboard') }}"
               class="flex items-center gap-1.5 bg-white/15 hover:bg-white/25 text-white text-xs font-medium px-3 py-2 rounded-xl transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
                Kembali
            </a>
            <div>
                <p class="text-white font-bold text-base leading-tight">Profil Saya</p>
                <p class="text-sky-200 text-xs mt-0.5">{{ $schoolName }}</p>
            </div>
        </div>
    </div>

    <div class="max-w-md mx-auto px-4 -mt-12">

        {{-- ===== AVATAR CARD ===== --}}
        <div class="bg-white rounded-3xl shadow-xl shadow-gray-200/60 overflow-hidden mb-4">
            <div class="h-1.5 bg-gradient-to-r from-sky-400 via-blue-500 to-sky-600"></div>
            <div class="px-6 py-5 flex items-center gap-4">
                {{-- Avatar initials --}}
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-sky-400 to-blue-600 flex items-center justify-center shrink-0 shadow-lg shadow-sky-200/50">
                    <span class="text-white font-bold text-2xl">
                        {{ strtoupper(substr($student->name, 0, 1)) }}
                    </span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-bold text-gray-900 text-base leading-tight truncate">{{ $student->name }}</p>
                    <p class="text-sky-600 text-sm font-semibold mt-0.5">NIS {{ $student->nis }}</p>
                    @if($student->kelas)
                    <p class="text-gray-400 text-xs mt-0.5">{{ $student->kelas->nama_kelas }}</p>
                    @endif
                </div>
                {{-- Status badge --}}
                <span class="shrink-0 inline-flex items-center gap-1 bg-[#F56C3E] text-white shadow-sm text-xs font-semibold px-2.5 py-1 rounded-full">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                    Aktif
                </span>
            </div>
        </div>

        {{-- ===== SUCCESS ALERT ===== --}}
        @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-2xl px-4 py-3 mb-4 flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success') }}
        </div>
        @endif
        @if(session('success_password'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-2xl px-4 py-3 mb-4 flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
            {{ session('success_password') }}
        </div>
        @endif

        {{-- ===== TAB NAVIGATION ===== --}}
        <div class="flex bg-white rounded-2xl shadow-sm shadow-gray-200/60 p-1 mb-4">
            <button @click="activeTab = 'info'"
                    :class="activeTab === 'info' ? 'bg-sky-600 text-white shadow-md shadow-sky-200' : 'text-gray-500 hover:text-gray-700'"
                    class="flex-1 text-xs font-semibold py-2.5 rounded-xl transition-all duration-200">
                Data Diri
            </button>
            <button @click="activeTab = 'edit'"
                    :class="activeTab === 'edit' ? 'bg-sky-600 text-white shadow-md shadow-sky-200' : 'text-gray-500 hover:text-gray-700'"
                    class="flex-1 text-xs font-semibold py-2.5 rounded-xl transition-all duration-200">
                Edit Profil
            </button>
            <button @click="activeTab = 'password'"
                    :class="activeTab === 'password' ? 'bg-sky-600 text-white shadow-md shadow-sky-200' : 'text-gray-500 hover:text-gray-700'"
                    class="flex-1 text-xs font-semibold py-2.5 rounded-xl transition-all duration-200">
                Password
            </button>
        </div>

        {{-- ===== TAB: DATA DIRI (read-only) ===== --}}
        <div x-show="activeTab === 'info'" x-transition>
            <div class="bg-white rounded-3xl shadow-sm shadow-gray-200/60 overflow-hidden mb-4">
                <div class="px-5 py-4 border-b border-gray-100">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Informasi Akademik</p>
                </div>
                <div class="px-5 py-2 divide-y divide-gray-50">
                    @php
                        $infoItems = [
                            ['label' => 'Nama Lengkap',    'value' => $student->name],
                            ['label' => 'NIS',             'value' => $student->nis ?? '-'],
                            ['label' => 'NISN',            'value' => $student->nisn ?? '-'],
                            ['label' => 'Kelas',           'value' => $student->kelas?->nama_kelas ?? '-'],
                            ['label' => 'Tahun Masuk',     'value' => $student->tahun_masuk ?? '-'],
                            ['label' => 'Status',          'value' => ucfirst($student->status)],
                        ];
                    @endphp
                    @foreach($infoItems as $item)
                    <div class="flex items-center justify-between py-3 gap-4">
                        <span class="text-xs text-gray-400 shrink-0 w-28">{{ $item['label'] }}</span>
                        <span class="text-xs font-semibold text-gray-700 text-right">{{ $item['value'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-sm shadow-gray-200/60 overflow-hidden mb-4">
                <div class="px-5 py-4 border-b border-gray-100">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Data Pribadi</p>
                </div>
                <div class="px-5 py-2 divide-y divide-gray-50">
                    @php
                        $pribadiItems = [
                            ['label' => 'Jenis Kelamin',  'value' => $student->gender === 'L' ? 'Laki-laki' : ($student->gender === 'P' ? 'Perempuan' : '-')],
                            ['label' => 'Tempat Lahir',   'value' => $student->birth_place ?? '-'],
                            ['label' => 'Tanggal Lahir',  'value' => $student->birth_date ? \Carbon\Carbon::parse($student->birth_date)->locale('id')->isoFormat('D MMMM YYYY') : '-'],
                            ['label' => 'Agama',          'value' => $student->agama ?? '-'],
                            ['label' => 'Alamat',         'value' => $student->address ?? '-'],
                            ['label' => 'No. HP Orang Tua','value' => $student->parent_phone ?? '-'],
                        ];
                    @endphp
                    @foreach($pribadiItems as $item)
                    <div class="flex items-start justify-between py-3 gap-4">
                        <span class="text-xs text-gray-400 shrink-0 w-28 pt-0.5">{{ $item['label'] }}</span>
                        <span class="text-xs font-semibold text-gray-700 text-right">{{ $item['value'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>

            <p class="text-center text-xs text-gray-400 pb-2">
                Data akademik hanya dapat diubah oleh admin sekolah.
            </p>
        </div>

        {{-- ===== TAB: EDIT PROFIL ===== --}}
        <div x-show="activeTab === 'edit'" x-transition>
            <div class="bg-white rounded-3xl shadow-sm shadow-gray-200/60 overflow-hidden mb-4">
                <div class="px-5 py-4 border-b border-gray-100">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Edit Data Kontak</p>
                    <p class="text-xs text-gray-400 mt-0.5">Hanya alamat dan no. HP orang tua yang bisa diubah</p>
                </div>
                <form action="{{ route('siswa.profil.update') }}" method="POST" class="px-5 py-5 space-y-5">
                    @csrf

                    {{-- No. HP Orang Tua --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">No. HP Orang Tua / Wali</label>
                        <input type="tel"
                               name="parent_phone"
                               value="{{ old('parent_phone', $student->parent_phone) }}"
                               placeholder="Contoh: 08123456789"
                               class="w-full bg-gray-50 border @error('parent_phone') border-red-300 @else border-gray-200 @enderror rounded-2xl px-4 py-3 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-transparent transition">
                        @error('parent_phone')
                        <p class="text-xs text-[#F56C3E] mt-1.5">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Alamat --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Alamat Lengkap</label>
                        <textarea name="address"
                                  rows="3"
                                  placeholder="Masukkan alamat lengkap..."
                                  class="w-full bg-gray-50 border @error('address') border-red-300 @else border-gray-200 @enderror rounded-2xl px-4 py-3 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-transparent transition resize-none">{{ old('address', $student->address) }}</textarea>
                        @error('address')
                        <p class="text-xs text-[#F56C3E] mt-1.5">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit"
                            class="w-full flex items-center justify-center gap-2 bg-sky-600 hover:bg-sky-700 active:bg-sky-800 text-white font-semibold text-sm py-3.5 rounded-2xl transition-colors shadow-md shadow-sky-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        Simpan Perubahan
                    </button>
                </form>
            </div>
        </div>

        {{-- ===== TAB: GANTI PASSWORD ===== --}}
        <div x-show="activeTab === 'password'" x-transition>
            <div class="bg-white rounded-3xl shadow-sm shadow-gray-200/60 overflow-hidden mb-4">
                <div class="px-5 py-4 border-b border-gray-100">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Ganti Password</p>
                    <p class="text-xs text-gray-400 mt-0.5">
                        @if($student->custom_password)
                            Password saat ini: custom password
                        @else
                            Password saat ini: tanggal lahir (format ddmmyy)
                        @endif
                    </p>
                </div>
                <form action="{{ route('siswa.profil.password') }}" method="POST" class="px-5 py-5 space-y-5">
                    @csrf

                    {{-- Password Saat Ini --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Password Saat Ini</label>
                        <input type="password"
                               name="current_password"
                               placeholder="{{ $student->custom_password ? 'Masukkan password saat ini' : 'Contoh: 270708 (tanggal lahir ddmmyy)' }}"
                               class="w-full bg-gray-50 border @error('current_password') border-red-300 @else border-gray-200 @enderror rounded-2xl px-4 py-3 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-transparent transition">
                        @error('current_password')
                        <p class="text-xs text-[#F56C3E] mt-1.5">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Password Baru --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Password Baru</label>
                        <input type="password"
                               name="new_password"
                               placeholder="Minimal 6 karakter"
                               class="w-full bg-gray-50 border @error('new_password') border-red-300 @else border-gray-200 @enderror rounded-2xl px-4 py-3 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-transparent transition">
                        @error('new_password')
                        <p class="text-xs text-[#F56C3E] mt-1.5">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Konfirmasi Password --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Konfirmasi Password Baru</label>
                        <input type="password"
                               name="new_password_confirmation"
                               placeholder="Ulangi password baru"
                               class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-4 py-3 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-transparent transition">
                    </div>

                    {{-- Info --}}
                    <div class="bg-amber-50 border border-amber-200 rounded-2xl px-4 py-3 flex items-start gap-2">
                        <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-xs text-amber-700 leading-relaxed">Setelah ganti password, gunakan password baru untuk login. Jika lupa, hubungi admin sekolah untuk reset ke tanggal lahir.</p>
                    </div>

                    <button type="submit"
                            class="w-full flex items-center justify-center gap-2 bg-sky-600 hover:bg-sky-700 active:bg-sky-800 text-white font-semibold text-sm py-3.5 rounded-2xl transition-colors shadow-md shadow-sky-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        Ganti Password
                    </button>
                </form>
            </div>
        </div>

    </div>

    <script src="//unpkg.com/alpinejs" defer></script>

</body>
</html>
