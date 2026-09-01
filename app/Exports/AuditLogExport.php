<?php

namespace App\Exports;

use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithDefaultStyles;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Style;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class AuditLogExport implements \Maatwebsite\Excel\Concerns\Export, WithMultipleSheets
{
    protected array $filters;
    protected string $schoolName;

    public function __construct(array $filters = [], string $schoolName = 'SMK')
    {
        $this->filters    = $filters;
        $this->schoolName = $schoolName;
    }

    public function sheets(): array
    {
        return [
            new AuditLogDataSheet($this->filters, $this->schoolName),
            new AuditLogSummarySheet($this->filters, $this->schoolName),
        ];
    }
}

// ─────────────────────────────────────────────────────────
// Sheet 1 — Data Lengkap
// ─────────────────────────────────────────────────────────
class AuditLogDataSheet implements
    \Maatwebsite\Excel\Concerns\Export,
    WithTitle,
    WithStyles,
    WithColumnWidths,
    ShouldAutoSize
{
    use \Maatwebsite\Excel\Concerns\Exportable;

    protected array $filters;
    protected string $schoolName;

    public function __construct(array $filters, string $schoolName)
    {
        $this->filters    = $filters;
        $this->schoolName = $schoolName;
    }

    public function title(): string
    {
        return 'Log Aktivitas';
    }

    /**
     * Build rows manually — avoids FromView dependency on blade context.
     * Returns array of arrays (header + data rows).
     */
    public function collection(): \Illuminate\Support\Collection
    {
        return $this->buildRows();
    }

    private function query()
    {
        $q = AuditLog::with('user')->latest();

        if (!empty($this->filters['action']))     $q->where('action', $this->filters['action']);
        if (!empty($this->filters['module']))     $q->where('module', $this->filters['module']);
        if (!empty($this->filters['search']))     $q->where(fn($s) => $s->where('description', 'like', '%'.$this->filters['search'].'%')->orWhere('action', 'like', '%'.$this->filters['search'].'%'));
        if (!empty($this->filters['start_date'])) $q->whereDate('created_at', '>=', $this->filters['start_date']);
        if (!empty($this->filters['end_date']))   $q->whereDate('created_at', '<=', $this->filters['end_date']);

        return $q->get();
    }

    private function buildRows(): \Illuminate\Support\Collection
    {
        $rows = collect();

        // Row 1 — Judul
        $rows->push([$this->schoolName . ' — Laporan Audit Trail', '', '', '', '', '', '']);
        // Row 2 — Periode
        $start = $this->filters['start_date'] ?? '-';
        $end   = $this->filters['end_date']   ?? '-';
        $rows->push(['Periode: ' . $start . ' s/d ' . $end, '', '', '', '', '', '']);
        // Row 3 — Tanggal cetak
        $rows->push(['Dicetak: ' . now()->isoFormat('D MMMM Y, HH:mm'), '', '', '', '', '', '']);
        // Row 4 — kosong
        $rows->push(['', '', '', '', '', '', '']);
        // Row 5 — Header kolom
        $rows->push(['WAKTU', 'USER', 'EMAIL', 'AKSI', 'MODUL', 'DESKRIPSI', 'IP ADDRESS']);

        // Data rows
        foreach ($this->query() as $log) {
            $rows->push([
                $log->created_at->format('d/m/Y H:i:s'),
                $log->user->name  ?? '—',
                $log->user->email ?? '—',
                $log->action,
                strtoupper($log->module ?? '—'),
                $log->description,
                $log->ip_address ?? '—',
            ]);
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();

        // Merge judul/periode/tanggal di kolom A–G
        $sheet->mergeCells('A1:G1');
        $sheet->mergeCells('A2:G2');
        $sheet->mergeCells('A3:G3');

        return [
            // Baris 1 — Judul utama
            1 => [
                'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0369A1']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
            // Baris 2 & 3 — Periode dan tanggal cetak
            2 => [
                'font' => ['italic' => true, 'size' => 10, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0284C7']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            3 => [
                'font' => ['italic' => true, 'size' => 10, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0284C7']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            // Baris 5 — Header tabel
            5 => [
                'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E293B']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
            // Data rows — zebra stripe
            "A6:G{$lastRow}" => [
                'font'      => ['size' => 9],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color'       => ['argb' => 'FFE2E8F0'],
                    ],
                ],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18, // Waktu
            'B' => 20, // User
            'C' => 28, // Email
            'D' => 22, // Aksi
            'E' => 14, // Modul
            'F' => 45, // Deskripsi
            'G' => 16, // IP
        ];
    }

    // Required by Exportable trait
    public function download(string $fileName, string $writerType = \Maatwebsite\Excel\Excel::XLSX, array $headers = [])
    {
        return \Maatwebsite\Excel\Facades\Excel::download($this, $fileName, $writerType, $headers);
    }
}

// ─────────────────────────────────────────────────────────
// Sheet 2 — Ringkasan / Summary
// ─────────────────────────────────────────────────────────
class AuditLogSummarySheet implements \Maatwebsite\Excel\Concerns\Export, WithTitle, WithStyles, WithColumnWidths, ShouldAutoSize
{
    use \Maatwebsite\Excel\Concerns\Exportable;

    protected array $filters;
    protected string $schoolName;

    public function __construct(array $filters, string $schoolName)
    {
        $this->filters    = $filters;
        $this->schoolName = $schoolName;
    }

    public function title(): string
    {
        return 'Ringkasan';
    }

    public function collection(): \Illuminate\Support\Collection
    {
        return $this->buildRows();
    }

    private function buildRows(): \Illuminate\Support\Collection
    {
        $q = AuditLog::with('user');
        if (!empty($this->filters['start_date'])) $q->whereDate('created_at', '>=', $this->filters['start_date']);
        if (!empty($this->filters['end_date']))   $q->whereDate('created_at', '<=', $this->filters['end_date']);
        $logs = $q->get();

        $rows = collect();
        $rows->push([$this->schoolName . ' — Ringkasan Audit Trail', '']);
        $start = $this->filters['start_date'] ?? '-';
        $end   = $this->filters['end_date']   ?? '-';
        $rows->push(['Periode: ' . $start . ' s/d ' . $end, '']);
        $rows->push(['', '']);
        $rows->push(['STATISTIK', 'JUMLAH']);
        $rows->push(['Total Log', $logs->count()]);
        $rows->push(['', '']);
        $rows->push(['PER AKSI', 'JUMLAH']);

        $perAksi = $logs->groupBy('action');
        foreach ($perAksi as $aksi => $group) {
            $rows->push([$aksi, $group->count()]);
        }

        $rows->push(['', '']);
        $rows->push(['PER MODUL', 'JUMLAH']);

        $perModul = $logs->groupBy('module');
        foreach ($perModul as $modul => $group) {
            $rows->push([strtoupper($modul ?? '—'), $group->count()]);
        }

        $rows->push(['', '']);
        $rows->push(['PER USER', 'JUMLAH']);

        $perUser = $logs->groupBy(fn($l) => $l->user->name ?? 'Unknown');
        foreach ($perUser as $user => $group) {
            $rows->push([$user, $group->count()]);
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:B1');
        $sheet->mergeCells('A2:B2');

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 13, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0369A1']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            2 => [
                'font' => ['italic' => true, 'size' => 10, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0284C7']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            4 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E293B']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 35, 'B' => 15];
    }

    public function download(string $fileName, string $writerType = \Maatwebsite\Excel\Excel::XLSX, array $headers = [])
    {
        return \Maatwebsite\Excel\Facades\Excel::download($this, $fileName, $writerType, $headers);
    }
}
