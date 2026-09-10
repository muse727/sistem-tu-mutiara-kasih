<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan Bulanan — {{ \Carbon\Carbon::createFromDate($tahun, $bulan, 1)->translatedFormat('F Y') }}</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* ── Base ────────────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            color: #171717;
            background: #F5F5F5;
        }

        /* ── Print wrapper ───────────────────────────────────────── */
        .page {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            padding: 20mm 18mm 20mm 18mm;
            min-height: 297mm;
        }

        /* ── Header ──────────────────────────────────────────────── */
        .report-header {
            display: flex;
            align-items: center;
            gap: 16px;
            padding-bottom: 16px;
            border-bottom: 3px solid #F56C3E;
            margin-bottom: 20px;
        }
        .report-header img {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }
        .report-header .school-info h1 {
            font-size: 16px;
            font-weight: 700;
            color: #171717;
            letter-spacing: -0.3px;
        }
        .report-header .school-info p {
            font-size: 11px;
            color: #777777;
            margin-top: 2px;
        }
        .report-title {
            text-align: center;
            margin-bottom: 20px;
        }
        .report-title h2 {
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #171717;
        }
        .report-title p {
            font-size: 12px;
            color: #333333;
            margin-top: 4px;
        }

        /* ── Summary Boxes ───────────────────────────────────────── */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin-bottom: 24px;
        }
        .summary-box {
            border: 1px solid #EDEDED;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
        }
        .summary-box .label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #777777;
            font-weight: 600;
        }
        .summary-box .value {
            font-size: 13px;
            font-weight: 700;
            color: #171717;
            margin-top: 4px;
        }
        .summary-box.total {
            background: #F56C3E;
            border-color: #F56C3E;
        }
        .summary-box.total .label,
        .summary-box.total .value {
            color: white;
        }

        /* ── Section titles ──────────────────────────────────────── */
        .section-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #F56C3E;
            border-left: 3px solid #F56C3E;
            padding-left: 8px;
            margin-bottom: 10px;
            margin-top: 20px;
        }

        /* ── Tables ──────────────────────────────────────────────── */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-bottom: 8px;
        }
        thead tr {
            background: #F5F5F5;
        }
        thead th {
            padding: 7px 8px;
            text-align: left;
            font-weight: 600;
            color: #333333;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border-bottom: 1px solid #EDEDED;
        }
        tbody tr {
            border-bottom: 1px solid #F5F5F5;
        }
        tbody tr:last-child {
            border-bottom: 1px solid #EDEDED;
        }
        tbody td {
            padding: 6px 8px;
            color: #333333;
        }
        tbody tr:nth-child(even) td {
            background: #fafafa;
        }
        tfoot tr td {
            padding: 7px 8px;
            font-weight: 700;
            color: #171717;
            border-top: 2px solid #EDEDED;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* ── Rekap per kelas ─────────────────────────────────────── */
        .rekap-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 20px;
        }
        .rekap-item {
            border: 1px solid #EDEDED;
            border-radius: 6px;
            padding: 8px 10px;
        }
        .rekap-item .kelas-name {
            font-size: 11px;
            font-weight: 600;
            color: #333333;
        }
        .rekap-item .kelas-total {
            font-size: 12px;
            font-weight: 700;
            color: #F56C3E;
            margin-top: 2px;
        }
        .rekap-item .kelas-count {
            font-size: 10px;
            color: #777777;
        }

        /* ── Signature Section ───────────────────────────────────── */
        .signature-section {
            margin-top: 40px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }
        .signature-box {
            text-align: center;
        }
        .signature-box .title {
            font-size: 11px;
            color: #333333;
            margin-bottom: 60px;
        }
        .signature-box .name-line {
            border-bottom: 1px solid #333333;
            margin-bottom: 4px;
        }
        .signature-box .name {
            font-size: 11px;
            color: #777777;
        }

        /* ── Print Button (screen only) ──────────────────────────── */
        .print-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #171717;
            color: white;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 999;
            gap: 12px;
        }
        .print-bar .info { font-size: 13px; }
        .print-bar .info span { color: #7dd3fc; font-weight: 600; }
        .print-bar .actions { display: flex; gap: 8px; }
        .btn-print {
            background: #F56C3E;
            color: white;
            border: none;
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .btn-back {
            background: #333333;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .screen-spacer { height: 52px; }

        /* ── Media Print ─────────────────────────────────────────── */
        @media print {
            body { background: white; font-size: 12px; }
            .print-bar, .screen-spacer { display: none !important; }
            .page { padding: 15mm 15mm; margin: 0; max-width: 100%; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            .section-title { page-break-after: avoid; }
            .signature-section { page-break-inside: avoid; }
        }

        @media screen {
            .page { margin-top: 8px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); border-radius: 4px; }
        }
    </style>
</head>
<body>

    {{-- Print bar (screen only) --}}
    <div class="print-bar">
        <div class="info">
            Laporan Keuangan Bulanan —
            <span>{{ \Carbon\Carbon::createFromDate($tahun, $bulan, 1)->translatedFormat('F Y') }}</span>
        </div>
        <div class="actions">
            <a href="{{ url('/reports/monthly?' . http_build_query(['bulan' => $bulan < 2 ? 12 : $bulan - 1, 'tahun' => $bulan < 2 ? $tahun - 1 : $tahun])) }}" class="btn-back">← Bulan Lalu</a>
            <a href="{{ url('/reports/monthly?' . http_build_query(['bulan' => $bulan > 11 ? 1 : $bulan + 1, 'tahun' => $bulan > 11 ? $tahun + 1 : $tahun])) }}" class="btn-back">Bulan Depan →</a>
            <button class="btn-print" onclick="window.print()">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Cetak / Save PDF
            </button>
        </div>
    </div>
    <div class="screen-spacer"></div>

    <div class="page">

        {{-- Header --}}
        <div class="report-header">
            @if($schoolLogo)
            <img src="{{ asset('storage/' . $schoolLogo) }}" alt="Logo">
            @else
            <div style="width:60px;height:60px;background:#F56C3E;border-radius:12px;display:flex;align-items:center;justify-content:center;">
                <svg width="32" height="32" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
            @endif
            <div class="school-info">
                <h1>{{ $schoolName }}</h1>
                @if($schoolAddress)
                <p>{{ $schoolAddress }}</p>
                @endif
            </div>
        </div>

        <div class="report-title">
            <h2>Laporan Keuangan Bulanan</h2>
            <p>Periode: {{ \Carbon\Carbon::createFromDate($tahun, $bulan, 1)->translatedFormat('F Y') }}</p>
        </div>

        {{-- Summary --}}
        <div class="summary-grid">
            <div class="summary-box total">
                <div class="label">Total Pemasukan</div>
                <div class="value">Rp {{ number_format($totalIncome, 0, ',', '.') }}</div>
            </div>
            <div class="summary-box">
                <div class="label">Tunai (Cash)</div>
                <div class="value">Rp {{ number_format($totalCash, 0, ',', '.') }}</div>
            </div>
            <div class="summary-box">
                <div class="label">Midtrans (Online)</div>
                <div class="value">Rp {{ number_format($totalMidtrans, 0, ',', '.') }}</div>
            </div>
            <div class="summary-box">
                <div class="label">Kantin (POS)</div>
                <div class="value">Rp {{ number_format($totalPos, 0, ',', '.') }}</div>
            </div>
        </div>

        {{-- Rekap per kelas --}}
        @if($rekapKelas->count() > 0)
        <div class="section-title">Rekap per Kelas</div>
        <div class="rekap-grid">
            @foreach($rekapKelas as $rk)
            <div class="rekap-item">
                <div class="kelas-name">{{ $rk['kelas'] }}</div>
                <div class="kelas-total">Rp {{ number_format($rk['total'], 0, ',', '.') }}</div>
                <div class="kelas-count">{{ $rk['count'] }} transaksi</div>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Tabel tagihan --}}
        <div class="section-title">Transaksi Tagihan Sekolah</div>
        @if($billTransactions->count() > 0)
        <table>
            <thead>
                <tr>
                    <th style="width:30px">No</th>
                    <th>Nama Siswa</th>
                    <th>Kelas</th>
                    <th>Jenis Tagihan</th>
                    <th class="text-right">Jumlah</th>
                    <th>Metode</th>
                    <th>Tanggal Bayar</th>
                </tr>
            </thead>
            <tbody>
                @foreach($billTransactions as $i => $bill)
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td>{{ $bill->student->name ?? '-' }}</td>
                    <td>{{ optional($bill->student->kelas)->nama_kelas ?? '-' }}</td>
                    <td>{{ $bill->name }}</td>
                    <td class="text-right">Rp {{ number_format($bill->amount, 0, ',', '.') }}</td>
                    <td>{{ $bill->payment_method ?? '-' }}</td>
                    <td>{{ $bill->paid_at?->format('d/m/Y') ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-right">Total Tagihan</td>
                    <td class="text-right">Rp {{ number_format($billTransactions->sum('amount'), 0, ',', '.') }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
        @else
        <p style="color:#777777;font-size:12px;padding:12px 0;">Tidak ada transaksi tagihan bulan ini.</p>
        @endif

        {{-- Tabel POS --}}
        <div class="section-title">Transaksi Kantin (POS)</div>
        @if($posTransactions->count() > 0)
        <table>
            <thead>
                <tr>
                    <th style="width:30px">No</th>
                    <th>Kode Transaksi</th>
                    <th>Item</th>
                    <th class="text-right">Total</th>
                    <th>Kasir</th>
                    <th>Tanggal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($posTransactions as $i => $order)
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td>
                    <td>{{ $order->transaction_code }}</td>
                    <td style="font-size:10px;color:#777777;">
                        {{ $order->items->map(fn($it) => ($it->item->name ?? '?') . ' x' . $it->qty)->join(', ') }}
                    </td>
                    <td class="text-right">Rp {{ number_format($order->payment_amount, 0, ',', '.') }}</td>
                    <td>{{ optional($order->user)->name ?? '-' }}</td>
                    <td>{{ $order->created_at?->format('d/m/Y H:i') }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-right">Total POS</td>
                    <td class="text-right">Rp {{ number_format($posTransactions->sum('payment_amount'), 0, ',', '.') }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
        @else
        <p style="color:#777777;font-size:12px;padding:12px 0;">Tidak ada transaksi kantin bulan ini.</p>
        @endif

        {{-- Signature --}}
        <div class="signature-section">
            <div class="signature-box">
                <div class="title">Mengetahui,<br>Kepala Sekolah</div>
                <div class="name-line"></div>
                <div class="name">( ________________________________ )</div>
            </div>
            <div class="signature-box">
                <div class="title">Dibuat oleh,<br>Bendahara</div>
                <div class="name-line"></div>
                <div class="name">( ________________________________ )</div>
            </div>
        </div>

    </div>{{-- .page --}}

</body>
</html>
