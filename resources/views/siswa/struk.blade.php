<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Struk Pembayaran — {{ $bill->name ?? 'Tagihan' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }

        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .print-card { box-shadow: none !important; border: 1px solid #EDEDED !important; }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen pb-10">

    {{-- ===== HEADER ===== --}}
    <div class="bg-gradient-to-r from-[#F56C3E] to-[#e45322] px-4 pt-10 pb-16 no-print">
        <div class="max-w-md mx-auto flex items-center gap-3">
            <a href="{{ route('siswa.dashboard') }}"
               class="flex items-center gap-1.5 bg-white/15 hover:bg-white/25 text-white text-xs font-medium px-3 py-2 rounded-xl transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
                Kembali
            </a>
            <div>
                <p class="text-white font-bold text-base leading-tight">Bukti Pembayaran</p>
                <p class="text-white/80 text-xs mt-0.5">{{ $schoolName }}</p>
            </div>
        </div>
    </div>

    {{-- Print-only header (hidden on screen) --}}
    <div class="hidden print:block text-center pt-6 pb-2">
        <p class="text-lg font-bold text-gray-900">{{ $schoolName }}</p>
        @if($schoolAddress)
            <p class="text-xs text-gray-500 mt-0.5">{{ $schoolAddress }}</p>
        @endif
        <p class="text-sm font-semibold text-gray-700 mt-1">BUKTI PEMBAYARAN</p>
    </div>

    <div class="max-w-md mx-auto px-4 -mt-8 no-print-offset">

        {{-- ===== STRUK CARD ===== --}}
        <div class="print-card bg-white rounded-3xl shadow-xl shadow-gray-200/60 overflow-hidden">

            {{-- Top decorative strip --}}
            <div class="h-1.5 bg-gradient-to-r from-[#F56C3E] to-[#e45322]"></div>

            <div class="px-6 pt-6 pb-8">

                {{-- LUNAS Badge --}}
                <div class="flex justify-center mb-5">
                    <div class="flex items-center gap-2 bg-green-50 border-2 border-green-200 rounded-2xl px-5 py-2.5">
                        <div class="w-6 h-6 rounded-full bg-green-500 flex items-center justify-center">
                            <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <span class="text-green-700 font-bold text-base tracking-wide">LUNAS</span>
                    </div>
                </div>

                {{-- Bill Name --}}
                <p class="text-center text-gray-900 font-bold text-xl leading-snug mb-1">
                    {{ $bill->name ?? ('Tagihan ' . $bill->bill_month . '/' . $bill->bill_year) }}
                </p>

                {{-- Amount --}}
                @if($bill->original_amount && $bill->original_amount != $bill->amount)
                <div class="mb-3 text-center space-y-0.5">
                    <p class="text-gray-400 text-sm line-through">Rp {{ number_format($bill->original_amount, 0, ',', '.') }}</p>
                    <p class="text-[#F56C3E] text-xs">Diskon Rp {{ number_format($bill->discount_amount, 0, ',', '.') }}{{ $bill->discount_note ? ' — ' . $bill->discount_note : '' }}</p>
                </div>
                @endif
                <p class="text-center text-green-600 font-extrabold text-3xl mb-6">
                    Rp {{ number_format($bill->amount, 0, ',', '.') }}
                </p>

                {{-- Divider with scissors icon --}}
                <div class="flex items-center gap-3 mb-6">
                    <div class="flex-1 border-t-2 border-dashed border-gray-200"></div>
                    <svg class="w-4 h-4 text-gray-300 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M5.5 13a3.5 3.5 0 01-.369-6.98 1 1 0 00.232-1.952 3.5 3.5 0 00-2.702 5.706L.22 12.634a.75.75 0 001.06 1.06l2.5-2.5a3.5 3.5 0 002.72-3.694zM5.5 11a2 2 0 100-4 2 2 0 000 4zM14.5 7a3.5 3.5 0 01.369 6.98 1 1 0 00-.232 1.952 3.5 3.5 0 002.702-5.706l2.441-2.88a.75.75 0 00-1.06-1.06l-2.5 2.5A3.5 3.5 0 0114.5 7zm0 4a2 2 0 100-4 2 2 0 000 4zM10 12a.75.75 0 01.75.75v2.5a.75.75 0 01-1.5 0v-2.5A.75.75 0 0110 12zM10 9.25a.75.75 0 01-.75-.75v-2.5a.75.75 0 011.5 0v2.5a.75.75 0 01-.75.75z"/>
                    </svg>
                    <div class="flex-1 border-t-2 border-dashed border-gray-200"></div>
                </div>

                {{-- Info Rows --}}
                <div class="space-y-3">

                    {{-- Nama Siswa --}}
                    <div class="flex items-start justify-between gap-4">
                        <span class="text-xs text-gray-400 shrink-0 pt-0.5 w-28">Nama Siswa</span>
                        <span class="text-sm font-semibold text-gray-800 text-right">{{ $student->name }}</span>
                    </div>

                    {{-- NIS --}}
                    <div class="flex items-start justify-between gap-4">
                        <span class="text-xs text-gray-400 shrink-0 pt-0.5 w-28">NIS</span>
                        <span class="text-sm font-semibold text-gray-800 text-right">{{ $student->nis }}</span>
                    </div>

                    {{-- Kelas --}}
                    @if($student->kelas_id)
                    <div class="flex items-start justify-between gap-4">
                        <span class="text-xs text-gray-400 shrink-0 pt-0.5 w-28">Kelas</span>
                        <span class="text-sm font-semibold text-gray-800 text-right">{{ optional($student->kelas)->nama_kelas ?? '-' }}</span>
                    </div>
                    @endif

                    {{-- Divider --}}
                    <div class="border-t border-gray-100 my-1"></div>

                    {{-- Metode Bayar --}}
                    <div class="flex items-start justify-between gap-4">
                        <span class="text-xs text-gray-400 shrink-0 pt-0.5 w-28">Metode Bayar</span>
                        <span class="text-sm font-semibold text-gray-800 text-right">
                            @php
                                $pm = $bill->payment_method ?? 'CASH';
                                $pmLabel = match(strtoupper($pm)) {
                                    'CASH'    => 'Tunai',
                                    'MIDTRANS'=> 'Midtrans',
                                    default   => $pm,
                                };
                            @endphp
                            {{ $pmLabel }}
                        </span>
                    </div>

                    {{-- Tanggal Bayar --}}
                    <div class="flex items-start justify-between gap-4">
                        <span class="text-xs text-gray-400 shrink-0 pt-0.5 w-28">Tanggal Bayar</span>
                        <span class="text-sm font-semibold text-gray-800 text-right">
                            {{-- Phase 2.4: paid_at is the canonical payment timestamp. --}}
                @if($bill->paid_at)
                    {{ \Carbon\Carbon::parse($bill->paid_at)->locale('id')->isoFormat('D MMMM YYYY, HH:mm') }}
                @else
                    <span class="italic text-gray-400">Tanggal pembayaran tidak tersedia</span>
                @endif
                        </span>
                    </div>

                    {{-- No. Transaksi --}}
                    <div class="flex items-start justify-between gap-4">
                        <span class="text-xs text-gray-400 shrink-0 pt-0.5 w-28">No. Transaksi</span>
                        <span class="text-xs font-mono font-semibold text-gray-600 text-right break-all">
                            @php
                                $pm = strtoupper($bill->payment_method ?? 'CASH');
                                if ($bill->midtrans_order_id) {
                                    $txId = $bill->midtrans_order_id;
                                } elseif ($pm === 'CASH' || $pm === 'TUNAI') {
                                    $txId = 'CASH-' . $bill->id;
                                } else {
                                    $txId = 'TRX-' . $bill->id;
                                }
                            @endphp
                            {{ $txId }}
                        </span>
                    </div>

                </div>

                {{-- Rincian Item (jika ada) --}}
                @if($bill->items->isNotEmpty())
                <div class="border-t-2 border-dashed border-gray-200 mt-6 mb-4"></div>

                <div class="mb-2">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Rincian Tagihan</p>
                    <div class="space-y-2">
                        @foreach($bill->items as $item)
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-gray-700">{{ $item->item_name }}</p>
                                @if($item->quantity > 1)
                                <p class="text-xs text-gray-400">{{ $item->quantity }}x × Rp {{ number_format($item->price, 0, ',', '.') }}</p>
                                @endif
                            </div>
                            <p class="text-xs font-semibold text-gray-700 shrink-0">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</p>
                        </div>
                        @endforeach
                    </div>
                    {{-- Total row --}}
                    <div class="flex items-center justify-between mt-3 pt-2 border-t border-gray-200">
                        <p class="text-xs font-bold text-gray-700">Total</p>
                        <p class="text-xs font-bold text-green-600">Rp {{ number_format($bill->amount, 0, ',', '.') }}</p>
                    </div>
                </div>
                @endif

                {{-- Bottom divider --}}
                <div class="border-t-2 border-dashed border-gray-200 mt-6 mb-4"></div>

                {{-- QR Code Verifikasi --}}
                @if($bill->verify_token)
                @php $verifyUrl = url('/siswa/verify/' . $bill->verify_token); @endphp
                <div class="flex flex-col items-center mb-4">
                    <p class="text-xs text-gray-400 mb-2">Scan untuk verifikasi keaslian struk</p>
                    <img
                        src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data={{ urlencode($verifyUrl) }}&bgcolor=ffffff&color=F56C3E&margin=4"
                        alt="QR Verifikasi"
                        class="w-28 h-28 rounded-xl border border-gray-100"
                        loading="lazy"
                    />
                    <p class="text-[10px] text-gray-300 mt-1.5 font-mono break-all text-center px-4">{{ $bill->verify_token }}</p>
                </div>
                @endif

                {{-- Footer text --}}
                <p class="text-center text-xs text-gray-400 leading-relaxed">
                    {{ $schoolName }}<br>
                    Terima kasih atas pembayaran Anda.
                </p>

            </div>
        </div>

        {{-- ===== ACTION BUTTONS ===== --}}
        <div class="mt-5 flex flex-col gap-3 no-print">

            {{-- Share WhatsApp --}}
            @if($bill->verify_token)
            @php
                $verifyUrl = url('/siswa/verify/' . $bill->verify_token);
                $pm = strtoupper($bill->payment_method ?? 'CASH');
                if ($bill->midtrans_order_id) {
                    $txId = $bill->midtrans_order_id;
                } elseif ($pm === 'CASH' || $pm === 'TUNAI') {
                    $txId = 'CASH-' . $bill->id;
                } else {
                    $txId = 'TRX-' . $bill->id;
                }
                $waText = "✅ *Bukti Pembayaran*\n" .
                    "━━━━━━━━━━━━━━━━━━\n" .
                    "📋 *" . ($bill->name ?? 'Tagihan') . "*\n" .
                    "👤 " . ($bill->student->name ?? '-') . "\n" .
                    "💰 Rp " . number_format($bill->amount, 0, ',', '.') . "\n" .
                    "🏦 " . ($bill->payment_method === 'CASH' ? 'Tunai' : ($bill->payment_method ?? 'Tunai')) . "\n" .
                    "🔖 " . $txId . "\n" .
                    "━━━━━━━━━━━━━━━━━━\n" .
                    "🔗 Verifikasi: " . $verifyUrl;
            @endphp
            <a href="https://wa.me/?text={{ urlencode($waText) }}"
               target="_blank"
               class="w-full flex items-center justify-center gap-2 bg-green-500 hover:bg-green-600 active:bg-green-700 text-white font-semibold text-sm py-3.5 rounded-2xl transition-colors shadow-md shadow-green-200">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                </svg>
                Bagikan via WhatsApp
            </a>
            @endif

            {{-- Cetak / Simpan PDF --}}
            <button onclick="window.print()"
                    class="w-full flex items-center justify-center gap-2 bg-[#F56C3E] hover:bg-[#e45322] active:bg-[#be3e15] text-white font-semibold text-sm py-3.5 rounded-2xl transition-colors shadow-md shadow-[#F56C3E]/30">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"/>
                </svg>
                Cetak / Simpan PDF
            </button>

            {{-- Kembali ke Dashboard --}}
            <a href="{{ route('siswa.dashboard') }}"
               class="w-full flex items-center justify-center gap-2 bg-white hover:bg-gray-50 active:bg-white text-[#171717] border border-[#EDEDED] shadow-sm font-semibold text-sm py-3.5 rounded-2xl transition-colors border border-gray-200">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
                Kembali ke Dashboard
            </a>

        </div>

    </div>

</body>
</html>
