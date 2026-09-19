<?php

namespace App\Http\Controllers\Integration;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * TU-STUDENT-READ-CONTRACT — READ-ONLY student lookup for the Finance
 * (Tabungan) backend. Machine-to-machine only: requests reach this controller
 * ONLY after passing the finance.hmac middleware (invalid HMAC → 401 before
 * any processing). There are NO write methods here by design.
 *
 * Contract rules:
 *   - `student_uuid` is the SOLE cross-system identity. nis/nisn/name/kelas/
 *     rombel are display/lookup metadata ONLY — never identity.
 *   - Only the approved minimal field set is returned (see serialize()).
 *   - Results are always bounded (pagination, per_page ≤ 50) and the list
 *     endpoint refuses to run without at least one search filter, so the
 *     entire student table can never be dumped in one response.
 *   - Soft-deleted students are excluded from list search; the show endpoint
 *     supports explicit historical lookup (include_trashed=1, marked in the
 *     response) so Finance can explain previously-linked students.
 */
class FinanceStudentController extends Controller
{
    /** Hard upper bound on page size — anti-dump. */
    private const MAX_PER_PAGE = 50;

    private const DEFAULT_PER_PAGE = 15;

    /**
     * GET /api/integration/finance/students
     *
     * Query params (at least one search filter required):
     *   student_uuid — exact match
     *   nis          — exact match
     *   nisn         — exact match
     *   name         — partial (LIKE %term%), controlled display search only
     *   status       — exact match against the 7 known lifecycle statuses
     *   page, per_page — pagination (per_page 1..50)
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'student_uuid' => ['nullable', 'uuid'],
            'nis' => ['nullable', 'string', 'max:30'],
            'nisn' => ['nullable', 'string', 'max:20'],
            'name' => ['nullable', 'string', 'min:2', 'max:100'],
            'status' => ['nullable', 'string', 'in:'.implode(',', array_keys(Student::STATUSES))],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ]);

        $hasFilter = filled($validated['student_uuid'] ?? null)
            || filled($validated['nis'] ?? null)
            || filled($validated['nisn'] ?? null)
            || filled($validated['name'] ?? null)
            || filled($validated['status'] ?? null);

        if (! $hasFilter) {
            return response()->json([
                'status' => 'error',
                'message' => 'Minimal satu parameter pencarian wajib diisi (student_uuid, nis, nisn, name, atau status).',
            ], 422);
        }

        $query = Student::query()->with(['kelas:id,nama_kelas', 'rombelAktif.rombel:id,nama_rombel']);

        if (filled($validated['student_uuid'] ?? null)) {
            $query->where('student_uuid', $validated['student_uuid']);
        }
        if (filled($validated['nis'] ?? null)) {
            $query->where('nis', $validated['nis']);
        }
        if (filled($validated['nisn'] ?? null)) {
            $query->where('nisn', $validated['nisn']);
        }
        if (filled($validated['name'] ?? null)) {
            $query->where('name', 'like', '%'.$validated['name'].'%');
        }
        if (filled($validated['status'] ?? null)) {
            $query->where('status', $validated['status']);
        }

        $perPage = min((int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE);

        $paginator = $query->orderBy('name')->orderBy('id')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => [
                'records' => collect($paginator->items())->map(fn (Student $s) => $this->serialize($s))->values(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }

    /**
     * GET /api/integration/finance/students/{student_uuid}
     *
     * Lookup by the SOLE stable identity. 404 when not found (matching
     * Finance's integration error conventions). include_trashed=1 enables
     * explicit historical lookup of a previously-linked student; the response
     * then carries deleted_at (otherwise always null).
     */
    public function show(Request $request, string $student_uuid)
    {
        if (! Str::isUuid($student_uuid)) {
            return response()->json(['status' => 'error', 'message' => 'Siswa tidak ditemukan.'], 404);
        }

        $query = Student::query()->with(['kelas:id,nama_kelas', 'rombelAktif.rombel:id,nama_rombel']);

        if ($request->boolean('include_trashed')) {
            $query->withTrashed();
        }

        $student = $query->where('student_uuid', $student_uuid)->first();

        if (! $student) {
            return response()->json(['status' => 'error', 'message' => 'Siswa tidak ditemukan.'], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->serialize($student),
        ]);
    }

    /**
     * The ONLY approved cross-system representation. student_uuid is identity;
     * everything else is display/lookup metadata. Sensitive fields (passwords,
     * parent credentials, payment, portal, and internal audit data) are never
     * selected or exposed.
     */
    private function serialize(Student $s): array
    {
        return [
            'student_uuid' => $s->student_uuid,
            'nis' => $s->nis,
            'nisn' => $s->nisn,
            'name' => $s->name,
            'kelas' => $s->kelas?->nama_kelas,
            'rombel' => $s->rombelAktif?->rombel?->nama_rombel,
            'status' => $s->status,
            'is_active' => (bool) $s->is_aktif,
            'deleted_at' => $s->deleted_at?->toIso8601String(),
        ];
    }
}
