<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Maatwebsite\Excel\Events\AfterSheet;
use Carbon\Carbon;

class BillsSummarySheet implements FromArray, WithTitle, WithStyles, WithColumnWidths, WithEvents
{
    protected Collection $bills;
    protected array $filters;
    protected array $school;

    public function __construct(Collection $bills, array $filters = [], array $school = [])
    {
        $this->bills   = $bills;
        $this->filters = $filters;
        $this->school  = $school;
    }

    public function title(): string
    {
        return 'Ringkasan';
    }

    public function array(): array
    {
        $bills    = $this->bills;
        $school   = $this->school;
        $now      = Carbon::now('Asia/Jakarta');

        // ── Hitung statistik ──────────────────────────────────────────────
        $total        = $bills->count();
        $paid         = $bills->where('status', 'PAID')->count();
        $unpaid       = $bills->whereIn('status', ['UNPAID', 'PENDING'])->count();
        $overdue      = $bills->where('status', 'OVERDUE')->count();

        $totalNominal = $bills->sum('amount');
        $paidNominal  = $bills->where('status', 'PAID')->sum('amount');
        $unpaidNominal= $bills->whereNotIn('status', ['PAID'])->sum('amount');

        $paidPct   = $total > 0 ? round($paid / $total * 100, 1) : 0;
        $unpaidPct = $total > 0 ? round($unpaid / $total * 100, 1) : 0;

        // ── Rekapitulasi per Kelas ────────────────────────────────────────
        $byKelas = $bills->groupBy(fn($b) => optional($b->student->kelas)->nama_kelas ?? 'Tidak Diketahui');

        // ── Rekapitulasi per Jenis Tagihan ────────────────────────────────
        $byType = $bills->groupBy('type');

        // ── Build rows ───────────────────────────────────────────────────
        $rows = [];

        // Header sekolah
        $rows[] = [$school['school_name'] ?? 'SEKOLAH'];
        $rows[] = [$school['school_address'] ?? ''];
        $rows[] = ['Telp: ' . ($school['school_phone'] ?? '-')];
        $rows[] = [''];
        $rows[] = ['LAPORAN REKAP TAGIHAN SISWA'];
        $rows[] = ['Dicetak pada: ' . $now->translatedFormat('d F Y, H:i') . ' WIB'];

        // Filter info
        if (!empty($this->filters['start_date']) && !empty($this->filters['end_date'])) {
            $rows[] = ['Periode: ' . $this->filters['start_date'] . ' s/d ' . $this->filters['end_date']];
        }
        if (!empty($this->filters['kelas_id'])) {
            $rows[] = ['Filter Kelas: ' . ($this->filters['kelas_label'] ?? $this->filters['kelas_id'])];
        }
        if (!empty($this->filters['status'])) {
            $rows[] = ['Filter Status: ' . $this->filters['status']];
        }

        $rows[] = [''];
        $rows[] = ['━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━'];
        $rows[] = ['RINGKASAN UMUM'];
        $rows[] = ['━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━'];
        $rows[] = [''];

        // KPI table header
        $rows[] = ['Keterangan', 'Jumlah', 'Nominal (Rp)', 'Persentase'];
        $rows[] = ['Total Tagihan',           $total,  $totalNominal,  '100%'];
        $rows[] = ['Tagihan Lunas (PAID)',     $paid,   $paidNominal,   $paidPct . '%'];
        $rows[] = ['Belum Lunas (UNPAID)',     $unpaid, $unpaidNominal, $unpaidPct . '%'];
        $rows[] = ['Lewat Jatuh Tempo (OVERDUE)', $overdue, $bills->where('status','OVERDUE')->sum('amount'), ''];
        $rows[] = [''];
        $rows[] = ['Uang Masuk (Terkumpul)',   '',      $paidNominal,   ''];
        $rows[] = ['Sisa Tunggakan',           '',      $unpaidNominal, ''];
        $rows[] = [''];

        // Rekapitulasi per kelas
        $rows[] = ['━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━'];
        $rows[] = ['REKAPITULASI PER KELAS'];
        $rows[] = ['━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━'];
        $rows[] = [''];
        $rows[] = ['Kelas', 'Total Tagihan', 'Sudah Bayar', 'Belum Bayar', 'Total Nominal', 'Terkumpul', 'Tunggakan'];

        foreach ($byKelas->sortKeys() as $kelas => $items) {
            $kelasPaid   = $items->where('status', 'PAID');
            $kelasUnpaid = $items->whereNotIn('status', ['PAID']);
            $rows[] = [
                $kelas,
                $items->count(),
                $kelasPaid->count(),
                $kelasUnpaid->count(),
                $items->sum('amount'),
                $kelasPaid->sum('amount'),
                $kelasUnpaid->sum('amount'),
            ];
        }

        $rows[] = [''];

        // Rekapitulasi per jenis tagihan
        $rows[] = ['━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━'];
        $rows[] = ['REKAPITULASI PER JENIS TAGIHAN'];
        $rows[] = ['━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━'];
        $rows[] = [''];
        $rows[] = ['Jenis Tagihan', 'Total', 'Lunas', 'Belum Lunas', 'Total Nominal', 'Terkumpul', 'Tunggakan'];

        foreach ($byType->sortKeys() as $type => $items) {
            $typePaid   = $items->where('status', 'PAID');
            $typeUnpaid = $items->whereNotIn('status', ['PAID']);
            $rows[] = [
                $type,
                $items->count(),
                $typePaid->count(),
                $typeUnpaid->count(),
                $items->sum('amount'),
                $typePaid->sum('amount'),
                $typeUnpaid->sum('amount'),
            ];
        }

        $rows[] = [''];
        $rows[] = [''];
        $rows[] = ['Laporan ini digenerate otomatis oleh Sistem TU — ' . $now->format('d/m/Y H:i')];

        return $rows;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 38,
            'B' => 16,
            'C' => 22,
            'D' => 18,
            'E' => 22,
            'F' => 22,
            'G' => 22,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $data  = $this->array();

                // Style row 1 — nama sekolah
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 16, 'color' => ['argb' => 'FF1E3A5F']],
                ]);

                // Row 5 — judul laporan
                $sheet->getStyle('A5')->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FF1E3A5F']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                ]);

                // Deteksi row index untuk section headers dan tabel
                foreach ($data as $rowIdx => $row) {
                    $excelRow = $rowIdx + 1;

                    if (!isset($row[0])) continue;

                    $cellVal = $row[0];

                    // Section header rows
                    if (in_array($cellVal, ['RINGKASAN UMUM', 'REKAPITULASI PER KELAS', 'REKAPITULASI PER JENIS TAGIHAN'])) {
                        $sheet->getStyle("A{$excelRow}")->applyFromArray([
                            'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FFFFFFFF']],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
                        ]);
                        $sheet->mergeCells("A{$excelRow}:G{$excelRow}");
                        continue;
                    }

                    // Table header rows — KPI
                    if ($cellVal === 'Keterangan') {
                        $sheet->getStyle("A{$excelRow}:D{$excelRow}")->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2563EB']],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        ]);
                        continue;
                    }

                    // Table header rows — per kelas / per jenis
                    if ($cellVal === 'Kelas' || $cellVal === 'Jenis Tagihan') {
                        $sheet->getStyle("A{$excelRow}:G{$excelRow}")->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2563EB']],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                        ]);
                        continue;
                    }

                    // Highlight baris penting
                    if ($cellVal === 'Tagihan Lunas (PAID)') {
                        $sheet->getStyle("A{$excelRow}:D{$excelRow}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDBFCE7']],
                            'font' => ['color' => ['argb' => 'FF166534']],
                        ]);
                    }
                    if ($cellVal === 'Belum Lunas (UNPAID)') {
                        $sheet->getStyle("A{$excelRow}:D{$excelRow}")->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFEE2E2']],
                            'font' => ['color' => ['argb' => 'FF991B1B']],
                        ]);
                    }
                    if ($cellVal === 'Uang Masuk (Terkumpul)') {
                        $sheet->getStyle("A{$excelRow}:D{$excelRow}")->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['argb' => 'FF166534']],
                        ]);
                    }
                    if ($cellVal === 'Sisa Tunggakan') {
                        $sheet->getStyle("A{$excelRow}:D{$excelRow}")->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['argb' => 'FFDC2626']],
                        ]);
                    }

                    // Format kolom nominal (C) sebagai currency — untuk rows KPI
                    if (isset($row[2]) && is_numeric($row[2]) && $cellVal !== 'Keterangan') {
                        $sheet->getCell("C{$excelRow}")->setValue($row[2]);
                        $sheet->getStyle("C{$excelRow}")->getNumberFormat()->setFormatCode('#,##0');
                    }
                }

                // Format kolom nominal di tabel per-kelas dan per-jenis (E, F, G)
                $highRow = count($data) + 1;
                $sheet->getStyle("E1:G{$highRow}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("C1:C{$highRow}")->getNumberFormat()->setFormatCode('#,##0');

                // Border pada area tabel
                $sheet->getStyle("A1:G{$highRow}")->applyFromArray([
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => false],
                ]);
            },
        ];
    }
}
