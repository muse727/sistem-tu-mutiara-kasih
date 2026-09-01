<?php

namespace App\Filament\Widgets;

use App\Models\Kelas;
use App\Models\Student;
use App\Models\StudentBill;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $siswAktif       = Student::where('status', 'active')->count();
        $totalSiswa      = Student::count();
        $tagihanBelumBayar = StudentBill::where('status', 'UNPAID')->count();
        $tagihanLunas    = StudentBill::where('status', 'PAID')->whereMonth('paid_at', now()->month)->count();
        $tagihanJatuhTempo = StudentBill::where('status', 'UNPAID')
            ->whereDate('due_date', '<', now())->count();
        $totalPendapatanBulanIni = StudentBill::where('status', 'PAID')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        return [
            Stat::make('Siswa Aktif', number_format($siswAktif))
                ->description("Dari {$totalSiswa} total siswa")
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success')
                ->chart([
                    Student::where('status', 'active')->whereDate('created_at', '>=', now()->subDays(6))->count(),
                    $siswAktif,
                ]),

            Stat::make('Tagihan Belum Bayar', number_format($tagihanBelumBayar))
                ->description($tagihanJatuhTempo > 0 ? "{$tagihanJatuhTempo} sudah jatuh tempo" : 'Semua masih dalam waktu')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($tagihanJatuhTempo > 0 ? 'danger' : 'warning'),

            Stat::make('Lunas Bulan Ini', number_format($tagihanLunas))
                ->description('Tagihan terbayar ' . now()->translatedFormat('F Y'))
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('info'),

            Stat::make('Pendapatan ' . now()->translatedFormat('F'), 'Rp ' . number_format($totalPendapatanBulanIni, 0, ',', '.'))
                ->description('Total pembayaran bulan ini')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
        ];
    }
}
