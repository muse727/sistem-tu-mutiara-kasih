<?php

namespace App\Http\Controllers;

use App\Models\SppSettlementBatch;
use App\Services\Integration\SppSettlementBatchService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * TU-DAILY-CLOSE-MAIN-UI — Daily Close SPP di dalam aplikasi utama TU.
 *
 * Lapisan operasional TIPIS di atas siklus batch settlement SPP yang SUDAH
 * TERSERTIFIKASI. Menggunakan persis logika bisnis yang sama dengan command
 * CLI `integration:spp-close-batches` — tidak menduplikasi aturan close, tidak
 * memutasi model batch secara langsung, dan tidak melewati jaminan locking /
 * closed-batch / overflow / idempotency / outbox milik service.
 *
 *   CLI path : SppSettlementBatchService::close() → buildOutboxEvent()
 *   Web path : SppSettlementBatchService::close() → buildOutboxEvent()
 *
 * Otorisasi memakai RBAC role bawaan aplikasi (users.role) via middleware
 * `role:` — SAMA seperti modul /bills:
 *   - akses halaman (index): admin, tu, staf, kepala_sekolah  (monitoring)
 *   - aksi close (close)   : admin, tu                       (operasional finansial)
 *
 * READ-ONLY kecuali satu aksi close eksplisit. Halaman tidak pernah
 * mengarang status SENT / ACKNOWLEDGED — itu tetap status lifecycle downstream.
 */
class SppDailyCloseController extends Controller
{
    public function __construct(
        protected SppSettlementBatchService $service,
    ) {}

    /**
     * GET /spp-daily-close
     * Tampilkan batch settlement SPP yang saat ini eligible untuk ditutup.
     */
    public function index()
    {
        return view('spp-daily-close.index', [
            'batch' => $this->eligibleBatch(),
            'canClose' => $this->canClose(),
        ]);
    }

    /**
     * POST /spp-daily-close
     * Tutup batch melalui service tersertifikasi — identik dengan command CLI.
     */
    public function close()
    {
        // Pertahanan tambahan di atas middleware role:admin,tu.
        abort_unless($this->canClose(), 403, 'Akses ditolak. Anda tidak memiliki izin untuk melakukan tindakan ini.');

        $batch = SppSettlementBatch::query()
            ->whereIn('status', [
                SppSettlementBatch::STATUS_DRAFT,
                SppSettlementBatch::STATUS_READY_TO_CLOSE,
            ])
            ->orderBy('period_start')
            ->orderBy('id')
            ->first();

        if (! $batch) {
            return redirect()->route('spp-daily-close.index')
                ->with('warning', 'Tidak ada batch settlement SPP yang memenuhi syarat untuk ditutup.');
        }

        try {
            $closed = $this->service->close($batch);

            // Identik dengan CLI: batch kosong tetap DRAFT.
            if ($closed->status === SppSettlementBatch::STATUS_CLOSED) {
                $this->service->buildOutboxEvent($closed);

                return redirect()->route('spp-daily-close.index')->with('success', sprintf(
                    'Daily Close berhasil. Batch periode %s ditutup (CLOSED) · Total Rp %s · %d pembayaran · %d tagihan.',
                    $closed->period_start?->toDateString(),
                    number_format((float) $closed->total_amount, 0, ',', '.'),
                    (int) $closed->payment_count,
                    (int) $closed->bill_count
                ));
            }

            return redirect()->route('spp-daily-close.index')
                ->with('warning', 'Batch masih kosong (tidak ada tagihan terselesaikan) sehingga tetap DRAFT.');
        } catch (\Throwable $e) {
            // Jangan pernah menelan exception, jangan pernah membocorkan stack trace.
            Log::error('SppDailyClose: close failed', [
                'batch_id' => $batch->batch_id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('spp-daily-close.index')
                ->with('error', 'Terjadi kesalahan saat menutup batch. Silakan coba lagi atau hubungi administrator.');
        }
    }

    // ── Internal ─────────────────────────────────────────────────────────────

    /** Aksi close dibatasi ke role operasional finansial. */
    protected function canClose(): bool
    {
        return in_array(Auth::user()?->role, ['admin', 'tu'], true);
    }

    /**
     * Batch open (DRAFT / READY_TO_CLOSE) yang eligible, direcompute agar angka
     * yang direview operator mutakhir. Recompute aman pada batch DRAFT/READY_TO_CLOSE
     * (guard immutability model hanya membekukan batch CLOSED+). Mengembalikan
     * array presentasi saja — tanpa kalkulasi bisnis baru.
     */
    protected function eligibleBatch(): ?array
    {
        $batch = SppSettlementBatch::query()
            ->whereIn('status', [
                SppSettlementBatch::STATUS_DRAFT,
                SppSettlementBatch::STATUS_READY_TO_CLOSE,
            ])
            ->orderBy('period_start')
            ->orderBy('id')
            ->first();

        if (! $batch) {
            return null;
        }

        $this->service->recompute($batch);
        $batch->refresh();

        return [
            'batch_id' => $batch->batch_id,
            'period_kind' => $batch->period_kind,
            'period_start' => $batch->period_start?->toDateString(),
            'period_end' => $batch->period_end?->toDateString(),
            'total_amount' => $batch->total_amount,
            'payment_count' => (int) $batch->payment_count,
            'bill_count' => (int) $batch->bill_count,
            'status' => $batch->status,
            'breakdown' => $batch->breakdown ?? [],
        ];
    }
}
