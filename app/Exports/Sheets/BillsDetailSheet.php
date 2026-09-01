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
use Maatwebsite\Excel\Events\AfterSheet;
use Carbon\Carbon;

class BillsDetailSheet implements FromArray, WithTitle, WithStyles, WithColumnWidths, WithEvents
{
    protected Collection $bills;

    public function __construct(Collection $bills)
    {
        $this->bills = $bills;
    }

    public function title(): string
    {
        return 'Detail Tagihan';
    }

    public function array(): array
    {
        $rows = [];

        // Header row
        $rows[] = [
            'No',
            'Nama Siswa',
            'NIS',
            'Kelas',
            'Nama Tagihan',
            'Jenis Tagihan',
            'Nominal (Rp)',
            'Jatuh Tempo',
            'Status',
            'Tgl Dibuat',
            'Tgl Bayar',
            'Metode Bayar',
            'Dikonfirmasi Oleh',
        ];

        foreach ($this->bills as $i => $bill) {
            $kelas     = optional($bill->student->kelas)->nama_kelas ?? '-';
            $status    = $bill->status;
            $paidAt    = $bill->paid_at  ? Carbon::parse($bill->paid_at)->format('d/m/Y H:i')  : '-';
            $dueDate   = $bill->due_date ? Carbon::parse($bill->due_date)->format('d/m/Y')      : '-';
            $createdAt = $bill->created_at ? $bill->created_at->format('d/m/Y H:i')             : '-';
            $confirmedBy = optional($bill->confirmedBy)->name ?? '-';
            $payMethod = $bill->payment_method ?? '-';

            $rows[] = [
                $i + 1,
                $bill->student->name ?? '-',
                $bill->student->nis  ?? '-',
                $kelas,
                $bill->name,
                $bill->type ?? '-',
                $bill->amount,
                $dueDate,
                $status,
                $createdAt,
                $paidAt,
                $payMethod,
                $confirmedBy,
            ];
        }

        return $rows;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,
            'B' => 25,
            'C' => 14,
            'D' => 14,
            'E' => 30,
            'F' => 18,
            'G' => 18,
            'H' => 14,
            'I' => 12,
            'J' => 18,
            'K' => 18,
            'L' => 16,
            'M' => 22,
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
                $sheet   = $event->sheet->getDelegate();
                $bills   = $this->bills;
                $lastRow = count($bills) + 1;

                // ── Header row styling ─────────────────────────────────────
                $sheet->getStyle("A1:M1")->applyFromArray([
                    'font' => [
                        'bold'  => true,
                        'color' => ['argb' => 'FFFFFFFF'],
                        'size'  => 11,
                    ],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF1E3A5F'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                        'wrapText'   => true,
                    ],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(30);

                // ── Freeze header row ──────────────────────────────────────
                $sheet->freezePane('A2');

                // ── Data rows — alternating stripes + status color ─────────
                foreach ($bills as $i => $bill) {
                    $row    = $i + 2;
                    $status = $bill->status;

                    // Alternating row color
                    $bgColor = ($i % 2 === 0) ? 'FFF8FAFC' : 'FFFFFFFF';

                    // Status-based color override
                    if ($status === 'PAID') {
                        $bgColor = 'FFECFDF5'; // green tint
                    } elseif ($status === 'OVERDUE') {
                        $bgColor = 'FFFEF2F2'; // red tint
                    } elseif (in_array($status, ['UNPAID', 'PENDING'])) {
                        $bgColor = 'FFFEFCE8'; // yellow tint
                    }

                    $sheet->getStyle("A{$row}:M{$row}")->applyFromArray([
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['argb' => $bgColor],
                        ],
                        'alignment' => [
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                    ]);

                    // Status cell bold + color
                    $statusColor = match($status) {
                        'PAID'    => 'FF166534',
                        'OVERDUE' => 'FF991B1B',
                        'UNPAID'  => 'FF92400E',
                        default   => 'FF374151',
                    };
                    $sheet->getStyle("I{$row}")->applyFromArray([
                        'font'      => ['bold' => true, 'color' => ['argb' => $statusColor]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }

                // ── Nominal column format ──────────────────────────────────
                $sheet->getStyle("G2:G{$lastRow}")
                      ->getNumberFormat()
                      ->setFormatCode('#,##0');

                // ── Center alignment for specific cols ─────────────────────
                $sheet->getStyle("A2:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C2:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("H2:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("J2:K{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // ── Border semua sel ───────────────────────────────────────
                $sheet->getStyle("A1:M{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['argb' => 'FFD1D5DB'],
                        ],
                    ],
                ]);

                // Header border lebih tebal
                $sheet->getStyle("A1:M1")->applyFromArray([
                    'borders' => [
                        'bottom' => [
                            'borderStyle' => Border::BORDER_MEDIUM,
                            'color'       => ['argb' => 'FF1E3A5F'],
                        ],
                    ],
                ]);

                // ── Auto filter ────────────────────────────────────────────
                $sheet->setAutoFilter("A1:M1");
            },
        ];
    }
}
