<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Riwayat Pembayaran — {{ $student->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-screen pb-32 bg-gray-50">

    {{-- ===== HEADER ===== --}}
    <div class="bg-[#F56C3E] px-4 pt-10 pb-16">
        <div class="flex items-center max-w-md gap-3 mx-auto">
            <a href="{{ route('siswa.dashboard') }}"
               class="flex items-center gap-1.5 bg-white/15 hover:bg-white/25 text-white text-xs font-medium px-3 py-2 rounded-xl transition-colors shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
                Kembali
            </a>
            <div class="min-w-0">
                <p class="text-base font-bold leading-tight text-white">Riwayat Pembayaran</p>
                <p class="mt-0.5 text-xs text-white/80">{{ $schoolName }}</p>
            </div>
        </div>
    </div>

    <div class="max-w-md px-4 mx-auto -mt-10">

        {{-- ===== FILTER ===== --}}
        <form method="GET" action="{{ route('siswa.history') }}"
              class="p-4 mb-4 bg-white border border-gray-100 shadow-sm rounded-2xl">
            <div class="grid grid-cols-2 gap-3">
                {{-- Tahun --}}
                <div>
                    <label for="tahun" class="block mb-1.5 text-xs font-semibold tracking-wide text-gray-500 uppercase">Tahun</label>
                    <select id="tahun" name="tahun" onchange="this.form.submit()"
                            class="w-full px-3 py-2.5 text-sm text-[#171717] bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#F56C3E]/40 focus:border-[#F56C3E]">
                        <option value="">Semua Tahun</option>
                        @foreach($availableYears as $year)
                            <option value="{{ $year }}" {{ (string) $tahun === (string) $year ? 'selected' : '' }}>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- Bulan --}}
                <div>
                    <label for="bulan" class="block mb-1.5 text-xs font-semibold tracking-wide text-gray-500 uppercase">Bulan</label>
                    <select id="bulan" name="bulan" onchange="this.form.submit()"
                            class="w-full px-3 py-2.5 text-sm text-[#171717] bg-white border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#F56C3E]/40 focus:border-[#F56C3E]">
                        <option value="">Semua Bulan</option>
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ (string) $bulan === (string) $m ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::createFromDate(null, $m, 1)->locale('id')->isoFormat('MMMM') }}
                            </option>
                        @endfor
                    </select>
                </div>
            </div>
            @if($tahun || $bulan)
                <div class="flex items-center justify-between mt-3">
                    <p class="text-xs text-gray-400">
                        Menampilkan periode
                        <span class="font-semibold text-[#F56C3E]">
                            {{ $bulan ? \Carbon\Carbon::createFromDate(null, (int) $bulan, 1)->locale('id')->isoFormat('MMMM') : 'Semua Bulan' }}
                            {{ $tahun ?: 'Semua Tahun' }}
                        </span>
                    </p>
                    <a href="{{ route('siswa.history') }}" class="text-xs font-semibold text-[#F56C3E] hover:underline">Reset</a>
                </div>
            @endif
        </form>

        {{-- ===== DAFTAR RIWAYAT ===== --}}
        <div class="overflow-hidden bg-white border border-gray-100 shadow-sm rounded-2xl">
            @if($paidBills->count() > 0)
                <div class="divide-y divide-gray-50">
                    @foreach($paidBills as $bill)
                        <div class="flex items-center gap-3 px-4 py-3.5">
                            {{-- Left: icon --}}
                            <div class="flex items-center justify-center w-10 h-10 bg-green-100 rounded-xl shrink-0">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            {{-- Center: info --}}
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ $bill->name ?? 'Tagihan ' . $bill->bill_month . '/' . $bill->bill_year }}</p>
                                <p class="text-xs text-gray-400 mt-0.5 truncate">
                                    {{ $bill->bill_month && $bill->bill_year
                                        ? \Carbon\Carbon::createFromDate($bill->bill_year, $bill->bill_month, 1)->locale('id')->isoFormat('MMMM Y')
                                        : 'Periode tidak tersedia' }}
                                    @if($bill->paid_at)
                                        · Dibayar {{ $bill->paid_at->isoFormat('D MMM Y') }}
                                    @endif
                                    @if($bill->payment_method)
                                        · <span class="capitalize">{{ $bill->payment_method }}</span>
                                    @endif
                                </p>
                                <a href="{{ route('siswa.tagihan.struk', $bill->id) }}" class="text-xs text-[#F56C3E] font-medium mt-1 inline-block">Lihat Struk →</a>
                            </div>
                            {{-- Right: amount + badge --}}
                            <div class="text-right shrink-0">
                                <p class="text-sm font-bold text-[#171717]">Rp {{ number_format($bill->amount, 0, ',', '.') }}</p>
                                <span class="inline-block mt-1.5 bg-[#F56C3E] text-white text-xs px-2 py-0.5 rounded-full font-semibold">
                                    Lunas
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- ===== PAGINASI (hanya jika lebih dari 1 halaman) ===== --}}
                @if($paidBills->hasPages())
                    <div class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                        <p class="text-xs text-gray-400">
                            {{ $paidBills->firstItem() }}–{{ $paidBills->lastItem() }} dari {{ $paidBills->total() }}
                        </p>
                        <div class="flex items-center gap-2">
                            @if($paidBills->onFirstPage())
                                <span class="flex items-center gap-1 px-3 py-2 text-xs font-semibold text-gray-300 border border-gray-100 cursor-not-allowed rounded-xl">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                                    Sebelumnya
                                </span>
                            @else
                                <a href="{{ $paidBills->previousPageUrl() }}"
                                   class="flex items-center gap-1 px-3 py-2 text-xs font-semibold text-[#171717] border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                                    Sebelumnya
                                </a>
                            @endif

                            <span class="px-2 text-xs font-semibold text-gray-500">
                                {{ $paidBills->currentPage() }} / {{ $paidBills->lastPage() }}
                            </span>

                            @if($paidBills->hasMorePages())
                                <a href="{{ $paidBills->nextPageUrl() }}"
                                   class="flex items-center gap-1 px-3 py-2 text-xs font-semibold text-white bg-[#F56C3E] hover:bg-[#e45322] rounded-xl transition-colors">
                                    Berikutnya
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            @else
                                <span class="flex items-center gap-1 px-3 py-2 text-xs font-semibold text-gray-300 border border-gray-100 cursor-not-allowed rounded-xl">
                                    Berikutnya
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                </span>
                            @endif
                        </div>
                    </div>
                @endif
            @else
                {{-- ===== EMPTY STATE ===== --}}
                <div class="px-4 py-16 text-center">
                    @if($hasAnyPaid)
                        <p class="text-sm text-gray-400">Tidak ada pembayaran untuk periode ini.</p>
                        <a href="{{ route('siswa.history') }}" class="inline-block mt-2 text-xs font-semibold text-[#F56C3E] hover:underline">Lihat semua riwayat</a>
                    @else
                        <p class="text-sm text-gray-400">Belum ada riwayat pembayaran.</p>
                    @endif
                </div>
            @endif
        </div>

        {{-- ===== INFO FOOTER ===== --}}
        <p class="mt-8 text-xs text-center text-gray-400">
            {{ $schoolName }} &middot; Portal Siswa
        </p>

    </div>

    {{-- ===== BOTTOM NAVIGATION (app shell) ===== --}}
    @include('siswa.partials.bottom-nav', ['active' => 'pembayaran'])

</body>
</html>
