<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\FinancialAuditLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthSiswaController extends Controller
{
    public function showLogin()
    {
        if (Auth::guard('siswa')->check()) {
            return redirect()->route('siswa.dashboard');
        }

        $schoolLogo  = DB::table('school_settings')->where('key', 'school_logo')->value('value');
        $schoolName  = DB::table('school_settings')->where('key', 'school_name')->value('value');
        $sidebarColor = DB::table('school_settings')->where('key', 'sidebar_color')->value('value') ?? 'blue';

        // Map sidebar_color ke Tailwind gradient class
        $gradientMap = [
            'blue'   => 'from-sky-400 via-sky-600 to-blue-900',
            'indigo' => 'from-indigo-400 via-indigo-600 to-indigo-900',
            'violet' => 'from-violet-400 via-violet-600 to-violet-900',
            'pink'   => 'from-pink-400 via-pink-500 to-pink-900',
            'rose'   => 'from-rose-400 via-rose-600 to-rose-900',
            'orange' => 'from-orange-400 via-orange-500 to-orange-800',
            'amber'  => 'from-amber-400 via-amber-500 to-amber-800',
            'green'  => 'from-green-400 via-green-600 to-green-900',
            'teal'   => 'from-teal-400 via-teal-600 to-teal-900',
            'brown'  => 'from-stone-400 via-stone-600 to-stone-900',
            'slate'  => 'from-slate-400 via-slate-600 to-slate-900',
            'black'  => 'from-gray-700 via-gray-800 to-gray-950',
        ];
        $bgGradient = $gradientMap[$sidebarColor] ?? $gradientMap['blue'];

        return view('siswa.auth.login', compact('schoolLogo', 'schoolName', 'bgGradient'));
    }

    public function login(Request $request)
    {
        $request->validate([
            'nis'      => 'required|string',
            'password' => 'required|string',
            'cf-turnstile-response' => 'required|string',
        ], [
            'cf-turnstile-response.required' => 'Captcha wajib diselesaikan.',
        ]);

        // Verifikasi Cloudflare Turnstile (via injectable boundary so tests
        // can fake the HTTP call without disabling verification).
        $verified = app(\App\Services\Security\TurnstileVerifier::class)
            ->verify($request->input('cf-turnstile-response'), $request->ip());

        if (! $verified) {
            return back()
                ->withErrors(['cf-turnstile-response' => 'Verifikasi captcha gagal. Coba lagi.'])
                ->withInput();
        }

        // Cari siswa berdasarkan NIS, hanya yang aktif
        $student = Student::where('nis', $request->nis)
                          ->where('status', 'active')
                          ->first();

        if (!$student) {
            return back()
                ->withErrors(['nis' => 'NIS tidak ditemukan atau akun tidak aktif.'])
                ->withInput();
        }

        // Verifikasi password: custom_password (bcrypt) jika sudah diset, fallback ke birth_date (ddmmyy)
        $authenticated = false;
        if ($student->custom_password) {
            $authenticated = \Illuminate\Support\Facades\Hash::check($request->password, $student->custom_password);
        } else {
            $expected = Carbon::parse($student->birth_date)->format('dmy');
            $authenticated = ($expected === $request->password);
        }

        if (! $authenticated) {
            FinancialAuditLogger::studentLoginFailed($request->nis, 'Password salah', $request);
            return back()
                ->withErrors(['password' => 'Password salah.'])
                ->withInput();
        }

        // Transparent password hashing: Jika password valid dan belum di-hash di DB, lakukan sekarang
        if (empty($student->custom_password)) {
            $student->custom_password = \Illuminate\Support\Facades\Hash::make($request->password);
            $student->save();
        }

        // Login manual via guard siswa (tanpa attempt() karena password tidak di-hash)
        Auth::guard('siswa')->login($student, $request->boolean('remember'));
        $request->session()->regenerate();

        FinancialAuditLogger::studentLogin($student->id, $student->nis, $student->name, $request);

        return redirect()->route('siswa.dashboard');
    }

    public function logout(Request $request)
    {
        $student = Auth::guard('siswa')->user();
        if ($student) {
            FinancialAuditLogger::studentLogout($student->id, $student->nis, $student->name, $request);
        }
        Auth::guard('siswa')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('siswa.login');
    }
}
