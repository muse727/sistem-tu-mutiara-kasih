<!DOCTYPE html>
<html lang="id" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Sistem TU') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"
        integrity="sha384-nLoOnA/BDh8A/jxqtckg4DumuCGOBYUnNJLZdQz/zfYNp3wcjGSoWTAzgko06G/2"
        crossorigin="anonymous"></script>
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0ea5e9">
    <script>if('serviceWorker'in navigator){navigator.serviceWorker.register('/sw.js')}</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @php
        // Ambil pengaturan sidebar & logo dari school_settings (cached per-request)
        $sidebarColor = \DB::table('school_settings')->where('key', 'sidebar_color')->value('value') ?? 'blue';

        // FITUR 1: Badge tagihan jatuh tempo — UNPAID & due_date lewat ATAU created_at > 30 hari
        $overdueTagihanCount = 0;
        try {
            $overdueTagihanCount = \DB::table('student_bills')
                ->where('status', 'UNPAID')
                ->where(function($q) {
                    $q->where(function($qq) {
                        // Punya due_date dan sudah lewat
                        $qq->whereNotNull('due_date')->where('due_date', '<', now()->toDateString());
                    })->orWhere(function($qq) {
                        // Tidak punya due_date tapi created_at > 30 hari
                        $qq->whereNull('due_date')->where('created_at', '<', now()->subDays(30));
                    });
                })
                ->count();
        } catch (\Exception $e) {
            $overdueTagihanCount = 0;
        }
        $schoolLogo   = \DB::table('school_settings')->where('key', 'school_logo')->value('value');
        $schoolName   = \DB::table('school_settings')->where('key', 'school_name')->value('value') ?? config('app.name', 'Sistem TU');

        // Palet warna — [bg utama, bg header/footer (lebih gelap), warna aktif text, label]
        $palettes = [
            'blue'   => ['#0ea5e9', '#0284c7', '#0ea5e9', 'Sky Blue'],
            'indigo' => ['#6366f1', '#4f46e5', '#6366f1', 'Indigo'],
            'violet' => ['#8b5cf6', '#7c3aed', '#8b5cf6', 'Violet'],
            'pink'   => ['#ec4899', '#db2777', '#ec4899', 'Pink'],
            'rose'   => ['#f43f5e', '#e11d48', '#f43f5e', 'Rose Red'],
            'orange' => ['#f97316', '#ea580c', '#f97316', 'Orange'],
            'amber'  => ['#f59e0b', '#d97706', '#f59e0b', 'Amber'],
            'green'  => ['#10b981', '#059669', '#10b981', 'Emerald'],
            'teal'   => ['#14b8a6', '#0d9488', '#14b8a6', 'Teal'],
            'brown'  => ['#92400e', '#78350f', '#92400e', 'Brown'],
            'slate'  => ['#475569', '#334155', '#475569', 'Slate Gray'],
            'black'  => ['#1e293b', '#0f172a', '#1e293b', 'Dark/Black'],
        ];

        $palette = $palettes[$sidebarColor] ?? $palettes['blue'];
        [$sidebarBg, $sidebarDark, $activeText] = $palette;
    @endphp

    <style>
        /* ══════════════════════════════════════════
           DARK MODE — pendekatan universal
           Target: #main-content-area dan semua anak-anaknya
           Tidak bergantung Tailwind class names
        ══════════════════════════════════════════ */

        /* Area konten utama */
        html.dark #main-content-area {
            background-color: #0f172a !important;
            color: #e2e8f0 !important;
        }
        /* Semua div/section/article/main di dalam konten */
        html.dark #main-content-area div,
        html.dark #main-content-area section,
        html.dark #main-content-area article,
        html.dark #main-content-area aside {
            background-color: inherit;
            color: inherit;
            border-color: #334155;
        }
        /* Card — element dengan background putih */
        html.dark #main-content-area [class*="bg-white"],
        html.dark #main-content-area [class*="bg-gray-50"],
        html.dark #main-content-area [class*="bg-gray-100"] {
            background-color: #1e293b !important;
            color: #e2e8f0 !important;
        }
        /* Teks */
        html.dark #main-content-area [class*="text-gray-"],
        html.dark #main-content-area [class*="text-slate-"],
        html.dark #main-content-area p,
        html.dark #main-content-area span,
        html.dark #main-content-area label,
        html.dark #main-content-area h1,
        html.dark #main-content-area h2,
        html.dark #main-content-area h3,
        html.dark #main-content-area h4,
        html.dark #main-content-area h5,
        html.dark #main-content-area h6 {
            color: #e2e8f0 !important;
        }
        /* Border */
        html.dark #main-content-area [class*="border-gray-"],
        html.dark #main-content-area [class*="border-slate-"],
        html.dark #main-content-area [class*="divide-gray-"] > * {
            border-color: #334155 !important;
        }
        /* Input, select, textarea */
        html.dark #main-content-area input,
        html.dark #main-content-area textarea,
        html.dark #main-content-area select {
            background-color: #1e293b !important;
            color: #e2e8f0 !important;
            border-color: #475569 !important;
        }
        /* Table */
        html.dark #main-content-area table { border-color: #334155 !important; }
        html.dark #main-content-area thead,
        html.dark #main-content-area thead tr,
        html.dark #main-content-area thead th {
            background-color: #0f172a !important;
            color: #94a3b8 !important;
            border-color: #334155 !important;
        }
        html.dark #main-content-area tbody tr {
            border-color: #334155 !important;
        }
        html.dark #main-content-area tbody tr:hover {
            background-color: #1e293b !important;
        }
        html.dark #main-content-area td, html.dark #main-content-area th {
            border-color: #334155 !important;
        }
        /* Hover states */
        html.dark #main-content-area [class*="hover:bg-gray-"]:hover,
        html.dark #main-content-area [class*="hover:bg-white"]:hover {
            background-color: #334155 !important;
        }
        /* Muted/secondary text */
        html.dark #main-content-area [class*="text-gray-4"],
        html.dark #main-content-area [class*="text-gray-5"],
        html.dark #main-content-area [class*="text-slate-4"],
        html.dark #main-content-area [class*="text-slate-5"] {
            color: #94a3b8 !important;
        }
        /* Header navbar */
        html.dark header.dm-header {
            background-color: #1e293b !important;
            border-color: #334155 !important;
        }
        html.dark header.dm-header [class*="text-gray-"] {
            color: #e2e8f0 !important;
        }
        html.dark header.dm-header button:not(#darkModeToggle) {
            color: #94a3b8 !important;
        }
        html.dark header.dm-header button:hover {
            background-color: #334155 !important;
        }
        /* Footer */
        html.dark footer.dm-text-muted {
            background-color: #0f172a !important;
            color: #64748b !important;
        }
        /* Toggle button state */
        html.dark #darkModeToggle { color: #f59e0b !important; }
        html.dark #darkModeToggle:hover { background-color: #334155 !important; }

        /* Gradient cards — ganti ke warna gelap, teks tetap berwarna aslinya */
        html.dark #main-content-area [class*="bg-gradient-to"] {
            background: #1e293b !important;
        }
        /* Jangan override teks berwarna (blue, emerald, red, yellow, amber, green, purple, etc) di dark mode */
        html.dark #main-content-area [class*="text-blue-"],
        html.dark #main-content-area [class*="text-emerald-"],
        html.dark #main-content-area [class*="text-red-"],
        html.dark #main-content-area [class*="text-yellow-"],
        html.dark #main-content-area [class*="text-amber-"],
        html.dark #main-content-area [class*="text-green-"],
        html.dark #main-content-area [class*="text-purple-"],
        html.dark #main-content-area [class*="text-indigo-"],
        html.dark #main-content-area [class*="text-sky-"],
        html.dark #main-content-area [class*="text-cyan-"],
        html.dark #main-content-area [class*="text-pink-"],
        html.dark #main-content-area [class*="text-rose-"],
        html.dark #main-content-area [class*="text-orange-"],
        html.dark #main-content-area [class*="text-teal-"],
        html.dark #main-content-area [class*="text-white"] {
            color: unset !important;
        }
        /* Light mode protection — pastikan teks berwarna tidak di-override di light mode */
        #main-content-area [class*="text-blue-"],
        #main-content-area [class*="text-emerald-"],
        #main-content-area [class*="text-red-"],
        #main-content-area [class*="text-yellow-"],
        #main-content-area [class*="text-amber-"],
        #main-content-area [class*="text-green-"],
        #main-content-area [class*="text-gray-"] {
            /* Pastikan ini tidak di-override oleh dark mode CSS apapun saat light mode */
        }
        .custom-scroll::-webkit-scrollbar { width: 5px; height: 5px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        .custom-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 10px; }

        /* CSS Custom Properties untuk warna sidebar — tidak bergantung Tailwind purge */
        :root {
            --sb-bg:     {{ $sidebarBg }};
            --sb-dark:   {{ $sidebarDark }};
            --sb-active: {{ $activeText }};
        }

        .sidebar-wrap        { background-color: var(--sb-bg); }
        .sidebar-header      { background-color: var(--sb-dark); }
        .sidebar-footer      { background-color: var(--sb-dark); }
        .sidebar-submenu     { background-color: color-mix(in srgb, var(--sb-dark) 60%, transparent); }
        .sidebar-active      { background-color: #ffffff; color: var(--sb-active) !important; }
        .sidebar-active svg  { color: var(--sb-active) !important; stroke: var(--sb-active) !important; }
    </style>
</head>
<body class="h-full font-sans antialiased text-gray-900" x-data="{ sidebarOpen: false }">

    {{-- ═══════════════════════════════════════════════════════
         SIDEBAR
    ═══════════════════════════════════════════════════════ --}}
    <aside class="sidebar-wrap fixed inset-y-0 left-0 z-50 w-64 text-white transition-transform duration-300 transform flex flex-col shadow-2xl h-full"
           :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">

        {{-- Header Sidebar: Logo Sekolah + Nama --}}
        <div class="sidebar-header h-auto min-h-[4.5rem] flex items-center justify-between px-4 py-3 flex-shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                @if($schoolLogo)
                    <img src="{{ Storage::url($schoolLogo) }}"
                         alt="Logo Sekolah"
                         class="w-10 h-10 rounded-lg object-contain bg-white p-0.5 flex-shrink-0 shadow">
                @else
                    <div class="p-1.5 bg-white rounded-lg flex-shrink-0">
                        <svg class="w-5 h-5" style="color: var(--sb-active)" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                @endif
                <span class="font-bold text-sm leading-tight truncate text-white">{{ $schoolName }}</span>
            </div>
            <button @click="sidebarOpen = false" class="lg:hidden text-white/80 hover:text-white ml-2 flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Nav Menu --}}
        <nav class="flex-1 overflow-y-auto custom-scroll px-3 py-5 space-y-1">
            <p class="px-3 text-[10px] font-bold text-white/50 uppercase tracking-widest mb-2">Menu Utama</p>

            {{-- Dashboard --}}
            <a href="{{ route('dashboard') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition
                      {{ request()->routeIs('dashboard') ? 'sidebar-active shadow-md' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                </svg>
                <span>Dashboard</span>
            </a>

            {{-- Manajemen Siswa --}}
            @if(in_array(Auth::user()->role, ['admin','tu','staf','kepala_sekolah']))
            <a href="{{ route('students.index') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition
                      {{ request()->routeIs('students.*') || request()->routeIs('naik-kelas.*') ? 'sidebar-active shadow-md' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                <span>Manajemen Siswa</span>
            </a>
            @endif

            {{-- Master Kelas & Rombel --}}
            @if(in_array(Auth::user()->role, ['admin','tu']))
            <a href="{{ route('kelas.index') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition
                      {{ request()->routeIs('kelas.*') ? 'sidebar-active shadow-md' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                <span>Master Kelas</span>
            </a>
            <a href="{{ route('rombel.index') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition
                      {{ request()->routeIs('rombel.*') || request()->routeIs('tahun-ajaran.*') ? 'sidebar-active shadow-md' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span>Rombel</span>
            </a>
            @endif


            {{-- Tagihan Sekolah --}}
            @if(in_array(Auth::user()->role, ['admin','tu','staf','kepala_sekolah']))
            <div x-data="{ open: {{ request()->routeIs('bills.*') ? 'true' : 'false' }} }">
                <button @click="open = !open"
                        class="w-full flex justify-between items-center px-3 py-2.5 rounded-xl text-sm font-medium transition
                               {{ request()->routeIs('bills.*') ? 'bg-white/15 text-white' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <span>Tagihan Sekolah</span>
                        @if($overdueTagihanCount > 0)
                        <span class="ml-auto inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 text-xs font-bold text-white bg-red-500 rounded-full shadow-sm">
                            {{ $overdueTagihanCount > 99 ? '99+' : $overdueTagihanCount }}
                        </span>
                        @endif
                    </div>
                    <svg class="w-4 h-4 transition-transform duration-200" :class="{'rotate-180': open}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" x-cloak class="sidebar-submenu mt-1 space-y-1 rounded-xl p-1">
                    <a href="{{ route('bills.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs transition
                              {{ request()->routeIs('bills.index') ? 'sidebar-active font-bold shadow-sm' : 'text-white/70 hover:text-white hover:bg-white/10' }}">
                        <span>Monitoring Tagihan</span>
                    </a>
                    @if(in_array(Auth::user()->role, ['admin','tu']))
                    <a href="{{ route('bills.create') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs transition
                              {{ request()->routeIs('bills.create') ? 'sidebar-active font-bold shadow-sm' : 'text-white/70 hover:text-white hover:bg-white/10' }}">
                        <span>Buat Tagihan Massal</span>
                    </a>
                    @endif
                </div>
            </div>
            @endif

            {{-- POS Sekolah --}}
            @if(in_array(Auth::user()->role, ['admin','tu','staf','kepala_sekolah']))
            <div x-data="{ open: {{ request()->routeIs('pos.*') ? 'true' : 'false' }} }">
                <button @click="open = !open"
                        class="w-full flex justify-between items-center px-3 py-2.5 rounded-xl text-sm font-medium transition
                               {{ request()->routeIs('pos.*') ? 'bg-white/15 text-white' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                        <span>POS Sekolah</span>
                    </div>
                    <svg class="w-4 h-4 transition-transform duration-200" :class="{'rotate-180': open}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" x-cloak class="sidebar-submenu mt-1 space-y-1 rounded-xl p-1">
                    @if(in_array(Auth::user()->role, ['admin','tu']))
                    <a href="{{ route('pos.items.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs transition
                              {{ request()->routeIs('pos.items.*') ? 'sidebar-active font-bold shadow-sm' : 'text-white/70 hover:text-white hover:bg-white/10' }}">
                        <span>Master Barang</span>
                    </a>
                    <a href="{{ route('pos.bundles.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs transition
                              {{ request()->routeIs('pos.bundles.*') ? 'sidebar-active font-bold shadow-sm' : 'text-white/70 hover:text-white hover:bg-white/10' }}">
                        <span>Paket Bundling</span>
                    </a>
                    @endif
                    @if(in_array(Auth::user()->role, ['admin','tu','staf']))
                    <a href="{{ route('pos.transaction') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs transition
                              {{ request()->routeIs('pos.transaction') ? 'sidebar-active font-bold shadow-sm' : 'text-white/70 hover:text-white hover:bg-white/10' }}">
                        <span>Kasir / Transaksi</span>
                    </a>
                    @endif
                    @if(in_array(Auth::user()->role, ['admin','tu','kepala_sekolah']))
                    <a href="{{ route('pos.history.index') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs transition
                              {{ request()->routeIs('pos.history.*') ? 'sidebar-active font-bold shadow-sm' : 'text-white/70 hover:text-white hover:bg-white/10' }}">
                        <span>Riwayat Transaksi</span>
                    </a>
                    @endif
                </div>
            </div>
            @endif

            {{-- Konfigurasi: admin only --}}
            @if(Auth::user()->role === 'admin')
            <p class="px-3 text-[10px] font-bold text-white/50 uppercase tracking-widest mb-1 mt-5">Pengaturan</p>
            <div x-data="{ open: {{ request()->routeIs('settings.*') || request()->routeIs('school.*') ? 'true' : 'false' }} }">
                <button @click="open = !open"
                        class="w-full flex justify-between items-center px-3 py-2.5 rounded-xl text-sm font-medium transition
                               {{ request()->routeIs('settings.*') || request()->routeIs('school.*') ? 'bg-white/15 text-white' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span>Konfigurasi</span>
                    </div>
                    <svg class="w-4 h-4 transition-transform duration-200" :class="{'rotate-180': open}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" x-cloak class="sidebar-submenu mt-1 space-y-1 rounded-xl p-1">

                    <a href="{{ route('school.settings') }}"
                       class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs transition
                              {{ request()->routeIs('school.settings') ? 'sidebar-active font-bold shadow-sm' : 'text-white/70 hover:text-white hover:bg-white/10' }}">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        <span>Identitas Sekolah</span>
                    </a>
                </div>
            </div>
            @endif

            {{-- FITUR 4: Audit Trail (admin only) --}}
            @if(Auth::user()->role === 'admin')
            <a href="{{ route('audit.index') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition
                      {{ request()->routeIs('audit.*') ? 'sidebar-active shadow-md' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <span>Audit Trail</span>
            </a>
            @endif

            {{-- FITUR 5: Laporan Bulanan --}}
            @if(in_array(Auth::user()->role, ['admin','tu','kepala_sekolah']))
            <a href="{{ route('reports.monthly') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition
                      {{ request()->routeIs('reports.*') ? 'sidebar-active shadow-md' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>Laporan Bulanan</span>
            </a>
            @endif

            {{-- Panduan --}}
            <a href="{{ route('panduan.index') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition
                      {{ request()->routeIs('panduan.*') ? 'sidebar-active shadow-md' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
                <span>Panduan</span>
            </a>

            {{-- Profile --}}
            <a href="{{ route('profile.edit') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition
                      {{ request()->routeIs('profile.*') ? 'sidebar-active shadow-md' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                <span>Profile User</span>
            </a>
        </nav>

        {{-- Footer Sidebar: Avatar + Logout --}}
        <div class="sidebar-footer p-4 border-t border-white/10 flex-shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-white flex items-center justify-center font-bold shadow-lg flex-shrink-0"
                     style="color: var(--sb-active)">
                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                </div>
                <div class="overflow-hidden flex-1 min-w-0">
                    <p class="text-xs font-bold truncate text-white">{{ Auth::user()->name }}</p>
                    <p class="text-[10px] text-white/50 capitalize">{{ Auth::user()->role }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="flex-shrink-0">
                    @csrf
                    <button type="submit" title="Logout"
                            class="text-white/50 hover:text-white transition p-1 rounded-lg hover:bg-white/10">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Overlay mobile --}}
    <div x-show="sidebarOpen" @click="sidebarOpen = false"
         x-transition.opacity
         class="fixed inset-0 z-40 bg-black/50 lg:hidden backdrop-blur-sm" x-cloak></div>

    {{-- ═══════════════════════════════════════════════════════
         MAIN CONTENT
    ═══════════════════════════════════════════════════════ --}}
    <div class="flex flex-col min-h-screen lg:pl-64">

        <header class="h-16 flex items-center justify-between gap-3 px-6 md:px-8 bg-white border-b border-gray-200 shadow-sm sticky top-0 z-30 dm-header">
            <div class="flex items-center gap-3 min-w-0 flex-1">
                <button @click="sidebarOpen = true" class="lg:hidden p-2 rounded-lg text-gray-500 hover:bg-gray-100 shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                @if (isset($header))
                    <div class="font-bold text-gray-800 text-base md:text-lg truncate">{{ $header }}</div>
                @elseif (isset($pageTitle))
                    <h2 class="text-base md:text-lg font-bold text-gray-800 truncate">{{ $pageTitle }}</h2>
                @else
                    <h2 class="text-base md:text-lg font-bold text-gray-800">Dashboard</h2>
                @endif
            </div>
            @if (isset($headerAction))
            <div class="shrink-0">{!! $headerAction !!}</div>
            @endif

            {{-- FITUR 6: Dark Mode Toggle --}}
            <button id="darkModeToggle"
                    title="Toggle Dark Mode"
                    class="ml-2 p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition shrink-0"
                    aria-label="Toggle dark mode">
                {{-- Sun icon (shown in dark mode) --}}
                <svg id="iconSun" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                {{-- Moon icon (shown in light mode) --}}
                <svg id="iconMoon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                </svg>
            </button>
        </header>
            <div id="main-content-area" class="w-full min-h-full px-6 py-6 lg:px-8 lg:py-8 dm-content">
                {{ $slot }}
            </div>
        </main>

        <footer class="px-6 lg:px-8 py-4 text-xs text-gray-400 dm-text-muted">
            &copy; {{ date('Y') }} {{ $schoolName }}. All rights reserved.
        </footer>
    </div>


    {{-- FITUR 6: Dark Mode JS --}}
    <script>
        (function() {
            // Restore dark mode from localStorage on load
            if (localStorage.getItem('darkMode') === '1') {
                document.documentElement.classList.add('dark');
            }
        })();

        document.addEventListener('DOMContentLoaded', function () {
            var toggle   = document.getElementById('darkModeToggle');
            var iconSun  = document.getElementById('iconSun');
            var iconMoon = document.getElementById('iconMoon');

            function applyDark(dark) {
                if (dark) {
                    document.documentElement.classList.add('dark');
                    iconSun.classList.remove('hidden');
                    iconMoon.classList.add('hidden');
                } else {
                    document.documentElement.classList.remove('dark');
                    iconSun.classList.add('hidden');
                    iconMoon.classList.remove('hidden');
                }
            }

            // Init state from localStorage
            applyDark(localStorage.getItem('darkMode') === '1');

            if (toggle) {
                toggle.addEventListener('click', function () {
                    var isDark = document.documentElement.classList.contains('dark');
                    localStorage.setItem('darkMode', isDark ? '0' : '1');
                    applyDark(!isDark);
                });
            }
        });
    </script>

    {{-- Global SweetAlert Flash Handler --}}
    <script>
        const _swalToast = Swal.mixin({
            toast: true, position: 'top-end',
            showConfirmButton: false, timer: 3000, timerProgressBar: true,
            didOpen: (toast) => {
                toast.onmouseenter = Swal.stopTimer;
                toast.onmouseleave = Swal.resumeTimer;
            }
        });
        @if(session('success'))
            _swalToast.fire({ icon: 'success', title: '{{ addslashes(session('success')) }}' });
        @endif
        @if(session('error'))
            Swal.fire({ icon: 'error', title: 'Gagal!', text: '{{ addslashes(session('error')) }}', confirmButtonColor: '#ef4444' });
        @endif
        @if(session('warning'))
            _swalToast.fire({ icon: 'warning', title: '{{ addslashes(session('warning')) }}' });
        @endif
        @if(session('info'))
            _swalToast.fire({ icon: 'info', title: '{{ addslashes(session('info')) }}' });
        @endif
    </script>

    @if(in_array(Auth::user()->role, ['admin','tu']))
    {{-- ═══════════════════════════════════════════
         FLOATING CHAT WIDGET — TU Panel
         3 states: bubble → inbox → room
    ═══════════════════════════════════════════ --}}
    <div x-data="tuChatWidget()" x-init="init()"
        class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3"
        style="position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;"
    >
        {{-- Panel --}}
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 scale-95"
            x-cloak
            class="w-80 bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden flex flex-col"
            style="height: 440px;"
        >
            {{-- Header --}}
            <div class="bg-gradient-to-r from-sky-600 to-blue-700 px-4 py-3 flex items-center gap-3">
                <button
                    x-show="view === 'room'"
                    @click="view = 'inbox'; activeStudent = null; stopPoll()"
                    class="text-white hover:text-sky-200 transition flex-shrink-0"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </button>
                <div class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-white font-semibold text-sm leading-none" x-text="view === 'room' ? activeStudent?.name : 'Pesan Masuk'"></p>
                    <p class="text-sky-200 text-xs mt-0.5" x-text="view === 'room' ? ('NIS ' + activeStudent?.nis + ' · ' + (activeStudent?.kelas ?? '-')) : 'Chat dari siswa'"></p>
                </div>
                <button @click="open = false" class="text-white/70 hover:text-white transition flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
            </div>

            {{-- INBOX VIEW --}}
            <div x-show="view === 'inbox'" class="flex-1 overflow-y-auto">
                <template x-if="threads.length === 0">
                    <div class="flex flex-col items-center justify-center h-full text-center px-6">
                        <svg class="w-10 h-10 text-gray-300 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>
                        <p class="text-gray-400 text-xs">Belum ada pesan dari siswa</p>
                    </div>
                </template>
                <template x-for="thread in threads" :key="thread.student_id">
                    <button
                        @click="openRoom(thread)"
                        class="w-full flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition border-b border-gray-50 text-left"
                    >
                        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-sky-400 to-blue-600 flex items-center justify-center flex-shrink-0">
                            <span class="text-white text-xs font-bold" x-text="thread.student_name.charAt(0).toUpperCase()"></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-semibold text-gray-800 truncate" x-text="thread.student_name"></p>
                                <span class="text-xs text-gray-400 flex-shrink-0 ml-2" x-text="thread.last_time"></span>
                            </div>
                            <p class="text-xs text-sky-600 font-medium mt-0.5" x-text="'NIS ' + thread.student_nis + ' · ' + thread.student_kelas"></p>
                            <p class="text-xs text-gray-500 truncate mt-0.5" x-text="thread.last_message"></p>
                        </div>
                        <template x-if="thread.unread > 0">
                            <span class="bg-red-500 text-white text-xs font-bold w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0" x-text="thread.unread"></span>
                        </template>
                    </button>
                </template>
            </div>

            {{-- ROOM VIEW --}}
            <div x-show="view === 'room'" class="flex flex-col flex-1 overflow-hidden">
                {{-- Messages --}}
                <div x-ref="msgBox" class="flex-1 overflow-y-auto px-4 py-3 space-y-2">
                    <template x-for="msg in messages" :key="msg.id">
                        <div :class="msg.sender === 'tu' ? 'flex justify-end' : 'flex justify-start'">
                            <div
                                :class="msg.sender === 'tu'
                                    ? 'bg-blue-600 text-white rounded-2xl rounded-br-sm'
                                    : 'bg-gray-100 text-gray-800 rounded-2xl rounded-bl-sm'"
                                class="max-w-[75%] px-3 py-2 text-sm"
                            >
                                <p x-text="msg.message"></p>
                                <p class="text-xs mt-1 opacity-60 text-right" x-text="msg.created_at"></p>
                            </div>
                        </div>
                    </template>
                </div>
                {{-- Input --}}
                <div class="px-3 py-3 border-t border-gray-100 flex gap-2">
                    <input
                        x-model="reply"
                        @keydown.enter.prevent="sendReply()"
                        type="text"
                        placeholder="Ketik balasan..."
                        class="flex-1 bg-gray-100 rounded-full px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
                    />
                    <button
                        @click="sendReply()"
                        :disabled="!reply.trim() || sending"
                        class="w-8 h-8 bg-blue-600 hover:bg-blue-700 disabled:opacity-40 text-white rounded-full flex items-center justify-center transition flex-shrink-0"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- Bubble Button --}}
        <button
            @click="toggleWidget()"
            class="w-14 h-14 bg-gradient-to-br from-sky-500 to-blue-700 hover:from-sky-600 hover:to-blue-800 text-white rounded-full shadow-xl flex items-center justify-center transition-all duration-200 relative"
        >
            <template x-if="!open">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
            </template>
            <template x-if="open">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </template>
            <span
                x-show="!open && unread > 0"
                x-text="unread > 9 ? '9+' : unread"
                class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold w-5 h-5 rounded-full flex items-center justify-center"
            ></span>
        </button>
    </div>

    <script>
    function tuChatWidget() {
        return {
            open: false,
            view: 'inbox',       // 'inbox' | 'room'
            threads: [],
            activeStudent: null,
            messages: [],
            reply: '',
            sending: false,
            unread: 0,
            pollTimer: null,
            unreadTimer: null,

            init() {
                this.fetchInbox();
                this.startUnreadPoll();
            },

            toggleWidget() {
                this.open = !this.open;
                if (this.open && this.view === 'inbox') this.fetchInbox();
            },

            startUnreadPoll() {
                this.unreadTimer = setInterval(() => this.fetchUnread(), 10000);
                this.fetchUnread();
            },

            fetchUnread() {
                fetch('{{ route('chat.unread') }}')
                    .then(r => r.json())
                    .then(d => { this.unread = d.unread; });
            },

            fetchInbox() {
                fetch('{{ route('chat.inbox') }}', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(d => { this.threads = d.threads || []; this.unread = d.totalUnread || 0; })
                    .catch(() => {});
            },

            openRoom(thread) {
                this.activeStudent = { id: thread.student_id, name: thread.student_name, nis: thread.student_nis, kelas: thread.student_kelas };
                this.messages = [];
                this.view = 'room';
                this.fetchMessages();
                this.startPoll();
            },

            fetchMessages() {
                if (!this.activeStudent) return;
                const lastId = this.messages.length ? this.messages[this.messages.length - 1].id : 0;
                fetch(`/chat/${this.activeStudent.id}/poll?after_id=${lastId}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(r => r.json())
                    .then(d => {
                        if (d.messages && d.messages.length) {
                            this.messages.push(...d.messages);
                            this.$nextTick(() => {
                                const box = this.$refs.msgBox;
                                if (box) box.scrollTop = box.scrollHeight;
                            });
                        }
                    });
            },

            startPoll() {
                this.stopPoll();
                this.pollTimer = setInterval(() => this.fetchMessages(), 4000);
            },

            stopPoll() {
                if (this.pollTimer) { clearInterval(this.pollTimer); this.pollTimer = null; }
            },

            sendReply() {
                if (!this.reply.trim() || this.sending || !this.activeStudent) return;
                this.sending = true;
                const text = this.reply;
                this.reply = '';
                fetch(`/chat/${this.activeStudent.id}/reply`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ message: text })
                })
                .then(r => r.json())
                .then(d => {
                    if (d.success && d.msg) {
                        this.messages.push(d.msg);
                        this.$nextTick(() => {
                            const box = this.$refs.msgBox;
                            if (box) box.scrollTop = box.scrollHeight;
                        });
                    }
                })
                .finally(() => { this.sending = false; });
            },
        };
    }
    </script>
    @endif

