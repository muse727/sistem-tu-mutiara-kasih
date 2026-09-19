<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Portal Siswa — {{ $student->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    {{-- Payment provider aktif (selector Task 1). Reversible via STUDENT_PAYMENT_PROVIDER. --}}
    <script>
        window.STUDENT_PAYMENT = {
            provider:  @json(config('services.student_payment.provider', 'midtrans')),
            clientKey: @json(config('services.midtrans.client_key')),
        };
    </script>
    {{-- Snap.js sandbox hanya dimuat saat provider aktif = midtrans (conditional, tidak duplikat). --}}
    @if (config('services.student_payment.provider', 'midtrans') === 'midtrans')
        <script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="{{ config('services.midtrans.client_key') }}"></script>
    @endif
    <style>
        [x-cloak] { display: none !important; }
        .bill-card { transition: box-shadow 0.15s ease, transform 0.15s ease; }
        .bill-card:active { transform: scale(0.98); }

        /* Chat Widget */
        #chat-fab { cursor: grab; user-select: none; }
        #chat-fab:active { cursor: grabbing; }
        .chat-bubble-in  { background:#F5F5F5; border-radius:4px 18px 18px 18px; }
        .chat-bubble-out { background:#F56C3E; color:#fff; border-radius:18px 4px 18px 18px; }
        #chat-messages   { scroll-behavior: smooth; }
    </style>
</head>
<body class="min-h-screen pb-32 bg-gray-50" x-data="{ tab: 'unpaid', selectedBills: [], selectedItems: [], selectedTotal: 0, multiSheetOpen: false, multiLoading: false, chatOpen: false, toggleBill(id, name, amount) { const idx = this.selectedBills.indexOf(id); if (idx === -1) { this.selectedBills.push(id); this.selectedItems.push({id, name, amount}); } else { this.selectedBills.splice(idx, 1); this.selectedItems.splice(idx, 1); } this.selectedTotal = this.selectedItems.reduce((s, i) => s + i.amount, 0); }, togglePilihSemua() { const allIds = {{ $unpaidBills->pluck('id') }}; const allItems = {{ $unpaidBills->map(fn($b) => ['id' => $b->id, 'name' => $b->name ?? 'Tagihan '.$b->bill_month.'/'.$b->bill_year, 'amount' => (int)$b->amount]) }}; if (this.selectedBills.length === allIds.length) { this.selectedBills = []; this.selectedItems = []; this.selectedTotal = 0; } else { this.selectedBills = allIds; this.selectedItems = allItems; this.selectedTotal = this.selectedItems.reduce((s, i) => s + i.amount, 0); } }, openMultiSheet() { if (this.selectedBills.length === 1) { const item = this.selectedItems[0]; window.dispatchEvent(new CustomEvent('open-payment', { detail: { id: item.id, name: item.name, amount: item.amount } })); } else { this.multiSheetOpen = true; } }, ...chatWidget() }">

    {{-- ===== HEADER ===== --}}
    <div class="bg-[#F56C3E] px-4 pt-10 pb-16">
        <div class="flex items-center justify-between max-w-md gap-3 mx-auto">
            <div class="flex items-center min-w-0 gap-3">
                {{-- Avatar + link ke profil --}}
                <a href="{{ route('siswa.profil') }}" class="flex items-center justify-center w-12 h-12 text-lg font-bold text-white transition-colors shadow-inner rounded-2xl bg-white/20 backdrop-blur hover:bg-white/30 shrink-0">
                    {{ strtoupper(substr($student->name, 0, 1)) }}
                </a>
                <div class="min-w-0">
                    <a href="{{ route('siswa.profil') }}" class="hover:underline decoration-white/50">
                        <p class="text-base font-bold leading-tight text-white truncate">{{ $student->name }}</p>
                    </a>
                    <p class="mt-1 text-xs text-white/80">NIS: {{ $student->nis }}</p>
                    <p class="text-white/70 text-xs mt-0.5 truncate">{{ optional($student->kelas)->nama_kelas ?? $schoolName }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-md px-4 mx-auto">

        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="flex items-start gap-2 px-4 py-3 mt-4 border border-green-200 bg-green-50 rounded-2xl">
                <svg class="w-5 h-5 text-[#F56C3E] shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <p class="text-sm font-medium text-green-700">{{ session('success') }}</p>
            </div>
        @endif

        {{-- ===== SUMMARY CARDS ===== --}}
        <div class="grid grid-cols-2 gap-3 mb-6 -mt-10">
            {{-- Belum Bayar --}}
            <div class="p-4 bg-white border border-gray-100 shadow-md rounded-2xl">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-2 h-2 bg-[#F56C3E] rounded-full"></div>
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Belum Bayar</p>
                </div>
                <p class="text-xl font-extrabold leading-tight text-[#171717] truncate">
                    Rp {{ number_format($totalUnpaid, 0, ',', '.') }}
                </p>
                <p class="mt-1.5 text-xs text-gray-400">{{ $unpaidBills->count() }} tagihan</p>
            </div>
            {{-- Sudah Bayar --}}
            <div class="p-4 bg-white border border-gray-100 shadow-md rounded-2xl">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Sudah Bayar</p>
                </div>
                <p class="text-xl font-extrabold leading-tight text-[#171717] truncate">
                    Rp {{ number_format($totalPaid, 0, ',', '.') }}
                </p>
                <p class="mt-1.5 text-xs text-gray-400">{{ $paidBills->count() }} tagihan</p>
            </div>
        </div>

        {{-- ===== TAGIHAN BULAN INI ===== --}}
        <div class="mt-6">
            <h2 class="mb-3 text-sm font-semibold tracking-wide text-gray-500 uppercase">
                Tagihan {{ \Carbon\Carbon::now()->locale('id')->isoFormat('MMMM YYYY') }}
            </h2>
            @if($thisMonthBills->isEmpty())
                <div class="px-4 py-3.5 text-sm text-center text-gray-400 bg-white border border-gray-100 rounded-2xl">
                    Tidak ada tagihan bulan ini
                </div>
            @elseif($thisMonthBills->where('status', '!=', 'PAID')->isEmpty())
                <div class="flex items-center gap-2 px-4 py-3.5 border border-green-100 bg-green-50 rounded-2xl">
                    <span class="text-[#F56C3E] text-lg">✅</span>
                    <span class="text-sm font-medium text-green-700">Semua tagihan bulan ini sudah lunas!</span>
                </div>
            @else
                <div class="overflow-hidden bg-white border border-gray-100 divide-y rounded-2xl divide-gray-50">
                    @foreach($thisMonthBills as $mb)
                    <div class="flex items-center justify-between gap-3 px-4 py-3.5">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-800 truncate">{{ $mb->name }}</p>
                            <p class="mt-0.5 text-sm font-bold text-[#171717]">Rp {{ number_format($mb->amount, 0, ',', '.') }}</p>
                        </div>
                        @if($mb->status === 'PAID')
                            <span class="shrink-0 text-xs font-semibold bg-[#F56C3E] text-white shadow-sm px-2.5 py-1 rounded-full">Lunas</span>
                        @else
                            <span class="shrink-0 text-xs font-semibold bg-[#171717] text-white shadow-sm px-2.5 py-1 rounded-full">Belum Bayar</span>
                        @endif
                    </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ===== TABS ===== --}}
        <div class="mt-6 overflow-hidden bg-white border border-gray-100 shadow-sm rounded-2xl">
            <div class="flex border-b border-gray-100">
                <button
                    @click="tab = 'unpaid'"
                    :class="tab === 'unpaid'
                        ? 'border-b-2 border-[#F56C3E] text-[#F56C3E] font-bold'
                        : 'border-b-2 border-transparent text-gray-400 hover:text-gray-600 font-medium'"
                    class="flex-1 px-4 py-3.5 text-sm transition-colors duration-150"
                >
                    Belum Lunas
                    <span class="ml-1 text-xs font-medium">({{ $unpaidBills->count() }})</span>
                </button>
                <button
                    @click="window.location.href = '{{ route('siswa.history') }}'"
                    class="flex-1 px-4 py-3.5 text-sm font-medium text-gray-400 border-b-2 border-transparent transition-colors duration-150 hover:text-gray-600"
                >
                    Lunas
                    <span class="ml-1 text-xs font-medium">({{ $paidBills->count() }})</span>
                </button>
            </div>

            {{-- ===== BELUM LUNAS ===== --}}
            <div x-show="tab === 'unpaid'" class="divide-y divide-gray-50">

                {{-- Header: Pilih Semua --}}
                @if($unpaidBills->count() > 1)
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gray-50/60">
                    <label class="flex items-center gap-2 cursor-pointer select-none" @click.prevent="togglePilihSemua()">
                        <div class="flex items-center justify-center w-5 h-5 transition-colors border-2 rounded-md"
                             :class="selectedBills.length === {{ $unpaidBills->count() }} ? 'bg-[#F56C3E] border-[#F56C3E]' : 'border-gray-300 bg-white'">
                            <svg x-show="selectedBills.length === {{ $unpaidBills->count() }}" class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-gray-700">Pilih Semua</span>
                    </label>
                    <span class="text-xs text-gray-400" x-show="selectedBills.length > 0" x-text="selectedBills.length + ' dipilih'"></span>
                </div>
                @endif

                @php $unpaidGrouped = $unpaidBills->groupBy('bill_year'); @endphp
                @forelse($unpaidGrouped as $year => $yearBills)
                    <div class="px-4 pt-4 pb-1.5">
                        <p class="text-xs font-semibold tracking-widest text-gray-400 uppercase">{{ $year }}</p>
                    </div>
                    @foreach($yearBills as $bill)
                        <div
                            class="bill-card flex items-center px-4 py-3.5 gap-3 cursor-pointer hover:bg-[#F56C3E]/5 active:bg-[#F56C3E]/10"
                            :class="selectedBills.includes({{ $bill->id }}) ? 'bg-[#F56C3E]/5' : ''"
                            @click="toggleBill({{ $bill->id }}, '{{ addslashes($bill->name ?? 'Tagihan') }}', {{ (int)$bill->amount }})"
                        >
                            {{-- Left: checkbox + icon --}}
                            <div class="flex items-center shrink-0">
                                <div class="flex items-center justify-center w-5 h-5 mr-3 transition-colors border-2 rounded-md"
                                     :class="selectedBills.includes({{ $bill->id }}) ? 'bg-[#F56C3E] border-[#F56C3E]' : 'border-gray-300 bg-white'">
                                    <svg x-show="selectedBills.includes({{ $bill->id }})" class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-[#F56C3E]/10">
                                    <svg class="w-5 h-5 text-[#F56C3E]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                            </div>
                            {{-- Center: info --}}
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ $bill->name ?? 'Tagihan ' . $bill->bill_month . '/' . $bill->bill_year }}</p>
                                <p class="text-xs text-gray-400 mt-0.5 truncate">
                                    @if($bill->due_date)
                                        Jatuh tempo {{ \Carbon\Carbon::parse($bill->due_date)->isoFormat('D MMM Y') }}
                                    @elseif($bill->bill_month && $bill->bill_year)
                                        {{ \Carbon\Carbon::createFromDate($bill->bill_year, $bill->bill_month, 1)->isoFormat('MMMM Y') }}
                                    @else
                                        —
                                    @endif
                                </p>
                                @php
                                    $typeLabel = match($bill->type) {
                                        'SPP'          => ['label' => 'SPP', 'class' => 'bg-white text-[#171717] border border-[#EDEDED]'],
                                        'PAKET'        => ['label' => 'Paket', 'class' => 'bg-white text-[#171717] border border-[#EDEDED]'],
                                        'KANTIN'       => ['label' => 'Kantin', 'class' => 'bg-white text-[#171717] border border-[#EDEDED]'],
                                        'DAFTAR_ULANG' => ['label' => 'Daftar Ulang', 'class' => 'bg-[#F56C3E] text-white'],
                                        default        => ['label' => $bill->type, 'class' => 'bg-gray-100 text-gray-500'],
                                    };
                                @endphp
                                <span class="inline-block mt-1.5 {{ $typeLabel['class'] }} text-xs px-2 py-0.5 rounded-md font-medium">
                                    {{ $typeLabel['label'] }}
                                </span>
                            </div>
                            {{-- Right: amount + badge --}}
                            <div class="text-right shrink-0">
                                @if($bill->original_amount && $bill->original_amount != $bill->amount)
                                    <p class="text-xs text-gray-400 line-through">Rp {{ number_format($bill->original_amount, 0, ',', '.') }}</p>
                                @endif
                                <p class="text-sm font-bold text-[#171717]">Rp {{ number_format($bill->amount, 0, ',', '.') }}</p>
                                <span class="inline-block mt-1.5 bg-[#171717] text-white text-xs px-2 py-0.5 rounded-full font-semibold">
                                    {{ $bill->status === 'PARTIAL' ? 'Sebagian' : 'Belum Bayar' }}
                                </span>
                            </div>
                            <svg class="self-center w-4 h-4 text-gray-300 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                    @endforeach
                @empty
                    <div class="px-4 py-16 text-center">
                        <p class="mb-3 text-5xl">🎉</p>
                        <p class="text-base font-bold text-gray-700">Semua tagihan lunas!</p>
                        <p class="mt-1 text-sm text-gray-400">Tidak ada tagihan yang perlu dibayar.</p>
                    </div>
                @endforelse
            </div>

            {{-- ===== LUNAS (ringkasan terbaru + tautan riwayat) ===== --}}
            @if($paidBills->count() > 0)
            <div>
                <div class="px-4 pt-4 pb-1.5">
                    <p class="text-xs font-semibold tracking-widest text-gray-400 uppercase">Terbaru</p>
                </div>
                @foreach($paidBills->take(3) as $bill)
                        <div class="flex items-center gap-3 px-4 py-3.5 border-t border-gray-50 first:border-t-0">
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
                                    @if($bill->paid_at)
                                        Dibayar {{ $bill->paid_at->isoFormat('D MMM Y') }}
                                    @else
                                        Tanggal pembayaran tidak tersedia
                                    @endif
                                    @if($bill->payment_method)
                                        · <span class="capitalize">{{ $bill->payment_method }}</span>
                                    @endif
                                </p>
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
                <a href="{{ route('siswa.history') }}"
                   class="flex items-center justify-center gap-1.5 px-4 py-3 text-sm font-semibold text-[#F56C3E] border-t border-gray-50 hover:bg-[#F56C3E]/5 transition-colors">
                    Lihat Semua Riwayat
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
            @else
            <div class="px-4 py-12 text-center">
                <p class="text-sm text-gray-400">Belum ada riwayat pembayaran.</p>
            </div>
            @endif
        </div>

        {{-- ===== INFO FOOTER ===== --}}
        <p class="mt-8 text-xs text-center text-gray-400">
            {{ $schoolName }} &middot; Portal Siswa
        </p>

    </div>

    {{-- ===== STICKY BOTTOM BAR (muncul saat ada tagihan dipilih) ===== --}}
    <div
        x-data="{}"
        x-show="selectedBills.length > 0"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-y-full opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-y-0 opacity-100"
        x-transition:leave-end="translate-y-full opacity-0"
        class="fixed left-0 right-0 z-40 no-print"
        style="bottom:calc(72px + env(safe-area-inset-bottom));"
    >
        <div class="max-w-md px-4 pb-2 mx-auto">
            <div class="flex items-center gap-3 px-4 py-3 bg-white border border-gray-100 shadow-xl rounded-2xl">
                {{-- Info --}}
                <div class="flex-1 min-w-0">
                    <p class="text-xs text-gray-400" x-text="selectedBills.length + ' tagihan dipilih'"></p>
                    <p class="text-base font-extrabold text-gray-900" x-text="'Rp ' + selectedTotal.toLocaleString('id-ID')"></p>
                </div>
                {{-- Batalkan --}}
                <button
                    @click="selectedBills = []; selectedItems = []"
                    class="px-3 py-2 text-xs font-medium text-gray-400 transition-colors rounded-xl hover:bg-gray-100 shrink-0"
                >
                    Batal
                </button>
                {{-- Bayar --}}
                <button
                    @click="openMultiSheet()"
                    class="bg-[#F56C3E] hover:bg-[#e45322] active:bg-[#be3e15] text-white text-sm font-bold px-5 py-2.5 rounded-xl transition-colors shrink-0 shadow-md shadow-[#F56C3E]/30"
                    x-text="selectedBills.length === 1 ? 'Bayar' : 'Bayar Dipilih'"
                >
                    Bayar
                </button>
            </div>
        </div>
    </div>

    {{-- ===== MULTI PAYMENT BOTTOM SHEET ===== --}}
    <div
        x-data="{}"
        x-show="multiSheetOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-end"
    >
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="multiSheetOpen = false"></div>

        {{-- Sheet --}}
        <div
            class="relative w-full max-w-md px-5 py-6 mx-auto bg-white shadow-2xl rounded-t-3xl"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="translate-y-full"
            x-transition:enter-end="translate-y-0"
        >
            <div class="w-10 h-1 mx-auto mb-5 bg-gray-200 rounded-full"></div>

            <h3 class="mb-4 text-base font-bold text-gray-900">Konfirmasi Pembayaran</h3>

            {{-- List tagihan dipilih --}}
            <div class="mb-4 space-y-2 overflow-y-auto max-h-48">
                <template x-for="item in selectedItems" :key="item.id">
                    <div class="flex items-center justify-between gap-3 py-2 border-b border-gray-50 last:border-0">
                        <div class="flex items-center min-w-0 gap-2">
                            <div class="w-1.5 h-1.5 rounded-full bg-[#F56C3E] shrink-0"></div>
                            <p class="text-sm text-gray-700 truncate" x-text="item.name"></p>
                        </div>
                        <p class="text-sm font-semibold text-gray-800 shrink-0" x-text="'Rp ' + item.amount.toLocaleString('id-ID')"></p>
                    </div>
                </template>
            </div>

            {{-- Total --}}
            <div class="bg-[#F56C3E]/10 rounded-2xl p-4 mb-4 flex items-center justify-between">
                <div>
                    <p class="text-xs text-[#F56C3E] font-medium" x-text="selectedBills.length + ' tagihan'"></p>
                    <p class="text-sm text-[#963316] font-semibold">Total Pembayaran</p>
                </div>
                <p class="text-xl font-extrabold text-[#963316]" x-text="'Rp ' + selectedTotal.toLocaleString('id-ID')"></p>
            </div>

            <p class="mb-5 text-xs text-center text-gray-400">Pembayaran diproses melalui Flip. Mendukung transfer bank, QRIS, dan e-wallet.</p>

            <div class="space-y-3">
                {{-- Phase E.3 — pembayaran multi-tagihan belum didukung Flip (satu tagihan per bill). Dinonaktifkan; bayar satu per satu. --}}
                <button
                    type="button"
                    disabled
                    title="Pembayaran sekaligus belum tersedia — silakan bayar satu tagihan per satu"
                    class="w-full bg-gray-200 text-gray-400 font-bold py-3.5 rounded-xl flex items-center justify-center gap-2 cursor-not-allowed"
                >
                    <span>Bayar Sekarang</span>
                </button>
                <p class="text-xs text-center text-gray-400">Pembayaran sekaligus belum tersedia. Silakan bayar satu tagihan per satu.</p>
                <button @click="multiSheetOpen = false" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-3.5 rounded-xl transition-colors text-sm">Batal</button>
            </div>
        </div>
    </div>

    {{-- ===== PAYMENT BOTTOM SHEET (Alpine) ===== --}}
    <div
        x-data="{ open: false, billId: null, billName: '', amount: 0, loading: false }"
        x-on:open-payment.window="open = true; billId = $event.detail.id; billName = $event.detail.name; amount = $event.detail.amount"
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 flex items-end"
    >
        {{-- Backdrop --}}
        <div
            class="absolute inset-0 bg-black/50 backdrop-blur-sm"
            @click="open = false"
        ></div>

        {{-- Sheet --}}
        <div
            class="relative w-full max-w-md px-6 py-6 mx-auto bg-white shadow-2xl rounded-t-3xl"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="translate-y-full"
            x-transition:enter-end="translate-y-0"
        >
            <div class="w-10 h-1 mx-auto mb-5 bg-gray-200 rounded-full"></div>

            <h3 class="mb-1 text-lg font-bold text-gray-900">Konfirmasi Pembayaran</h3>
            <p class="mb-5 text-sm text-gray-500" x-text="billName"></p>

            <div class="bg-[#F56C3E]/10 rounded-2xl p-4 mb-5 flex items-center justify-between">
                <p class="text-sm text-[#963316] font-medium">Total Tagihan</p>
                <p class="text-xl font-extrabold text-[#963316]" x-text="'Rp ' + amount.toLocaleString('id-ID')"></p>
            </div>

            <p class="mb-5 text-xs text-center text-gray-400">
                Pembayaran diproses melalui Flip. Mendukung transfer bank, QRIS, dan e-wallet.
            </p>

            <div class="space-y-3">
                <button
                    @click="loading = true; prosesPayment(billId, () => { loading = false; open = false; })"
                    :disabled="loading"
                    class="w-full bg-[#F56C3E] hover:bg-[#e45322] disabled:bg-[#F56C3E]/50 text-white font-bold py-3.5 rounded-xl transition-colors flex items-center justify-center gap-2"
                >
                    <template x-if="loading">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                    </template>
                    <span x-text="loading ? 'Memproses...' : 'Bayar Sekarang'"></span>
                </button>
                <button
                    @click="open = false"
                    class="w-full py-3 text-sm font-medium text-gray-500 transition-colors border border-gray-200 rounded-xl hover:bg-gray-50"
                >
                    Batal
                </button>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Dipanggil saat card tagihan diklik — buka bottom sheet via Alpine custom event
        function initBayar(billId, billName, amount) {
            window.dispatchEvent(new CustomEvent('open-payment', {
                detail: { id: billId, name: billName, amount: amount }
            }));
        }

        // Dipanggil saat tombol "Bayar Sekarang" diklik di bottom sheet SINGLE.
        // Provider dispatch (selector Task 1): midtrans → Snap; flip → hosted page.
        // Backend TETAP authoritative: settlement hanya lewat callback masing-masing provider.
        const PAY_PROVIDER = (window.STUDENT_PAYMENT && window.STUDENT_PAYMENT.provider) || 'midtrans';

        async function prosesPayment(billId, onDone) {
            if (PAY_PROVIDER === 'flip') return prosesPaymentFlip(billId, onDone);
            return prosesPaymentMidtrans(billId, onDone);
        }

        // ── MIDTRANS — existing createToken() → Snap.js ──────────────────────
        async function prosesPaymentMidtrans(billId, onDone) {
            try {
                const res = await fetch(`/siswa/tagihan/${billId}/pay`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept':        'application/json',
                        'Content-Type':  'application/json',
                    },
                });

                const data = await res.json();

                if (!res.ok || !data.snap_token) {
                    alert('Gagal: ' + (data.error || 'Token pembayaran tidak tersedia, coba lagi.'));
                    onDone();
                    return;
                }

                if (typeof window.snap === 'undefined') {
                    alert('Gagal: modul pembayaran belum termuat. Muat ulang halaman.');
                    onDone();
                    return;
                }

                // Buka Snap modal. Settlement TIDAK dari sini — hanya via Midtrans callback.
                window.snap.pay(data.snap_token, {
                    onSuccess: function () { window.location.reload(); },
                    onPending: function () { window.location.reload(); },
                    onError:   function () { alert('Pembayaran gagal. Coba lagi.'); onDone(); },
                    onClose:   function () { onDone(); },
                });
            } catch (e) {
                alert('Error jaringan: ' + e.message);
                onDone();
            }
        }

        // ── FLIP — existing pay-flip → redirect ke hosted page ───────────────
        async function prosesPaymentFlip(billId, onDone) {
            try {
                const res = await fetch(`/siswa/tagihan/${billId}/pay-flip`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept':        'application/json',
                        'Content-Type':  'application/json',
                    },
                });

                const data = await res.json();

                if (!res.ok) {
                    alert('Gagal: ' + (data.error || 'Terjadi kesalahan, coba lagi.'));
                    onDone(); // pulihkan tombol agar bisa coba lagi
                    return;
                }

                // Wajib ada payment_url dari backend. Jangan redirect tanpa itu.
                if (!data.payment_url) {
                    alert('Gagal: tautan pembayaran tidak tersedia. Coba lagi.');
                    onDone();
                    return;
                }

                // Redirect ke halaman pembayaran hosted Flip (bukan popup).
                const url = data.payment_url.startsWith('http') ? data.payment_url : 'https://' + data.payment_url;
                window.location.href = url;
                // onDone sengaja TIDAK dipanggil — halaman berpindah ke Flip.

            } catch (e) {
                alert('Error jaringan: ' + e.message);
                onDone();
            }
        }

        // Phase E.3 — pembayaran multi-tagihan via Snap dinonaktifkan (belum didukung Flip).
        // Backend /siswa/payment/multi (Midtrans) dibiarkan utuh sebagai dukungan historis,
        // tetapi UI multi-pay tidak lagi memanggilnya.
    </script>

    {{-- ===== CHAT PANEL (partial bersama, dibuka lewat bottom-nav "Chat") ===== --}}
    @include('siswa.partials.chat-widget', ['mode' => 'panel'])

    <script>
        // Satu sumber kebenaran untuk buka/tutup chat (dipakai bottom-nav "Chat").
        window.toggleStudentChat = function () {
            const stack = document.body && document.body._x_dataStack;
            if (stack && stack[0] && Object.prototype.hasOwnProperty.call(stack[0], 'chatOpen')) {
                stack[0].chatOpen = !stack[0].chatOpen;
            }
        };
    </script>

    {{-- ===== BOTTOM NAVIGATION (app shell) ===== --}}
    @include('siswa.partials.bottom-nav', ['active' => 'pembayaran', 'chatBadge' => true])

</body>
</html>
