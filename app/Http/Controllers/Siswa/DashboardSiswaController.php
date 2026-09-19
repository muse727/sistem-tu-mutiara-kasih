<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;

use App\Models\StudentBill;
use App\Services\FinancialAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardSiswaController extends Controller
{
    public function index()
    {
        $student = Auth::guard('siswa')->user();

        $bills = StudentBill::where('student_id', $student->id)
                            ->with('items')
                            ->orderByDesc('bill_year')
                            ->orderByDesc('bill_month')
                            ->get();

        $unpaidBills = $bills->whereIn('status', ['UNPAID', 'PARTIAL']);
        $paidBills   = $bills->where('status', 'PAID');
        $totalUnpaid = $unpaidBills->sum('amount');
        $totalPaid   = $paidBills->sum('amount');

        $schoolName = DB::table('school_settings')->where('key', 'school_name')->value('value') ?? 'Sekolah';

        $thisMonthBills = StudentBill::where('student_id', $student->id)
                            ->with('items')
                            ->where('bill_month', now()->month)
                            ->where('bill_year', now()->year)
                            ->orderBy('name')
                            ->get();

        return view('siswa.dashboard', compact(
            'student', 'bills', 'unpaidBills', 'paidBills',
            'totalUnpaid', 'totalPaid', 'schoolName', 'thisMonthBills'
        ));
    }

    /**
     * Riwayat pembayaran (tagihan berstatus PAID) dengan filter periode
     * (tahun/bulan tagihan) dan paginasi server-side.
     *
     * HANYA menyaring riwayat lunas — perilaku tagihan belum bayar dan
     * dashboard utama tidak berubah. Memakai field periode yang sudah
     * tersimpan (bill_year / bill_month) dari sumber data StudentBill yang sama.
     */
    public function history(Request $request)
    {
        $student = Auth::guard('siswa')->user();

        $tahun = $request->query('tahun');
        $bulan = $request->query('bulan');

        $base = StudentBill::where('student_id', $student->id)
                    ->where('status', 'PAID');

        // Tahun yang tersedia dari riwayat lunas siswa (untuk dropdown).
        $availableYears = (clone $base)
                    ->select('bill_year')
                    ->distinct()
                    ->orderByDesc('bill_year')
                    ->pluck('bill_year');

        $query = (clone $base)->with('items');

        if ($tahun !== null && $tahun !== '' && ctype_digit((string) $tahun)) {
            $query->where('bill_year', (int) $tahun);
        }
        if ($bulan !== null && $bulan !== '' && ctype_digit((string) $bulan)
            && (int) $bulan >= 1 && (int) $bulan <= 12) {
            $query->where('bill_month', (int) $bulan);
        }

        $paidBills = $query->orderByDesc('bill_year')
                    ->orderByDesc('bill_month')
                    ->orderByDesc('paid_at')
                    ->paginate(10)
                    ->appends(array_filter(['tahun' => $tahun, 'bulan' => $bulan]));

        $schoolName = DB::table('school_settings')->where('key', 'school_name')->value('value') ?? 'Sekolah';

        $hasAnyPaid = (clone $base)->exists();

        return view('siswa.history', compact(
            'student', 'paidBills', 'availableYears', 'schoolName',
            'tahun', 'bulan', 'hasAnyPaid'
        ));
    }

    public function detail(StudentBill $bill)
    {
        $student = Auth::guard('siswa')->user();

        // Pastikan tagihan milik siswa ini
        if ((int) $bill->student_id !== (int) $student->id) {
            abort(403);
        }

        $schoolName = DB::table('school_settings')->where('key', 'school_name')->value('value') ?? 'Sekolah';

        $bill->load('items');

        FinancialAuditLogger::billViewed($student->id, $student->name, $bill, request());

        return view('siswa.bill-detail', compact('bill', 'student', 'schoolName'));
    }

    public function struk(StudentBill $bill)
    {
        $student = Auth::guard('siswa')->user();

        if ((int) $bill->student_id !== (int) $student->id) {
            abort(403);
        }

        if ($bill->status !== 'PAID') {
            return redirect()->route('siswa.dashboard');
        }

        $schoolName    = DB::table('school_settings')->where('key', 'school_name')->value('value') ?? 'Sekolah';
        $schoolAddress = DB::table('school_settings')->where('key', 'school_address')->value('value') ?? '';

        $bill->load('items');

        FinancialAuditLogger::receiptDownloaded($student->id, $student->name, $bill, request());

        return view('siswa.struk', compact('bill', 'student', 'schoolName', 'schoolAddress'));
    }
}
