<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Verifikasi Pembayaran — {{ $schoolName }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen pb-10">

    {{-- Header --}}
    <div class="bg-gradient-to-r from-sky-500 via-sky-600 to-blue-700 px-4 pt-10 pb-16">
        <div class="max-w-md mx-auto">
            <p class="text-white font-bold text-base leading-tight">Verifikasi Pembayaran</p>
            <p class="text-sky-200 text-xs mt-0.5">{{ $schoolName }}</p>
        </div>
    </div>

    <div class="max-w-md mx-auto px-4 -mt-8">
        <div class="bg-white rounded-3xl shadow-xl shadow-gray-200/60 overflow-hidden">
            <div class="h-1.5 bg-gradient-to-r from-sky-400 via-blue-500 to-sky-600"></div>
            <div class="px-6 py-8">

                @if(! $valid)
                    {{-- Tidak valid --}}
                    <div class="flex flex-col items-center text-center py-4">
                        <div class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center mb-4">
                            <svg class="w-8 h-8 text-[#F56C3E]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </div>
                        <h2 class="text-lg font-bold text-gray-900 mb-2">Struk Tidak Valid</h2>
                        <p class="text-sm text-gray-500 leading-relaxed">Kode verifikasi tidak ditemukan atau telah kedaluwarsa. Hubungi pihak sekolah untuk konfirmasi pembayaran.</p>
                    </div>

                @else
                    {{-- Valid --}}
                    <div class="flex flex-col items-center text-center mb-6">
                        <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mb-3">
                            <svg class="w-8 h-8 text-[#F56C3E]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <span class="inline-flex items-center gap-1.5 bg-[#F56C3E] text-white shadow-sm text-xs font-bold px-3 py-1 rounded-full mb-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                            PEMBAYARAN TERVERIFIKASI
                        </span>
                        <h2 class="text-base font-bold text-gray-900">Bukti pembayaran ini sah dan valid</h2>
                    </div>

                    {{-- Detail tagihan --}}
                    <div class="bg-gray-50 rounded-2xl p-4 space-y-3 mb-4">

                        <div class="flex items-start justify-between gap-4">
                            <span class="text-xs text-gray-400 shrink-0 pt-0.5 w-28">Nama Siswa</span>
                            <span class="text-xs font-semibold text-gray-700 text-right">{{ $bill->student->name ?? '-' }}</span>
                        </div>

                        <div class="flex items-start justify-between gap-4">
                            <span class="text-xs text-gray-400 shrink-0 pt-0.5 w-28">NIS</span>
                            <span class="text-xs font-semibold text-gray-700 text-right">{{ $bill->student->nis ?? '-' }}</span>
                        </div>

                        <div class="flex items-start justify-between gap-4">
                            <span class="text-xs text-gray-400 shrink-0 pt-0.5 w-28">Tagihan</span>
                            <span class="text-xs font-semibold text-gray-700 text-right">{{ $bill->name ?? ('Tagihan ' . ($bill->bill_month ?? '') . '/' . ($bill->bill_year ?? '')) }}</span>
                        </div>

                        <div class="flex items-start justify-between gap-4">
                            <span class="text-xs text-gray-400 shrink-0 pt-0.5 w-28">Jumlah</span>
                            <span class="text-xs font-bold text-green-600 text-right">Rp {{ number_format($bill->amount, 0, ',', '.') }}</span>
                        </div>

                        @if($bill->paid_at)
                        <div class="flex items-start justify-between gap-4">
                            <span class="text-xs text-gray-400 shrink-0 pt-0.5 w-28">Tanggal Bayar</span>
                            <span class="text-xs font-semibold text-gray-700 text-right">
                                {{ \Carbon\Carbon::parse($bill->paid_at)->locale('id')->isoFormat('D MMMM YYYY, HH:mm') }}
                            </span>
                        </div>
                        @endif

                        @if($bill->payment_method)
                        <div class="flex items-start justify-between gap-4">
                            <span class="text-xs text-gray-400 shrink-0 pt-0.5 w-28">Metode Bayar</span>
                            <span class="text-xs font-semibold text-gray-700 text-right">{{ $bill->payment_method === 'CASH' ? 'Tunai' : $bill->payment_method }}</span>
                        </div>
                        @endif

                        @if($bill->midtrans_order_id)
                        <div class="flex items-start justify-between gap-4">
                            <span class="text-xs text-gray-400 shrink-0 pt-0.5 w-28">No. Transaksi</span>
                            <span class="text-xs font-mono font-semibold text-gray-600 text-right break-all">{{ $bill->midtrans_order_id }}</span>
                        </div>
                        @endif

                    </div>

                    @if($bill->items->isNotEmpty())
                    <div class="mb-4">
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
                    </div>
                    @endif

                    <p class="text-center text-xs text-gray-400 leading-relaxed pt-4 border-t border-dashed border-gray-200">
                        {{ $schoolName }}<br>
                        @if($schoolAddress){{ $schoolAddress }}<br>@endif
                        Dokumen ini diterbitkan secara digital dan dapat diverifikasi melalui QR code.
                    </p>

                @endif

            </div>
        </div>
    </div>

</body>
</html>
