<?php

namespace App\Http\Controllers;

use App\Models\StudentBill;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Laporan Bulanan — PDF/Print-ready
     */
    public function monthlyReport(Request $request)
    {
        $bulan  = (int) ($request->bulan ?? now()->month);
        $tahun  = (int) ($request->tahun ?? now()->year);

        // Validasi range
        $bulan = max(1, min(12, $bulan));
        $tahun = max(2000, min(2100, $tahun));

        $periode = Carbon::createFromDate($tahun, $bulan, 1);

        // ─── Summary per metode ───────────────────────────────────────────────
        $totalCash     = StudentBill::whereMonth('paid_at', $bulan)
            ->whereYear('paid_at', $tahun)
            ->where('status', 'PAID')
            ->where('payment_method', 'CASH')
            ->sum('amount');

        $totalMidtrans = StudentBill::whereMonth('paid_at', $bulan)
            ->whereYear('paid_at', $tahun)
            ->where('status', 'PAID')
            ->where('payment_method', 'MIDTRANS')
            ->sum('amount');

        $totalPos      = PosOrder::whereMonth('created_at', $bulan)
            ->whereYear('created_at', $tahun)
            ->where('payment_status', 'PAID')
            ->sum('payment_amount');

        $totalIncome   = $totalCash + $totalMidtrans + $totalPos;

        // ─── Transaksi tagihan ────────────────────────────────────────────────
        $billTransactions = StudentBill::with(['student.kelas'])
            ->whereMonth('paid_at', $bulan)
            ->whereYear('paid_at', $tahun)
            ->where('status', 'PAID')
            ->orderBy('paid_at')
            ->get();

        // ─── Rekap per kelas ─────────────────────────────────────────────────
        $rekapKelas = $billTransactions
            ->groupBy(fn($b) => optional($b->student->kelas)->nama_kelas ?? 'Tidak Berkelas')
            ->map(fn($group) => [
                'kelas'  => $group->first()->student->kelas->nama_kelas ?? 'Tidak Berkelas',
                'count'  => $group->count(),
                'total'  => $group->sum('amount'),
            ])
            ->sortBy('kelas')
            ->values();

        // ─── Transaksi POS ────────────────────────────────────────────────────
        $posTransactions = PosOrder::with(['orderItems.item'])
            ->whereMonth('created_at', $bulan)
            ->whereYear('created_at', $tahun)
            ->where('payment_status', 'PAID')
            ->orderBy('created_at')
            ->get();

        // ─── School settings ─────────────────────────────────────────────────
        $schoolName = \DB::table('school_settings')->where('key', 'school_name')->value('value')
                    ?? config('app.name', 'Sistem TU');
        $schoolLogo = \DB::table('school_settings')->where('key', 'school_logo')->value('value');
        $schoolAddress = \DB::table('school_settings')->where('key', 'school_address')->value('value') ?? '';

        return view('reports.monthly', compact(
            'bulan', 'tahun', 'periode',
            'totalCash', 'totalMidtrans', 'totalPos', 'totalIncome',
            'billTransactions', 'rekapKelas', 'posTransactions',
            'schoolName', 'schoolLogo', 'schoolAddress'
        ));
    }
}
