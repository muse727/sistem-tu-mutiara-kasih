<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Portal Siswa — Login</title>
    <link rel="manifest" href="/manifest-siswa.json">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #F5F5F5;
        }

        /* Lightweight Animated Background Pattern */
        .bg-pattern {
            position: fixed;
            top: -50px; left: -50px; right: -50px; bottom: -50px;
            background-image: radial-gradient(#F56C3E 1px, transparent 1px);
            background-size: 32px 32px;
            opacity: 0.08;
            z-index: -1;
            pointer-events: none; /* VERY IMPORTANT so it doesn't block scroll */
            animation: patternMove 30s linear infinite;
        }

        /* Decorative Blurs */
        .blob-1, .blob-2 {
            position: fixed;
            border-radius: 50%;
            filter: blur(60px);
            z-index: -1;
            opacity: 0.4;
            pointer-events: none; /* VERY IMPORTANT */
            animation: float 12s ease-in-out infinite alternate;
            will-change: transform;
        }
        .blob-1 { top: -10%; left: -10%; width: 300px; height: 300px; background: #F56C3E; }
        .blob-2 { bottom: -10%; right: -10%; width: 250px; height: 250px; background: #fce4db; animation-delay: -5s; }

        @keyframes patternMove {
            0% { transform: translateY(0); }
            100% { transform: translateY(32px); }
        }

        @keyframes float {
            0% { transform: translateY(0) scale(1); }
            100% { transform: translateY(30px) scale(1.1); }
        }

        /* Card Entrance Animation */
        .animate-enter {
            animation: cardEnter 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
            transform: translateY(20px);
        }
        
        @keyframes cardEnter {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0 30px white inset !important;
            -webkit-text-fill-color: #171717 !important;
        }

        /* Custom Input Focus Ring */
        input:focus {
            box-shadow: 0 0 0 4px rgba(245, 108, 62, 0.15) !important;
        }
    </style>
</head>
<body class="min-h-screen relative overflow-x-hidden bg-[#F5F5F5]">
    <div class="min-h-screen flex flex-col justify-center items-center px-4 py-10 w-full">

    <!-- Background Elements -->
    <div class="bg-pattern"></div>
    <div class="blob-1"></div>
    <div class="blob-2"></div>

    <div class="w-full max-w-md relative z-10 animate-enter">

        {{-- Logo & Header (Now inside a unified glassmorphism wrapper if desired, but separated is cleaner) --}}
        <div class="text-center mb-8">
            <div class="relative inline-flex items-center justify-center w-28 h-28 bg-white rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-white/60 mb-6 p-2  group hover:scale-105 transition-transform duration-500 ease-out">
                <!-- Inner glow -->
                <div class="absolute inset-0 bg-[#F56C3E] rounded-3xl blur-xl opacity-20 group-hover:opacity-40 transition-opacity duration-500"></div>
                
                <div class="relative w-full h-full bg-gray-50 rounded-2xl flex items-center justify-center overflow-hidden border border-gray-100">
                    @if (!empty($schoolLogo))
                        <img src="{{ Storage::url($schoolLogo) }}" alt="Logo Sekolah" class="w-full h-full object-contain p-2">
                    @else
                        <svg class="w-12 h-12 text-[#F56C3E]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l6.16-3.422A12.083 12.083 0 0121 13c0 5.523-4.03 10.075-9.284 10.95a.75.75 0 01-.432 0C6.03 23.075 2 18.523 2 13c0-.538.05-1.064.144-1.578L12 14z"/>
                        </svg>
                    @endif
                </div>
            </div>
            
            <h1 class="text-3xl font-extrabold text-[#171717] tracking-tight mb-2">{{ $schoolName ?? 'Portal Siswa' }}</h1>
            <div class="inline-flex items-center gap-2 px-3 py-1 bg-white/80  rounded-full border border-gray-200 shadow-sm">
                <span class="w-2 h-2 rounded-full bg-[#F56C3E] animate-pulse"></span>
                <p class="text-gray-600 text-xs font-semibold uppercase tracking-wider">Sistem Informasi Sekolah</p>
            </div>
        </div>

        {{-- Login Card --}}
        <div class="bg-white  rounded-[2rem] shadow-2xl border border-white p-8 sm:p-10 relative overflow-hidden">
            
            <!-- Decorative corner accent -->
            <div class="absolute -top-12 -right-12 w-24 h-24 bg-gradient-to-br from-[#F56C3E] to-[#f9c4b3] rounded-full blur-2xl opacity-40"></div>

            <div class="mb-8 relative z-10">
                <h2 class="text-2xl font-bold text-[#171717] mb-1.5">Selamat Datang 👋</h2>
                <p class="text-gray-500 text-sm">Masuk untuk melihat tagihan & riwayat pembayaran Anda.</p>
            </div>

            {{-- Error Messages --}}
            @if ($errors->any())
                <div class="bg-red-50/80  border border-red-100 rounded-2xl p-4 mb-6 relative z-10 animate-enter" style="animation-delay: 0.1s">
                    @foreach ($errors->all() as $error)
                        <p class="text-red-600 text-sm font-medium flex items-start gap-2">
                            <svg class="w-5 h-5 shrink-0 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            {{ $error }}
                        </p>
                    @endforeach
                </div>
            @endif

            {{-- Success Flash --}}
            @if (session('success'))
                <div class="bg-emerald-50/80  border border-emerald-100 rounded-2xl p-4 mb-6 relative z-10 animate-enter" style="animation-delay: 0.1s">
                    <p class="text-emerald-700 text-sm font-medium flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        {{ session('success') }}
                    </p>
                </div>
            @endif

            <form method="POST" action="{{ route('siswa.login.post') }}" class="space-y-6 relative z-10">
                @csrf

                {{-- NIS Field --}}
                <div class="space-y-2">
                    <label for="nis" class="block text-sm font-bold text-gray-700">
                        Nomor Induk Siswa (NIS)
                    </label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors duration-300 group-focus-within:text-[#F56C3E]">
                            <svg class="w-5 h-5 text-gray-400 group-focus-within:text-[#F56C3E]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <input
                            type="text"
                            id="nis"
                            name="nis"
                            value="{{ old('nis') }}"
                            autocomplete="username"
                            inputmode="numeric"
                            placeholder="Masukkan NIS anak"
                            required
                            class="w-full pl-12 pr-4 py-3.5 bg-gray-50 border {{ $errors->has('nis') ? 'border-red-300 bg-red-50/50' : 'border-gray-200' }} rounded-2xl text-gray-900 text-sm font-medium focus:outline-none focus:border-[#F56C3E] focus:bg-white transition-all duration-300 placeholder-gray-400"
                        >
                    </div>
                </div>

                {{-- Password Field --}}
                <div class="space-y-2">
                    <label for="password" class="block text-sm font-bold text-gray-700">
                        PIN Rahasia (Tanggal Lahir)
                    </label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors duration-300 group-focus-within:text-[#F56C3E]">
                            <svg class="w-5 h-5 text-gray-400 group-focus-within:text-[#F56C3E]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            inputmode="numeric"
                            placeholder="Contoh: 120706 (12 Juli 2006)"
                            required
                            class="w-full pl-12 pr-12 py-3.5 bg-gray-50 border {{ $errors->has('password') ? 'border-red-300 bg-red-50/50' : 'border-gray-200' }} rounded-2xl text-gray-900 text-sm font-medium focus:outline-none focus:border-[#F56C3E] focus:bg-white transition-all duration-300 placeholder-gray-400"
                        >
                        {{-- Toggle visibility --}}
                        <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-[#F56C3E] transition-colors">
                            <svg id="eye-icon" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Cloudflare Turnstile --}}
                <div class="cf-turnstile mt-2" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="light"></div>
                @error('cf-turnstile-response')
                <p class="text-xs font-semibold text-red-500">{{ $message }}</p>
                @enderror

                {{-- Submit --}}
                <button
                    type="submit"
                    class="group relative w-full bg-[#171717] hover:bg-[#F56C3E] text-white font-bold py-4 rounded-2xl transition-all duration-300 text-sm shadow-[0_10px_20px_-10px_rgba(23,23,23,0.5)] hover:shadow-[0_10px_20px_-10px_rgba(245,108,62,0.6)] mt-4 overflow-hidden flex items-center justify-center gap-2"
                >
                    <span class="relative z-10">Masuk ke Portal</span>
                    <svg class="w-4 h-4 relative z-10 group-hover:translate-x-1 transition-transform duration-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                    </svg>
                </button>
            </form>
        </div>

        <div class="mt-8 text-center animate-enter" style="animation-delay: 0.2s">
            <p class="text-[#777777] text-sm font-medium">
                Kendala login? <a href="#" class="text-[#F56C3E] font-bold hover:underline decoration-2 underline-offset-2">Hubungi TU Sekolah</a>
            </p>
        </div>

    </div>

    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon  = document.getElementById('eye-icon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                `;
            } else {
                input.type = 'password';
                icon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                `;
            }
        }
    </script>

    {{-- ===== POPUP DOWNLOAD APK ===== --}}
    <div id="apk-popup" class="fixed inset-0 z-[9999] items-end justify-center px-4 pb-6 hidden">
        <div class="absolute inset-0 bg-black/30  transition-opacity" onclick="dismissApkPopup()"></div>

        <div class="relative w-full max-w-sm bg-white rounded-3xl shadow-[0_20px_50px_-10px_rgba(0,0,0,0.3)] p-6" style="animation: slide-up 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;">
            <button onclick="dismissApkPopup()" class="absolute top-5 right-5 text-gray-400 hover:text-[#171717] bg-gray-50 hover:bg-gray-100 rounded-full p-1 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>

            <div class="flex items-center gap-4 mb-5">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-[#F56C3E] to-[#e45322] flex items-center justify-center shrink-0 shadow-lg shadow-[#F56C3E]/30 text-white">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-extrabold text-[#171717] text-lg leading-tight">Install Aplikasi</h3>
                    <p class="text-gray-500 text-xs font-medium mt-1">Lebih cepat & hemat kuota!</p>
                </div>
            </div>

            <ul class="text-sm font-medium text-gray-600 space-y-2.5 mb-6">
                <li class="flex items-center gap-3">
                    <div class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    Notifikasi tagihan langsung
                </li>
                <li class="flex items-center gap-3">
                    <div class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    Tidak perlu buka browser
                </li>
            </ul>

            <div class="flex gap-3">
                <a href="/download/app-siswa.apk"
                   onclick="markApkInstalled()"
                   class="flex-1 bg-[#171717] hover:bg-[#F56C3E] text-white text-sm font-bold py-3.5 rounded-2xl text-center transition-colors flex items-center justify-center gap-2 shadow-lg">
                    Download APK
                </a>
                <button onclick="dismissApkPopup()"
                        class="px-5 py-3.5 rounded-2xl bg-gray-50 text-gray-600 text-sm font-bold hover:bg-gray-100 transition-colors">
                    Nanti
                </button>
            </div>
        </div>
    </div>

    <style>
        @keyframes slide-up {
            from { opacity: 0; transform: translateY(100%); }
            to   { opacity: 1; transform: translateY(0); }
        }
    </style>

    </div>

    <script>
        (function () {
            var isStandalone = window.matchMedia('(display-mode: standalone)').matches
                            || window.navigator.standalone === true;
            if (isStandalone) return;
            if (localStorage.getItem('apk_installed_v1')) return;
            setTimeout(function () {
                var popup = document.getElementById('apk-popup');
                if (popup) {
                    popup.classList.remove('hidden');
                    popup.classList.add('flex');
                }
            }, 1000);
        })();

        function markApkInstalled() {
            localStorage.setItem('apk_installed_v1', '1');
            document.getElementById('apk-popup').classList.add('hidden');
            document.getElementById('apk-popup').classList.remove('flex');
        }
        function dismissApkPopup() {
            var popup = document.getElementById('apk-popup');
            popup.classList.add('hidden');
            popup.classList.remove('flex');
        }
    </script>
</body>
</html>
