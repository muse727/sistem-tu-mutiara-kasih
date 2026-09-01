<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Detail Tagihan — {{ $bill->name ?? 'Tagihan' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://app.sandbox.midtrans.com/snap/snap.js"
            data-client-key="{{ config('services.midtrans.client_key') }}"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen pb-10">

    {{-- ===== HEADER ===== --}}
    <div class="bg-gradient-to-r from-sky-500 via-sky-600 to-blue-700 px-4 pt-10 pb-16">
        <div class="max-w-md mx-auto flex items-center gap-3">
            <a href="{{ route('siswa.dashboard') }}"
               class="flex items-center gap-1.5 bg-white/15 hover:bg-white/25 text-white text-xs font-medium px-3 py-2 rounded-xl transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
                Kembali
            </a>
            <div>
                <p class="text-white font-bold text-base leading-tight">Detail Tagihan</p>
                <p class="text-sky-200 text-xs mt-0.5">{{ $schoolName }}</p>
            </div>
        </div>
    </div>

    <div class="max-w-md mx-auto px-4 -mt-8">

        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="mb-4 bg-green-50 border border-green-200 text-green-700 rounded-2xl px-4 py-3 text-sm">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl px-4 py-3 text-sm">
                {{ session('error') }}
            </div>
        @endif

        {{-- ===== DETAIL CARD ===== --}}
        <div class="bg-white rounded-3xl shadow-xl shadow-gray-200/60 overflow-hidden">
            {{-- Top strip warna sesuai status --}}
            @if($bill->status === 'PAID')
                <div class="h-1.5 bg-gradient-to-r from-green-400 to-emerald-500"></div>
            @elseif($bill->status === 'PARTIAL')
                <div class="h-1.5 bg-gradient-to-r from-amber-400 to-orange-400"></div>
            @else
                <div class="h-1.5 bg-gradient-to-r from-sky-400 via-blue-500 to-sky-600"></div>
            @endif

            <div class="px-6 pt-6 pb-8">

                {{-- Status Badge --}}
                <div class="flex justify-center mb-5">
                    @if($bill->status === 'PAID')
                        <div class="flex items-center gap-2 bg-green-50 border-2 border-green-200 rounded-2xl px-5 py-2.5">
                            <div class="w-5 h-5 rounded-full bg-green-500 flex items-center justify-center">
                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <span class="text-green-700 font-bold text-sm">LUNAS</span>
                        </div>
                    @elseif($bill->status === 'PARTIAL')
                        <div class="flex items-center gap-2 bg-amber-50 border-2 border-amber-200 rounded-2xl px-5 py-2.5">
                            <div class="w-5 h-5 rounded-full bg-amber-500 flex items-center justify-center">
                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01"/>
                                </svg>
                            </div>
                            <span class="text-amber-700 font-bold text-sm">SEBAGIAN</span>
                        </div>
                    @else
                        <div class="flex items-center gap-2 bg-red-50 border-2 border-red-200 rounded-2xl px-5 py-2.5">
                            <div class="w-5 h-5 rounded-full bg-red-500 flex items-center justify-center">
                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </div>
                            <span class="text-red-700 font-bold text-sm">BELUM BAYAR</span>
                        </div>
                    @endif
                </div>

                {{-- Nama Tagihan --}}
                <div class="text-center mb-6">
                    <p class="text-gray-900 font-bold text-xl leading-tight">
                        {{ $bill->name ?? 'Tagihan ' . ($bill->bill_month ?? '') . '/' . ($bill->bill_year ?? '') }}
                    </p>
                    @php
                        $typeLabel = match($bill->type) {
                            'SPP'          => ['label' => 'SPP', 'class' => 'bg-sky-100 text-sky-700'],
                            'GEDUNG'       => ['label' => 'Gedung', 'class' => 'bg-violet-100 text-violet-700'],
                            'DAFTAR_ULANG' => ['label' => 'Daftar Ulang', 'class' => 'bg-amber-100 text-amber-700'],
                            'KOMITE'       => ['label' => 'Komite', 'class' => 'bg-emerald-100 text-emerald-700'],
                            default        => ['label' => $bill->type, 'class' => 'bg-gray-100 text-gray-500'],
                        };
                    @endphp
                    <span class="inline-block mt-2 text-xs font-semibold px-3 py-1 rounded-full {{ $typeLabel['class'] }}">
                        {{ $typeLabel['label'] }}
                    </span>
                </div>

                {{-- Divider --}}
                <div class="border-t border-dashed border-gray-200 mb-5"></div>

                {{-- Info Rows --}}
                <div class="space-y-3">
                    <div class="flex items-start justify-between gap-4">
                        <span class="text-xs text-gray-400 shrink-0 pt-0.5 w-32">Nama Siswa</span>
                        <span class="text-sm font-semibold text-gray-800 text-right">{{ $student->name }}</span>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <span class="text-xs text-gray-400 shrink-0 pt-0.5 w-32">NIS</span>
                        <span class="text-sm font-semibold text-gray-800 text-right">{{ $student->nis }}</span>
                    </div>
                    @if($bill->bill_month && $bill->bill_year)
                    <div class="flex items-start justify-between gap-4">
                        <span class="text-xs text-gray-400 shrink-0 pt-0.5 w-32">Periode</span>
                        <span class="text-sm font-semibold text-gray-800 text-right">
                            {{ \Carbon\Carbon::createFromDate($bill->bill_year, $bill->bill_month, 1)->isoFormat('MMMM Y') }}
                        </span>
                    </div>
                    @endif
                    @if($bill->due_date)
                    <div class="flex items-start justify-between gap-4">
                        <span class="text-xs text-gray-400 shrink-0 pt-0.5 w-32">Jatuh Tempo</span>
                        <span class="text-sm font-semibold text-gray-800 text-right">
                            {{ \Carbon\Carbon::parse($bill->due_date)->isoFormat('D MMMM Y') }}
                        </span>
                    </div>
                    @endif
                    @if($bill->discount_amount > 0)
                    <div class="flex items-start justify-between gap-4">
                        <span class="text-xs text-gray-400 shrink-0 pt-0.5 w-32">Harga Normal</span>
                        <span class="text-sm text-gray-400 line-through text-right">
                            Rp {{ number_format($bill->original_amount, 0, ',', '.') }}
                        </span>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <span class="text-xs text-gray-400 shrink-0 pt-0.5 w-32">Diskon</span>
                        <span class="text-sm font-semibold text-green-600 text-right">
                            - Rp {{ number_format($bill->discount_amount, 0, ',', '.') }}
                            @if($bill->discount_note)
                                <br><span class="text-xs text-gray-400 font-normal">{{ $bill->discount_note }}</span>
                            @endif
                        </span>
                    </div>
                    @endif
                </div>

                {{-- Total Amount --}}
                <div class="mt-5 bg-gray-50 rounded-2xl px-5 py-4 flex items-center justify-between">
                    <span class="text-sm text-gray-500 font-medium">Total Tagihan</span>
                    <span class="text-xl font-bold text-gray-900">
                        Rp {{ number_format($bill->amount, 0, ',', '.') }}
                    </span>
                </div>

                @if($bill->status === 'PAID' && $bill->paid_at)
                <div class="mt-3 bg-green-50 rounded-2xl px-5 py-3 flex items-center justify-between">
                    <span class="text-xs text-green-600 font-medium">Dibayar</span>
                    <span class="text-sm font-semibold text-green-700">
                        {{ $bill->paid_at->isoFormat('D MMM Y, HH:mm') }}
                        @if($bill->payment_method)
                            · <span class="capitalize">{{ strtolower($bill->payment_method) }}</span>
                        @endif
                    </span>
                </div>
                @endif

                {{-- Divider --}}
                <div class="border-t border-dashed border-gray-200 my-5"></div>

                {{-- Action Buttons --}}
                @if($bill->status === 'PAID')
                    <a href="{{ route('siswa.tagihan.struk', $bill->id) }}"
                       class="block w-full text-center bg-green-500 hover:bg-green-600 text-white font-bold py-3.5 rounded-2xl text-sm transition-colors">
                        Lihat Struk Pembayaran
                    </a>
                @else
                    {{-- Tombol Bayar --}}
                    <button id="btn-bayar"
                            onclick="bayarTagihan()"
                            class="block w-full text-center bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-600 hover:to-blue-700 text-white font-bold py-3.5 rounded-2xl text-sm transition-all shadow-lg shadow-sky-200">
                        Bayar Sekarang · Rp {{ number_format($bill->amount, 0, ',', '.') }}
                    </button>
                    <p class="text-center text-xs text-gray-400 mt-2">Pembayaran aman via Midtrans</p>
                @endif

            </div>
        </div>

        {{-- Back link --}}
        <div class="text-center mt-6">
            <a href="{{ route('siswa.dashboard') }}" class="text-sm text-gray-400 hover:text-sky-500 transition-colors">
                ← Kembali ke Dashboard
            </a>
        </div>

    </div>

    <script>
        const billId   = {{ $bill->id }};
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

        async function bayarTagihan() {
            const btn = document.getElementById('btn-bayar');
            btn.disabled = true;
            btn.textContent = 'Memproses...';

            try {
                const res = await fetch(`/siswa/tagihan/${billId}/pay`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                });

                if (res.status === 401 || res.redirected) {
                    window.location.href = '{{ route("siswa.login") }}';
                    return;
                }

                const data = await res.json();

                if (data.error) {
                    alert(data.error);
                    btn.disabled = false;
                    btn.textContent = 'Bayar Sekarang · Rp {{ number_format($bill->amount, 0, ",", ".") }}';
                    return;
                }

                window.snap.pay(data.snap_token, {
                    onSuccess: function(result) {
                        window.location.href = '{{ route("siswa.payment.success") }}?order_id=' + result.order_id;
                    },
                    onPending: function(result) {
                        window.location.reload();
                    },
                    onError: function(result) {
                        alert('Pembayaran gagal. Silakan coba lagi.');
                        btn.disabled = false;
                        btn.textContent = 'Bayar Sekarang · Rp {{ number_format($bill->amount, 0, ",", ".") }}';
                    },
                    onClose: function() {
                        btn.disabled = false;
                        btn.textContent = 'Bayar Sekarang · Rp {{ number_format($bill->amount, 0, ",", ".") }}';
                    }
                });

            } catch (e) {
                alert('Gagal menghubungi server. Coba lagi.');
                btn.disabled = false;
                btn.textContent = 'Bayar Sekarang · Rp {{ number_format($bill->amount, 0, ",", ".") }}';
            }
        }
    </script>

</body>
</html>
