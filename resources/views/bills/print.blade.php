<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kwitansi #{{ str_pad($bill->id, 5, '0', STR_PAD_LEFT) }} — {{ $bill->student->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            @page { margin: 15mm; size: A4; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col items-center py-10 print:bg-white print:py-0">

    {{-- Tombol Aksi (hilang saat print) --}}
    <div class="no-print mb-6 flex gap-3">
        <button onclick="window.print()"
                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-bold shadow-lg transition flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            Cetak / Simpan PDF
        </button>
        <a href="{{ route('bills.index') }}"
           class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg font-bold shadow-lg transition">
            Kembali
        </a>
    </div>

    {{-- Kertas Kwitansi --}}
    <div class="bg-white w-full max-w-[210mm] p-10 md:p-12 shadow-2xl print:shadow-none print:w-full print:max-w-none rounded-xl relative overflow-hidden">

        {{-- Watermark LUNAS --}}
        @if($bill->status === 'PAID')
        <div class="absolute top-10 right-10 opacity-10 pointer-events-none select-none">
            <span class="text-9xl font-black text-green-600 border-8 border-green-600 rounded-xl px-4 transform -rotate-12 inline-block">
                LUNAS
            </span>
        </div>
        @endif

        {{-- ===== HEADER SEKOLAH ===== --}}
        <div class="flex justify-between items-start border-b-2 border-gray-800 pb-6 mb-8">
            <div class="flex items-center gap-4">
                @if(!empty($school['school_logo']))
                    <img src="{{ asset('storage/' . $school['school_logo']) }}"
                         class="w-16 h-16 object-contain rounded-lg" alt="Logo Sekolah">
                @else
                    <div class="w-16 h-16 bg-blue-900 text-white flex items-center justify-center rounded-full font-bold text-2xl shrink-0">
                        TU
                    </div>
                @endif
                <div>
                    <h1 class="text-2xl font-black text-gray-900 uppercase tracking-wide">
                        {{ $school['school_name'] ?? 'Sekolah' }}
                    </h1>
                    @if(!empty($school['school_address']))
                        <p class="text-sm text-gray-600">{{ $school['school_address'] }}</p>
                    @endif
                    @if(!empty($school['school_phone']))
                        <p class="text-sm text-gray-600">Telp: {{ $school['school_phone'] }}</p>
                    @endif
                </div>
            </div>
            <div class="text-right">
                <h2 class="text-4xl font-black text-gray-200 uppercase tracking-widest">KWITANSI</h2>
                <p class="text-gray-500 mt-1 font-mono">#KWT-{{ str_pad($bill->id, 5, '0', STR_PAD_LEFT) }}</p>
                @php
                    $typeLabels = [
                        'SPP'          => 'SPP Bulanan',
                        'PAKET'        => 'Paket Bundling',
                        'KANTIN'       => 'Kantin/Koperasi',
                        'DAFTAR_ULANG' => 'Daftar Ulang',
                        'LAINNYA'      => 'Lainnya',
                    ];
                @endphp
                <span class="inline-block mt-2 bg-blue-50 text-blue-700 border border-blue-200 text-xs font-bold px-3 py-1 rounded-full uppercase">
                    {{ $typeLabels[$bill->type] ?? $bill->type }}
                </span>
            </div>
        </div>

        {{-- ===== INFO SISWA & PEMBAYARAN ===== --}}
        <div class="flex justify-between mb-10">
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Diterima dari:</p>
                <h3 class="text-xl font-bold text-gray-800">{{ $bill->student->name }}</h3>
                <p class="text-gray-600">NIS: <span class="font-mono font-bold">{{ $bill->student->nis }}</span></p>
                <p class="text-gray-600">Kelas: {{ optional($bill->student->kelas)->nama_kelas ?? '-' }}</p>
            </div>
            <div class="text-right">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Tanggal Bayar:</p>
                <p class="text-lg font-bold text-gray-800">
                    {{ $bill->paid_at ? $bill->paid_at->translatedFormat('d F Y, H:i') : '-' }}
                </p>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mt-4 mb-1">Metode Pembayaran:</p>
                <span class="bg-gray-100 text-gray-800 px-3 py-1 rounded-md text-sm font-bold border border-gray-200">
                    {{ strtoupper($bill->payment_method ?? 'CASH') }}
                </span>
                @if($bill->midtrans_order_id)
                <p class="text-xs text-gray-400 font-mono mt-1">{{ $bill->midtrans_order_id }}</p>
                @endif
            </div>
        </div>

        {{-- ===== TABEL ITEM ===== --}}
        <table class="w-full mb-8">
            <thead>
                <tr class="bg-gray-50 border-y border-gray-200">
                    <th class="py-3 px-4 text-left font-bold text-gray-600 uppercase text-xs tracking-wider w-8">#</th>
                    <th class="py-3 px-4 text-left font-bold text-gray-600 uppercase text-xs tracking-wider">Deskripsi</th>
                    <th class="py-3 px-4 text-center font-bold text-gray-600 uppercase text-xs tracking-wider w-20">Qty</th>
                    <th class="py-3 px-4 text-right font-bold text-gray-600 uppercase text-xs tracking-wider w-40">Jumlah</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @if($bill->items->count() > 0)
                    {{-- Tagihan dengan rincian item (PAKET, KANTIN, LAINNYA) --}}
                    @foreach($bill->items as $i => $item)
                    <tr>
                        <td class="py-4 px-4 text-gray-400 text-sm">{{ $i + 1 }}</td>
                        <td class="py-4 px-4 text-gray-800 font-medium">{{ $item->item_name }}</td>
                        <td class="py-4 px-4 text-center text-gray-600">{{ $item->quantity }}</td>
                        <td class="py-4 px-4 text-right font-bold text-gray-800">
                            Rp {{ number_format($item->subtotal, 0, ',', '.') }}
                        </td>
                    </tr>
                    @endforeach
                @else
                    {{-- SPP dan tagihan tanpa item detail --}}
                    <tr>
                        <td class="py-4 px-4 text-gray-400 text-sm">1</td>
                        <td class="py-4 px-4 text-gray-800 font-medium">
                            {{ $bill->name }}
                            @if($bill->bill_month && $bill->bill_year)
                                <span class="text-gray-500 text-sm ml-1">
                                    ({{ \Carbon\Carbon::createFromDate($bill->bill_year, $bill->bill_month, 1)->translatedFormat('F Y') }})
                                </span>
                            @endif
                        </td>
                        <td class="py-4 px-4 text-center text-gray-600">1</td>
                        <td class="py-4 px-4 text-right font-bold text-gray-800">
                            Rp {{ number_format($bill->amount, 0, ',', '.') }}
                        </td>
                    </tr>
                @endif

                {{-- Diskon jika ada --}}
                @if($bill->discount_amount && $bill->discount_amount > 0)
                <tr>
                    <td colspan="3" class="py-2 px-4 text-right text-sm text-gray-500">
                        Diskon
                        @if($bill->discount_note) <span class="italic">({{ $bill->discount_note }})</span> @endif
                    </td>
                    <td class="py-2 px-4 text-right text-sm font-bold text-green-600">
                        - Rp {{ number_format($bill->discount_amount, 0, ',', '.') }}
                    </td>
                </tr>
                @endif
            </tbody>
            <tfoot>
                <tr class="border-t-2 border-gray-800">
                    <td colspan="3" class="pt-4 text-right font-black text-gray-900 uppercase tracking-wide text-lg">
                        Total Bayar
                    </td>
                    <td class="pt-4 px-4 text-right font-black text-blue-600 text-2xl">
                        Rp {{ number_format($bill->amount, 0, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
        </table>

        {{-- ===== FOOTER TANDA TANGAN ===== --}}
        <div class="mt-16 flex justify-between items-end">
            <div class="text-sm text-gray-400 italic">
                * Kwitansi ini diterbitkan secara otomatis oleh sistem.<br>
                Simpan sebagai bukti pembayaran yang sah.
            </div>
            <div class="text-center">
                <p class="text-xs font-bold text-gray-400 uppercase mb-2">Petugas Tata Usaha</p>
                @if(!empty($school['school_signature']))
                    <img src="{{ asset('storage/' . $school['school_signature']) }}"
                         alt="Tanda Tangan"
                         class="h-16 mx-auto object-contain">
                @else
                    <div class="h-16"></div>
                @endif
                <div class="border-b border-gray-300 w-48 mx-auto"></div>
                <p class="font-bold text-gray-800 mt-2">
                    {{ $school['school_treasurer'] ?? optional($bill->confirmedBy)->name ?? 'Administrator TU' }}
                </p>
            </div>
        </div>

    </div>

</body>
</html>
