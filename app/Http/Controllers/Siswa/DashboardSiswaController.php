<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;

use App\Models\StudentBill;
use App\Services\FinancialAuditLogger;
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
