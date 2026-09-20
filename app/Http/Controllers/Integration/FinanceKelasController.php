<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use Illuminate\Http\JsonResponse;

/**
 * TU-STUDENT-READ-CONTRACT — READ-ONLY class list for the Finance (Tabungan)
 * backend class→student picker. Machine-to-machine only: requests reach this
 * controller ONLY after passing the finance.hmac middleware. No write methods
 * by design. Returns only active classes; each entry carries the id Finance
 * passes back as the `kelas_id` filter on the student list endpoint.
 */
class FinanceKelasController extends Controller
{
    public function index(): JsonResponse
    {
        $kelas = Kelas::query()
            ->where('is_aktif', true)
            ->withCount(['students' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('tingkat')
            ->orderBy('nama_kelas')
            ->get(['id', 'nama_kelas', 'tingkat', 'wali_kelas']);

        return response()->json([
            'status' => 'success',
            'data' => [
                'records' => $kelas->map(fn (Kelas $k) => [
                    'kelas_id'    => $k->id,
                    'nama_kelas'  => $k->nama_kelas,
                    'tingkat'     => $k->tingkat,
                    'wali_kelas'  => $k->wali_kelas,
                    'jumlah_siswa' => (int) $k->students_count,
                ])->values(),
            ],
        ]);
    }
}
