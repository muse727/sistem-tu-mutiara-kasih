<x-app-layout>
    <x-slot name="header">Audit Trail</x-slot>

    <div class="space-y-6">

        {{-- ===== HEADER ===== --}}
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div class="flex items-center gap-4">
                <div class="p-3 bg-gradient-to-br from-sky-500/10 to-blue-600/10 rounded-2xl border border-gray-200/50">
                    <svg class="w-7 h-7 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Audit Trail</h1>
                    <p class="text-sm text-gray-500 mt-0.5">Log aktivitas sistem — hanya dapat diakses oleh Admin</p>
                </div>
            </div>

            {{-- Export Buttons --}}
            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('audit.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}"
                   class="flex items-center gap-2 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors shadow-sm shadow-emerald-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Export XLSX
                </a>
                <a href="{{ route('audit.export', array_merge(request()->query(), ['format' => 'csv'])) }}"
                   class="flex items-center gap-2 bg-white border border-gray-200 hover:border-sky-400 hover:bg-white text-[#171717] border border-[#EDEDED] shadow-sm hover:text-sky-700 text-sm font-semibold px-4 py-2.5 rounded-xl transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Export CSV
                </a>
            </div>
        </div>

        {{-- ===== STAT CARDS ===== --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @php
                $totalAll   = \App\Models\AuditLog::count();
                $totalToday = \App\Models\AuditLog::whereDate('created_at', today())->count();
                $totalFiltered = $logs->total();
                $uniqueUsers = \App\Models\AuditLog::distinct('user_id')->count('user_id');
            @endphp

            {{-- Total Semua --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="p-2 bg-sky-50 rounded-xl">
                        <svg class="w-5 h-5 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($totalAll) }}</p>
                <p class="text-xs text-gray-400 mt-0.5">Total Semua Log</p>
            </div>

            {{-- Hasil Filter --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="p-2 bg-violet-50 rounded-xl">
                        <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($totalFiltered) }}</p>
                <p class="text-xs text-gray-400 mt-0.5">Hasil Filter Aktif</p>
            </div>

            {{-- Hari Ini --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="p-2 bg-amber-50 rounded-xl">
                        <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($totalToday) }}</p>
                <p class="text-xs text-gray-400 mt-0.5">Aktivitas Hari Ini</p>
            </div>

            {{-- Unique Users --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <div class="p-2 bg-emerald-50 rounded-xl">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                        </svg>
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($uniqueUsers) }}</p>
                <p class="text-xs text-gray-400 mt-0.5">User Aktif</p>
            </div>
        </div>

        {{-- ===== FILTER CARD ===== --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <div class="flex items-center gap-2 mb-4">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                </svg>
                <span class="text-sm font-semibold text-gray-700">Filter & Rentang Tanggal</span>
                @if(request()->hasAny(['search','action','module','start_date','end_date']))
                    <span class="ml-auto text-xs bg-white text-[#171717] border border-[#EDEDED] shadow-sm font-semibold px-2 py-0.5 rounded-full">Filter aktif</span>
                @endif
            </div>

            <form method="GET" action="{{ route('audit.index') }}">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3">
                    {{-- Cari --}}
                    <div class="xl:col-span-1">
                        <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">Cari</label>
                        <div class="relative">
                            <input type="text" name="search" value="{{ request('search') }}"
                                   placeholder="Aksi / deskripsi..."
                                   class="w-full pl-9 pr-3 py-2.5 rounded-xl border-gray-200 text-sm focus:ring-sky-400 focus:border-sky-400 shadow-sm">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                    </div>

                    {{-- Aksi --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">Aksi</label>
                        <select name="action" class="w-full rounded-xl border-gray-200 text-sm focus:ring-sky-400 focus:border-sky-400 shadow-sm py-2.5">
                            <option value="">Semua Aksi</option>
                            @foreach($actions as $act)
                                <option value="{{ $act }}" {{ request('action') == $act ? 'selected' : '' }}>
                                    {{ $act }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Modul --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">Modul</label>
                        <select name="module" class="w-full rounded-xl border-gray-200 text-sm focus:ring-sky-400 focus:border-sky-400 shadow-sm py-2.5">
                            <option value="">Semua Modul</option>
                            @foreach($modules as $mod)
                                <option value="{{ $mod }}" {{ request('module') == $mod ? 'selected' : '' }}>
                                    {{ strtoupper($mod) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Tanggal Mulai --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">
                            <span class="flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                Tanggal Mulai
                            </span>
                        </label>
                        <input type="date" name="start_date" value="{{ request('start_date') }}"
                               class="w-full rounded-xl border-gray-200 text-sm focus:ring-sky-400 focus:border-sky-400 shadow-sm py-2.5">
                    </div>

                    {{-- Tanggal Akhir --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1.5 uppercase tracking-wide">
                            <span class="flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                Tanggal Akhir
                            </span>
                        </label>
                        <input type="date" name="end_date" value="{{ request('end_date') }}"
                               class="w-full rounded-xl border-gray-200 text-sm focus:ring-sky-400 focus:border-sky-400 shadow-sm py-2.5">
                    </div>
                </div>

                <div class="flex items-center gap-2 mt-4">
                    <button type="submit"
                            class="flex items-center gap-2 bg-[#F56C3E] hover:bg-sky-700 text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition-colors shadow-sm shadow-sky-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        Terapkan Filter
                    </button>
                    <a href="{{ route('audit.index') }}"
                       class="flex items-center gap-2 bg-white border border-gray-200 hover:border-gray-300 text-gray-600 text-sm font-semibold px-5 py-2.5 rounded-xl transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Reset
                    </a>

                    {{-- Export shortcut di filter row --}}
                    @if(request()->hasAny(['start_date','end_date','action','module']))
                    <span class="ml-auto text-xs text-gray-400">Export sesuai filter aktif:</span>
                    <a href="{{ route('audit.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}"
                       class="text-xs text-emerald-600 hover:text-emerald-700 font-semibold underline underline-offset-2">XLSX</a>
                    <span class="text-gray-300">|</span>
                    <a href="{{ route('audit.export', array_merge(request()->query(), ['format' => 'csv'])) }}"
                       class="text-xs text-sky-600 hover:text-sky-700 font-semibold underline underline-offset-2">CSV</a>
                    @endif
                </div>
            </form>
        </div>

        {{-- ===== TABLE CARD ===== --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            {{-- Table Header --}}
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <span class="text-sm font-semibold text-gray-700">Log Aktivitas</span>
                </div>
                <span class="text-sm text-gray-400">
                    Menampilkan <span class="font-semibold text-gray-700">{{ $logs->firstItem() ?? 0 }}–{{ $logs->lastItem() ?? 0 }}</span>
                    dari <span class="font-semibold text-gray-700">{{ number_format($logs->total()) }}</span> log
                </span>
            </div>

            {{-- Table --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100">
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-36">Waktu</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Modul</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Detail</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-32">IP</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-20">Sumber</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse($logs as $log)
                        <tr class="hover:bg-sky-50/30 transition-colors group">
                            {{-- Waktu --}}
                            <td class="px-5 py-4">
                                <p class="font-medium text-gray-800 text-xs">
                                    {{ $log->created_at->format('d/m/Y') }}
                                </p>
                                <p class="text-xs text-gray-400 mt-0.5 font-mono">
                                    {{ $log->created_at->format('H:i:s') }}
                                </p>
                            </td>

                            {{-- User --}}
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-full bg-gradient-to-br from-sky-400 to-blue-600 flex items-center justify-center text-white text-xs font-bold shrink-0">
                                        {{ strtoupper(substr($log->user->name ?? 'U', 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-800 text-xs leading-tight">{{ $log->user->name ?? '—' }}</p>
                                        <p class="text-xs text-gray-400">{{ $log->user->email ?? '' }}</p>
                                    </div>
                                </div>
                            </td>

                            {{-- Aksi --}}
                            <td class="px-5 py-4">
                                @php
                                    $badgeClass = match(true) {
                                        str_contains($log->action, 'PAYMENT') || str_contains($log->action, 'PAID')
                                            => 'bg-[#F56C3E] text-white shadow-sm',
                                        str_contains($log->action, 'CREATE') || str_contains($log->action, 'CREATED')
                                            => 'bg-white text-[#171717] border border-[#EDEDED] shadow-sm',
                                        str_contains($log->action, 'DELETE') || str_contains($log->action, 'DELETED')
                                            => 'bg-[#171717] text-white shadow-sm',
                                        str_contains($log->action, 'UPDATE') || str_contains($log->action, 'UPDATED')
                                            => 'bg-white text-[#171717] border border-[#EDEDED] shadow-sm',
                                        str_contains($log->action, 'LOGIN') || str_contains($log->action, 'LOGOUT')
                                            => 'bg-violet-100 text-violet-700 border border-violet-200',
                                        default => 'bg-white text-[#171717] border border-[#EDEDED] shadow-sm',
                                    };
                                @endphp
                                <span class="inline-flex items-center text-xs font-semibold px-2.5 py-1 rounded-lg {{ $badgeClass }}">
                                    {{ $log->action }}
                                </span>
                            </td>

                            {{-- Modul --}}
                            <td class="px-5 py-4">
                                <span class="text-xs font-mono font-semibold text-gray-500 uppercase bg-gray-50 px-2 py-1 rounded-lg">
                                    {{ $log->module ?? '—' }}
                                </span>
                            </td>

                            {{-- Detail --}}
                            <td class="px-5 py-4 max-w-xs">
                                <p class="text-gray-700 text-xs leading-relaxed line-clamp-2">{{ $log->description }}</p>
                                @if($log->auditable_type && $log->auditable_id)
                                    <p class="text-xs text-gray-400 mt-0.5 font-mono">
                                        {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}
                                    </p>
                                @endif
                            </td>

                            {{-- IP --}}
                            <td class="px-5 py-4">
                                <span class="text-xs font-mono text-gray-500">{{ $log->ip_address ?? '—' }}</span>
                            </td>

                            {{-- Sumber --}}
                            <td class="px-5 py-4">
                                <span class="inline-flex items-center text-xs font-semibold px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 border border-slate-200">
                                    {{ strtoupper($log->source ?? 'WEB') }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-5 py-16 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="w-14 h-14 bg-gray-50 rounded-2xl flex items-center justify-center">
                                        <svg class="w-7 h-7 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                        </svg>
                                    </div>
                                    <p class="text-gray-400 text-sm font-medium">Belum ada log audit</p>
                                    @if(request()->hasAny(['search','action','module','start_date','end_date']))
                                        <a href="{{ route('audit.index') }}" class="text-[#F56C3E] text-xs hover:underline">Reset filter</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($logs->hasPages())
            <div class="px-6 py-4 border-t border-gray-100">
                {{ $logs->links() }}
            </div>
            @endif
        </div>

    </div>
</x-app-layout>
