<?php

namespace App\Http\Controllers;

use App\Models\PosOrder;
use App\Models\StudentBill;
use App\Services\FinancialAuditLogger;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosReportController extends Controller
{
    public function index(Request $request)
    {
        // 1. Filter tanggal (default: hari ini)
        $startDate = $request->start_date ?? date('Y-m-d');
        $endDate   = $request->end_date   ?? date('Y-m-d');

        // 2. Base query dengan eager load
        $baseQuery = PosOrder::with(['user', 'student', 'items.item'])->withCount('items')
            ->whereBetween('created_at', [
                "$startDate 00:00:00",
                "$endDate 23:59:59",
            ]);

        // 3. Filter status opsional
        if ($request->filled('status')) {
            $baseQuery->where('payment_status', $request->status);
        }

        // Sort: oldest_unpaid — tampilkan piutang terlama dulu.
        // PENTING: jangan tambah ->latest() lagi di bawah — akan override sort ini.
        if ($request->sort === 'oldest_unpaid') {
            $baseQuery->where('payment_status', 'UNPAID')->oldest('created_at');
        } else {
            $baseQuery->latest('created_at');
        }

        // BUG FIX: Hitung summary dari query terpisah — bukan clone yang
        // sudah termutasi. Setiap aggregate pakai query builder segar
        // dengan kondisi yang sama agar angka tidak saling terkontaminasi.
        $summaryBase = function () use ($startDate, $endDate, $request) {
            $q = PosOrder::whereBetween('created_at', [
                "$startDate 00:00:00",
                "$endDate 23:59:59",
            ]);
            if ($request->filled('status')) {
                $q->where('payment_status', $request->status);
            }
            return $q;
        };

        // Total omset: semua transaksi dalam rentang & filter
        $totalOmset = (clone $summaryBase())->sum('total_amount');

        // Uang masuk: hanya dari transaksi PAID
        $totalCashIn = (clone $summaryBase())
            ->where('payment_status', 'PAID')
            ->sum('payment_amount');

        // Piutang: total dari transaksi UNPAID
        $totalUnpaid = (clone $summaryBase())
            ->where('payment_status', 'UNPAID')
            ->sum('total_amount');

        // 4. Data tabel dengan pagination
        // TIDAK pakai ->latest() di sini — sort sudah ditentukan di atas.
        $transactions = $baseQuery->paginate(10)->withQueryString();

        return view('pos.history.index', compact(
            'transactions',
            'totalOmset',
            'totalCashIn',
            'totalUnpaid',
            'startDate',
            'endDate'
        ));
    }

    /**
     * Pelunasan hutang POS.
     * Hanya bisa dilakukan pada transaksi UNPAID.
     */
    public function repay(Request $request, $id)
    {
        $request->validate([
            'payment_amount' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $transaction = PosOrder::findOrFail($id);

            if ($transaction->payment_status === 'PAID') {
                return back()->with('error', 'Transaksi ini sudah lunas!');
            }

            $paymentAmount = (float) $request->payment_amount;
            $changeAmount  = max(0, $paymentAmount - $transaction->total_amount);

            $transaction->update([
                'payment_status' => 'PAID',
                'payment_amount' => $paymentAmount,
                'change_amount'  => $changeAmount,
            ]);

            // Opsi C: sync StudentBill type KANTIN yang terhubung ke pos_order ini.
            // Pastikan student_bills ikut PAID agar piutang siswa bersih.
            $kantinBill = StudentBill::where('pos_order_id', $transaction->id)
                ->where('type', 'KANTIN')
                ->where('status', 'UNPAID')
                ->first();

            if ($kantinBill) {
                $kantinBill->update([
                    'status'         => 'PAID',
                    'paid_at'        => now(),
                    'payment_method' => 'CASH',
                ]);
                FinancialAuditLogger::paymentConfirmed(
                    $kantinBill,
                    AuditLog::SOURCE_WEB,
                    auth()->id()
                );
            }

            DB::commit();

            $msg = 'Hutang berhasil dilunasi!';
            if ($changeAmount > 0) {
                $msg .= ' Kembalian: Rp ' . number_format($changeAmount, 0, ',', '.');
            }
            return back()->with('success', $msg);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Gagal memproses pelunasan: ' . $e->getMessage());
        }
    }
}
