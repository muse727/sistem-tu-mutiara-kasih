<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\StudentBill;
use App\Services\FinancialAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VerifyPaymentController extends Controller
{
    /**
     * Halaman verifikasi publik — bisa diakses tanpa login.
     * URL: /verify/{token}
     */
    public function show(string $token, Request $request)
    {
        $bill = StudentBill::where('verify_token', $token)
                           ->with(['student', 'items'])
                           ->first();

        $schoolName    = DB::table('school_settings')->where('key', 'school_name')->value('value')    ?? 'Sekolah';
        $schoolAddress = DB::table('school_settings')->where('key', 'school_address')->value('value') ?? '';

        if (! $bill) {
            return view('siswa.verify', [
                'valid'         => false,
                'bill'          => null,
                'schoolName'    => $schoolName,
                'schoolAddress' => $schoolAddress,
            ]);
        }

        FinancialAuditLogger::receiptVerified($bill, $token, $request);

        return view('siswa.verify', [
            'valid'         => true,
            'bill'          => $bill,
            'schoolName'    => $schoolName,
            'schoolAddress' => $schoolAddress,
        ]);
    }
}
