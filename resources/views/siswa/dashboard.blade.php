<!DOCTYPE html>
<html lang="id" x-data="{ tab: 'unpaid', ...chatWidget() }" x-init="chatInit()">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Portal Siswa — {{ $student->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    {{-- Midtrans Snap.js Sandbox --}}
    <script
        src="https://app.sandbox.midtrans.com/snap/snap.js"
        data-client-key="{{ config('services.midtrans.client_key') }}">
    </script>
    <style>
        [x-cloak] { display: none !important; }
        .bill-card { transition: box-shadow 0.15s ease, transform 0.15s ease; }
        .bill-card:active { transform: scale(0.98); }

        /* Chat Widget */
        #chat-fab { cursor: grab; user-select: none; }
        #chat-fab:active { cursor: grabbing; }
        .chat-bubble-in  { background:#f1f5f9; border-radius:4px 18px 18px 18px; }
        .chat-bubble-out { background:#0ea5e9; color:#fff; border-radius:18px 4px 18px 18px; }
        #chat-messages   { scroll-behavior: smooth; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen pb-8">

    {{-- ===== HEADER ===== --}}
    <div class="bg-gradient-to-r from-sky-500 via-sky-600 to-blue-700 px-4 pt-12 pb-20">
        <div class="max-w-md mx-auto flex items-center justify-between">
            <div class="flex items-center gap-3">
                {{-- Avatar initials --}}
                <div class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur flex items-center justify-center text-white font-bold text-lg shadow-inner">
                    {{ strtoupper(substr($student->name, 0, 1)) }}
                </div>
                <div>
                    <p class="text-white font-bold text-base leading-tight">{{ $student->name }}</p>
                    <p class="text-sky-200 text-xs mt-0.5">NIS: {{ $student->nis }}</p>
                    <p class="text-sky-300 text-xs">{{ optional($student->kelas)->nama_kelas ?? $schoolName }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('siswa.logout') }}">
                @csrf
                <button type="submit"
                        class="flex items-center gap-1.5 bg-white/15 hover:bg-white/25 text-white text-xs font-medium px-3 py-2 rounded-xl transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Keluar
                </button>
            </form>
        </div>
    </div>

    <div class="max-w-md mx-auto px-4">

        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="mt-4 bg-green-50 border border-green-200 rounded-2xl px-4 py-3 flex items-start gap-2">
                <svg class="w-5 h-5 text-green-500 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <p class="text-green-700 text-sm font-medium">{{ session('success') }}</p>
            </div>
        @endif

        {{-- ===== SUMMARY CARDS ===== --}}
        <div class="grid grid-cols-2 gap-3 -mt-12 mb-5">
            {{-- Belum Bayar --}}
            <div class="bg-white rounded-2xl p-4 shadow-xl border border-gray-100">
                <div class="flex items-center gap-1.5 mb-2">
                    <div class="w-2 h-2 rounded-full bg-red-400"></div>
                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide">Belum Bayar</p>
                </div>
                <p class="text-lg font-extrabold text-red-600 leading-tight">
                    Rp {{ number_format($totalUnpaid, 0, ',', '.') }}
                </p>
                <p class="text-xs text-gray-400 mt-1">{{ $unpaidBills->count() }} tagihan</p>
            </div>
            {{-- Sudah Bayar --}}
            <div class="bg-white rounded-2xl p-4 shadow-xl border border-gray-100">
                <div class="flex items-center gap-1.5 mb-2">
                    <div class="w-2 h-2 rounded-full bg-green-400"></div>
                    <p class="text-xs text-gray-500 font-semibold uppercase tracking-wide">Sudah Bayar</p>
                </div>
                <p class="text-lg font-extrabold text-green-600 leading-tight">
                    Rp {{ number_format($totalPaid, 0, ',', '.') }}
                </p>
                <p class="text-xs text-gray-400 mt-1">{{ $paidBills->count() }} tagihan</p>
            </div>
        </div>

        {{-- ===== TAGIHAN BULAN INI ===== --}}
        <div class="mt-5">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">
                Tagihan {{ \Carbon\Carbon::now()->locale('id')->isoFormat('MMMM YYYY') }}
            </h2>
            @if($thisMonthBills->isEmpty())
                <div class="bg-white rounded-2xl px-4 py-3 text-sm text-gray-400 text-center border border-gray-100">
                    Tidak ada tagihan bulan ini
                </div>
            @elseif($thisMonthBills->where('status', '!=', 'PAID')->isEmpty())
                <div class="bg-green-50 border border-green-100 rounded-2xl px-4 py-3 flex items-center gap-2">
                    <span class="text-green-500 text-lg">✅</span>
                    <span class="text-sm font-medium text-green-700">Semua tagihan bulan ini sudah lunas!</span>
                </div>
            @else
                <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden divide-y divide-gray-50">
                    @foreach($thisMonthBills as $mb)
                    <div class="flex items-center justify-between px-4 py-3">
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ $mb->name }}</p>
                            <p class="text-xs text-gray-400">Rp {{ number_format($mb->amount, 0, ',', '.') }}</p>
                        </div>
                        @if($mb->status === 'PAID')
                            <span class="text-xs font-semibold bg-green-100 text-green-700 px-2.5 py-1 rounded-full">Lunas</span>
                        @else
                            <span class="text-xs font-semibold bg-red-100 text-red-700 px-2.5 py-1 rounded-full">Belum Bayar</span>
                        @endif
                    </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ===== TABS ===== --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="flex border-b border-gray-100">
                <button
                    @click="tab = 'unpaid'"
                    :class="tab === 'unpaid'
                        ? 'border-b-2 border-sky-600 text-sky-600 font-bold bg-sky-50/50'
                        : 'text-gray-400 hover:text-gray-600'"
                    class="flex-1 py-3.5 text-sm transition-all duration-150"
                >
                    Belum Lunas
                    <span class="ml-1 text-xs">({{ $unpaidBills->count() }})</span>
                </button>
                <button
                    @click="tab = 'paid'"
                    :class="tab === 'paid'
                        ? 'border-b-2 border-sky-600 text-sky-600 font-bold bg-sky-50/50'
                        : 'text-gray-400 hover:text-gray-600'"
                    class="flex-1 py-3.5 text-sm transition-all duration-150"
                >
                    Lunas
                    <span class="ml-1 text-xs">({{ $paidBills->count() }})</span>
                </button>
            </div>

            {{-- ===== BELUM LUNAS ===== --}}
            <div x-show="tab === 'unpaid'" class="divide-y divide-gray-50">
                @php $unpaidGrouped = $unpaidBills->groupBy('bill_year'); @endphp
                @forelse($unpaidGrouped as $year => $yearBills)
                    <div class="px-4 pt-3 pb-1">
                        <p class="text-xs text-gray-400 font-semibold uppercase tracking-widest">{{ $year }}</p>
                    </div>
                    @foreach($yearBills as $bill)
                        <div
                            class="bill-card flex items-center px-4 py-4 gap-3 cursor-pointer hover:bg-sky-50/40 active:bg-sky-100/60"
                            onclick="initBayar({{ $bill->id }}, '{{ addslashes($bill->name ?? 'Tagihan') }}', {{ (int)$bill->amount }})"
                        >
                            {{-- Left: icon --}}
                            <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            {{-- Center: info --}}
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-gray-900 text-sm truncate">{{ $bill->name ?? 'Tagihan ' . $bill->bill_month . '/' . $bill->bill_year }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">
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
                                        'SPP'          => ['label' => 'SPP', 'class' => 'bg-blue-100 text-blue-600'],
                                        'PAKET'        => ['label' => 'Paket', 'class' => 'bg-purple-100 text-purple-600'],
                                        'KANTIN'       => ['label' => 'Kantin', 'class' => 'bg-orange-100 text-orange-600'],
                                        'DAFTAR_ULANG' => ['label' => 'Daftar Ulang', 'class' => 'bg-green-100 text-green-600'],
                                        default        => ['label' => $bill->type, 'class' => 'bg-gray-100 text-gray-500'],
                                    };
                                @endphp
                                <span class="inline-block mt-1 {{ $typeLabel['class'] }} text-xs px-1.5 py-0.5 rounded font-medium">
                                    {{ $typeLabel['label'] }}
                                </span>
                            </div>
                            {{-- Right: amount + badge --}}
                            <div class="text-right shrink-0">
                                @if($bill->original_amount && $bill->original_amount != $bill->amount)
                                    <p class="text-xs text-gray-400 line-through">Rp {{ number_format($bill->original_amount, 0, ',', '.') }}</p>
                                @endif
                                <p class="font-bold text-gray-900 text-sm">Rp {{ number_format($bill->amount, 0, ',', '.') }}</p>
                                <span class="inline-block mt-1 bg-red-100 text-red-600 text-xs px-2 py-0.5 rounded-full font-semibold">
                                    {{ $bill->status === 'PARTIAL' ? 'Sebagian' : 'Belum Bayar' }}
                                </span>
                            </div>
                            <svg class="w-4 h-4 text-sky-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                    @endforeach
                @empty
                    <div class="text-center py-16 px-4">
                        <p class="text-5xl mb-3">🎉</p>
                        <p class="text-gray-700 font-bold text-base">Semua tagihan lunas!</p>
                        <p class="text-gray-400 text-sm mt-1">Tidak ada tagihan yang perlu dibayar.</p>
                    </div>
                @endforelse
            </div>

            {{-- ===== LUNAS ===== --}}
            <div x-show="tab === 'paid'" class="divide-y divide-gray-50">
                @php $paidGrouped = $paidBills->groupBy('bill_year'); @endphp
                @forelse($paidGrouped as $year => $yearBills)
                    <div class="px-4 pt-3 pb-1">
                        <p class="text-xs text-gray-400 font-semibold uppercase tracking-widest">{{ $year }}</p>
                    </div>
                    @foreach($yearBills as $bill)
                        <div class="flex items-center px-4 py-4 gap-3">
                            {{-- Left: icon --}}
                            <div class="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            {{-- Center: info --}}
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-gray-900 text-sm truncate">{{ $bill->name ?? 'Tagihan ' . $bill->bill_month . '/' . $bill->bill_year }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    {{-- Phase 2.4: use paid_at as canonical payment date --}}
                        @if($bill->paid_at)
                            Dibayar {{ $bill->paid_at->isoFormat('D MMM Y') }}
                        @else
                            Tanggal pembayaran tidak tersedia
                        @endif
                                    @if($bill->payment_method)
                                        · <span class="capitalize">{{ $bill->payment_method }}</span>
                                    @endif
                                </p>
                                <a href="{{ route('siswa.tagihan.struk', $bill->id) }}" class="text-xs text-sky-500 font-medium mt-0.5 block">Lihat Struk →</a>
                            </div>
                            {{-- Right: amount + badge --}}
                            <div class="text-right shrink-0">
                                <p class="font-bold text-gray-900 text-sm">Rp {{ number_format($bill->amount, 0, ',', '.') }}</p>
                                <span class="inline-block mt-1 bg-green-100 text-green-600 text-xs px-2 py-0.5 rounded-full font-semibold">
                                    Lunas
                                </span>
                            </div>
                        </div>
                    @endforeach
                @empty
                    <div class="text-center py-16 px-4">
                        <p class="text-gray-400 text-sm">Belum ada riwayat pembayaran.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- ===== INFO FOOTER ===== --}}
        <p class="text-center text-gray-400 text-xs mt-6">
            {{ $schoolName }} &middot; Portal Siswa
        </p>

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
            class="relative w-full bg-white rounded-t-3xl shadow-2xl px-6 py-6 max-w-md mx-auto"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="translate-y-full"
            x-transition:enter-end="translate-y-0"
        >
            <div class="w-10 h-1 bg-gray-200 rounded-full mx-auto mb-5"></div>

            <h3 class="text-lg font-bold text-gray-900 mb-1">Konfirmasi Pembayaran</h3>
            <p class="text-gray-500 text-sm mb-5" x-text="billName"></p>

            <div class="bg-sky-50 rounded-2xl p-4 mb-5 flex items-center justify-between">
                <p class="text-sm text-sky-700 font-medium">Total Tagihan</p>
                <p class="text-xl font-extrabold text-sky-700" x-text="'Rp ' + amount.toLocaleString('id-ID')"></p>
            </div>

            <p class="text-xs text-gray-400 mb-5 text-center">
                Pembayaran diproses melalui Midtrans. Mendukung transfer bank, kartu kredit, QRIS, dan e-wallet.
            </p>

            <div class="space-y-3">
                <button
                    @click="loading = true; prosesPayment(billId, () => { loading = false; open = false; })"
                    :disabled="loading"
                    class="w-full bg-sky-600 hover:bg-sky-700 disabled:bg-sky-300 text-white font-bold py-3.5 rounded-xl transition-colors flex items-center justify-center gap-2"
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
                    class="w-full border border-gray-200 text-gray-500 font-medium py-3 rounded-xl hover:bg-gray-50 transition-colors text-sm"
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

        // Dipanggil saat tombol "Bayar Sekarang" diklik di bottom sheet
        async function prosesPayment(billId, onDone) {
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

                if (!res.ok) {
                    alert('Gagal: ' + (data.error || 'Terjadi kesalahan, coba lagi.'));
                    onDone();
                    return;
                }

                onDone(); // tutup sheet sebelum Snap terbuka

                snap.pay(data.snap_token, {
                    onSuccess: function(result) {
                        window.location.href = '/siswa/payment/success';
                    },
                    onPending: function(result) {
                        alert('Pembayaran pending. Selesaikan pembayaran sesuai instruksi yang diberikan.');
                        window.location.reload();
                    },
                    onError: function(result) {
                        alert('Pembayaran gagal: ' + (result.status_message || 'Terjadi kesalahan.'));
                        window.location.reload();
                    },
                    onClose: function() {
                        // Pengguna tutup popup tanpa bayar — tidak perlu action
                    }
                });

            } catch (e) {
                alert('Error jaringan: ' + e.message);
                onDone();
            }
        }
    </script>

    {{-- ===== FLOATING CHAT WIDGET ===== --}}
    <div id="chat-fab-wrapper" style="position:fixed; bottom:24px; right:24px; z-index:9999;">

        {{-- Bubble tombol chat --}}
        <div id="chat-fab"
             @click="chatOpen = !chatOpen"
             class="relative w-14 h-14 bg-sky-500 hover:bg-sky-600 rounded-full shadow-2xl flex items-center justify-center transition-colors select-none">
            {{-- Ikon chat --}}
            <svg x-show="!chatOpen" class="w-6 h-6 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/>
            </svg>
            {{-- Ikon close --}}
            <svg x-show="chatOpen" x-cloak class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            {{-- Badge unread --}}
            <span x-show="chatUnread > 0 && !chatOpen" x-cloak
                  x-text="chatUnread"
                  class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold w-5 h-5 rounded-full flex items-center justify-center shadow">
            </span>
        </div>

        {{-- Panel chat --}}
        <div x-show="chatOpen" x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 translate-y-4"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-4"
             class="absolute bottom-16 right-0 w-80 bg-white rounded-3xl shadow-2xl border border-gray-100 flex flex-col overflow-hidden"
             style="height:420px;">

            {{-- Header panel --}}
            <div class="bg-gradient-to-r from-sky-500 to-blue-600 px-4 py-3 flex items-center gap-2 shrink-0">
                <div class="w-8 h-8 rounded-xl bg-white/20 flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-white text-sm font-semibold leading-tight">Hubungi TU</p>
                    <p class="text-sky-200 text-xs">Tata Usaha siap membantu</p>
                </div>
            </div>

            {{-- Messages area --}}
            <div id="chat-messages" class="flex-1 overflow-y-auto px-3 py-3 space-y-2">
                {{-- Loading state --}}
                <template x-if="chatLoading">
                    <div class="flex justify-center py-4">
                        <div class="w-5 h-5 border-2 border-sky-500 border-t-transparent rounded-full animate-spin"></div>
                    </div>
                </template>

                {{-- Empty state --}}
                <template x-if="!chatLoading && chatMessages.length === 0">
                    <div class="flex flex-col items-center justify-center h-full py-8 text-center">
                        <svg class="w-10 h-10 text-gray-200 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/>
                        </svg>
                        <p class="text-xs text-gray-400">Belum ada pesan.</p>
                        <p class="text-xs text-gray-400">Mulai chat dengan TU di sini.</p>
                    </div>
                </template>

                {{-- Pesan --}}
                <template x-for="(msg, index) in chatMessages" :key="msg.id">
                    <div>
                        {{-- Pemisah tanggal --}}
                        <template x-if="index === 0 || chatMessages[index-1].date !== msg.date">
                            <div class="flex justify-center my-2">
                                <span class="bg-gray-100 text-gray-400 text-xs px-2 py-0.5 rounded-full" x-text="msg.date"></span>
                            </div>
                        </template>

                        {{-- Pesan dari siswa --}}
                        <template x-if="msg.sender === 'student'">
                            <div class="flex justify-end">
                                <div class="max-w-[80%]">
                                    <div class="chat-bubble-out px-3 py-2 text-xs" x-text="msg.message"></div>
                                    <p class="text-xs text-gray-400 mt-0.5 text-right" x-text="msg.created_at"></p>
                                </div>
                            </div>
                        </template>

                        {{-- Pesan dari TU --}}
                        <template x-if="msg.sender === 'tu'">
                            <div class="flex justify-start">
                                <div class="max-w-[80%]">
                                    <p class="text-xs text-gray-400 mb-0.5 ml-1">TU</p>
                                    <div class="chat-bubble-in px-3 py-2 text-xs text-gray-800" x-text="msg.message"></div>
                                    <p class="text-xs text-gray-400 mt-0.5 ml-1" x-text="msg.created_at"></p>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            {{-- Input --}}
            <div class="border-t border-gray-100 px-3 py-2 shrink-0">
                <form @submit.prevent="chatSend" class="flex items-end gap-2">
                    <textarea
                        x-model="chatText"
                        @keydown.enter.prevent="!$event.shiftKey && chatSend()"
                        placeholder="Ketik pesan..."
                        rows="1"
                        class="flex-1 resize-none text-xs rounded-xl border border-gray-200 px-3 py-2 focus:outline-none focus:border-sky-400 focus:ring-1 focus:ring-sky-400 transition-colors"
                        :disabled="chatSending"
                    ></textarea>
                    <button
                        type="submit"
                        :disabled="!chatText.trim() || chatSending"
                        class="w-8 h-8 bg-sky-500 hover:bg-sky-600 disabled:bg-gray-200 text-white rounded-xl flex items-center justify-center transition-colors shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.269 20.876L5.999 12zm0 0h7.5"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function chatWidget() {
            return {
                chatOpen: false,
                chatLoading: false,
                chatSending: false,
                chatMessages: [],
                chatText: '',
                chatUnread: 0,
                chatLastId: 0,
                _chatPollInterval: null,
                _chatUnreadInterval: null,
            };
        }

        function chatInit() {
            // Polling unread badge setiap 10 detik (saat chat tertutup)
            this._chatUnreadInterval = setInterval(async () => {
                if (this.chatOpen) return;
                try {
                    const r = await fetch('{{ route("siswa.chat.unread") }}');
                    const d = await r.json();
                    this.chatUnread = d.unread ?? 0;
                } catch (e) {}
            }, 10000);

            // Watch: saat chat dibuka, load pesan & mulai polling
            this.$watch('chatOpen', async (open) => {
                if (open) {
                    await this.chatLoad();
                    this.chatUnread = 0;
                    this._chatPollInterval = setInterval(() => this.chatPoll(), 4000);
                } else {
                    clearInterval(this._chatPollInterval);
                }
            });
        }

        async function chatLoad() {
            this.chatLoading = true;
            try {
                const r = await fetch('{{ route("siswa.chat.messages") }}');
                const d = await r.json();
                this.chatMessages = d.messages ?? [];
                if (this.chatMessages.length > 0) {
                    this.chatLastId = this.chatMessages[this.chatMessages.length - 1].id;
                }
                this.chatScrollBottom();
            } catch (e) {}
            this.chatLoading = false;
        }

        async function chatPoll() {
            try {
                const r = await fetch(`{{ route("siswa.chat.messages") }}`);
                const d = await r.json();
                const msgs = d.messages ?? [];
                const existingIds = new Set(this.chatMessages.map(m => m.id));
                const fresh = msgs.filter(m => !existingIds.has(m.id));
                if (fresh.length > 0) {
                    this.chatMessages.push(...fresh);
                    this.chatLastId = fresh[fresh.length - 1].id;
                    this.chatScrollBottom();
                }
            } catch (e) {}
        }

        async function chatSend() {
            const text = this.chatText.trim();
            if (!text || this.chatSending) return;
            this.chatSending = true;
            this.chatText = '';
            try {
                const r = await fetch('{{ route("siswa.chat.send") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ message: text }),
                });
                const d = await r.json();
                if (d.success) {
                    this.chatMessages.push(d.data);
                    this.chatLastId = d.data.id;
                    this.chatScrollBottom();
                }
            } catch (e) {
                alert('Gagal mengirim pesan.');
                this.chatText = text;
            } finally {
                this.chatSending = false;
            }
        }

        function chatScrollBottom() {
            this.$nextTick(() => {
                const el = document.getElementById('chat-messages');
                if (el) el.scrollTop = el.scrollHeight;
            });
        }
    </script>

</body>
</html>
