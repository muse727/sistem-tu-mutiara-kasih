<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Services\FinancialAuditLogger;

class ProfilSiswaController extends Controller
{
    /**
     * Tampilkan halaman profil siswa.
     */
    public function show()
    {
        $student = Auth::guard('siswa')->user();
        $student->load('kelas');

        $schoolName = DB::table('school_settings')->where('key', 'school_name')->value('value') ?? 'Sekolah';

        return view('siswa.profil', compact('student', 'schoolName'));
    }

    /**
     * Update data yang bisa diedit: alamat + no HP orang tua.
     */
    public function update(Request $request)
    {
        $request->validate([
            'address'      => 'nullable|string|max:500',
            'parent_phone' => 'nullable|string|max:20|regex:/^[0-9+\-\s()]+$/',
        ], [
            'parent_phone.regex' => 'Format nomor HP tidak valid.',
        ]);

        $student = Auth::guard('siswa')->user();

        $oldValues = [
            'address'      => $student->address,
            'parent_phone' => $student->parent_phone,
        ];

        $student->update([
            'address'      => $request->input('address'),
            'parent_phone' => $request->input('parent_phone'),
        ]);

        FinancialAuditLogger::profileUpdated($student->id, $student->name, $oldValues, [
            'address'      => $request->input('address'),
            'parent_phone' => $request->input('parent_phone'),
        ], $request);

        return redirect()
            ->route('siswa.profil')
            ->with('success', 'Data berhasil diperbarui.');
    }

    /**
     * Ganti password siswa.
     * Password lama bisa berupa: birth_date (ddmmyy) atau custom_password (bcrypt).
     * Password baru di-hash dengan bcrypt.
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6|confirmed',
        ], [
            'new_password.min'       => 'Password baru minimal 6 karakter.',
            'new_password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $student = Auth::guard('siswa')->user();

        // Verifikasi password lama
        $currentValid = false;
        if ($student->custom_password) {
            // Sudah pernah ganti password → verifikasi via bcrypt
            $currentValid = Hash::check($request->current_password, $student->custom_password);
        } else {
            // Masih pakai default birth_date (ddmmyy)
            $expected = \Carbon\Carbon::parse($student->birth_date)->format('dmy');
            $currentValid = ($expected === $request->current_password);
        }

        if (! $currentValid) {
            return back()
                ->withErrors(['current_password' => 'Password saat ini salah.'])
                ->withInput();
        }

        $student->update([
            'custom_password' => Hash::make($request->new_password),
        ]);

        FinancialAuditLogger::passwordChanged($student->id, $student->name, $student->nis, $request);

        return redirect()
            ->route('siswa.profil')
            ->with('success_password', 'Password berhasil diperbarui.');
    }
}
