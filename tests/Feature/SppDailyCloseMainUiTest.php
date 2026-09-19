<?php

/**
 * TU-DAILY-CLOSE-MAIN-UI — Daily Close SPP di dalam aplikasi utama TU.
 *
 * Memverifikasi lapisan web TIPIS memakai jalur close backend yang SUDAH
 * TERSERTIFIKASI: otorisasi role (middleware `role:` — sama seperti /bills),
 * tampilan eligibility, empty state, close terkonfirmasi memanggil
 * SppSettlementBatchService::close(), hasil CLOSED, immutability, overflow,
 * idempotency, outbox v1.0 tanpa fee, dan konvergensi dengan command CLI.
 * Finance TIDAK dihubungi; batch mode diaktifkan per-test.
 */

use App\Models\IntegrationOutboxEvent;
use App\Models\SppSettlementBatch;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\User;
use App\Services\Integration\SppSettlementBatchService;

function mdcUser(string $role): User
{
    return User::factory()->create(['role' => $role]);
}

function mdcBill(array $overrides = []): StudentBill
{
    return StudentBill::factory()->create(array_merge([
        'student_id' => Student::factory()->create(['status' => 'active'])->id,
        'type' => 'SPP', 'status' => 'PAID', 'payment_method' => 'CASH',
        'paid_at' => \Carbon\Carbon::parse('2026-09-15 10:00:00', 'Asia/Jakarta'),
        'amount' => 500000,
    ], $overrides));
}

function mdcSettleAll(): void
{
    foreach (StudentBill::all() as $b) {
        app(SppSettlementBatchService::class)->recordEligibleSettlement($b);
    }
}

beforeEach(function () {
    config([
        'integration.enabled' => true,
        'integration.spp_batch_enabled' => true,
        'integration.spp_batch_activated_at' => null,
        'integration.spp_batch_period_kind' => 'DAILY',
    ]);
});

// ── AUTHORIZATION (role-based RBAC, middleware `role:`) ──────────────────────

test('guest diarahkan ke login', function () {
    $this->get(route('spp-daily-close.index'))->assertRedirect();
});

test('admin, tu, staf, kepala_sekolah dapat MELIHAT halaman', function (string $role) {
    $this->actingAs(mdcUser($role))
        ->get(route('spp-daily-close.index'))
        ->assertOk();
})->with(['admin', 'tu', 'staf', 'kepala_sekolah']);

test('admin dan tu dapat CLOSE', function (string $role) {
    $this->actingAs(mdcUser($role));
    mdcBill();
    mdcSettleAll();

    $this->post(route('spp-daily-close.close'))->assertRedirect(route('spp-daily-close.index'));

    expect(SppSettlementBatch::sole()->fresh()->status)->toBe('CLOSED');
})->with(['admin', 'tu']);

test('staf dan kepala_sekolah TIDAK dapat close (403), batch tidak berubah', function (string $role) {
    $this->actingAs(mdcUser($role));
    mdcBill();
    mdcSettleAll();

    $this->post(route('spp-daily-close.close'))->assertForbidden();

    expect(SppSettlementBatch::sole()->fresh()->status)->toBe('DRAFT');
})->with(['staf', 'kepala_sekolah']);

// ── ELIGIBILITY / EMPTY STATE ────────────────────────────────────────────────

test('tanpa batch eligible → empty state', function () {
    $this->actingAs(mdcUser('tu'))
        ->get(route('spp-daily-close.index'))
        ->assertOk()
        ->assertSee('Tidak ada batch yang memenuhi syarat');
});

test('batch eligible tampil dengan angka live dan status', function () {
    $this->actingAs(mdcUser('tu'));
    mdcBill();
    mdcBill();
    mdcSettleAll();

    $this->get(route('spp-daily-close.index'))
        ->assertOk()
        ->assertSee('DRAFT')
        ->assertSee('Tutup Batch')
        ->assertSee('1.000.000'); // 2 x 500.000, format id-ID
});

// ── CLOSE ────────────────────────────────────────────────────────────────────

test('close memanggil service tersertifikasi → CLOSED + outbox v1.0 (tanpa fee)', function () {
    $this->actingAs(mdcUser('tu'));
    mdcBill();
    mdcSettleAll();

    $this->post(route('spp-daily-close.close'))
        ->assertRedirect(route('spp-daily-close.index'))
        ->assertSessionHas('success');

    $batch = SppSettlementBatch::sole()->fresh();
    expect($batch->status)->toBe('CLOSED');

    $event = IntegrationOutboxEvent::where('event_type', 'spp.settlement_batch')->sole();
    expect($event->payload)->toHaveKeys(['total_amount', 'payment_count', 'bill_count', 'breakdown'])
        ->and($event->payload)->not->toHaveKeys(['fee_amount', 'net_amount', 'fee_payer', 'customer_surcharge']);
});

test('close berulang tidak membuka/membatch ulang (idempotent)', function () {
    $this->actingAs(mdcUser('tu'));
    mdcBill();
    mdcSettleAll();

    $this->post(route('spp-daily-close.close'));
    $batch = SppSettlementBatch::sole()->fresh();
    expect($batch->status)->toBe('CLOSED');
    $snapshot = $batch->total_amount;

    // POST kedua: tidak ada batch eligible → redirect warning, tidak ada mutasi.
    $this->post(route('spp-daily-close.close'))->assertRedirect();

    expect(SppSettlementBatch::count())->toBe(1)
        ->and(SppSettlementBatch::sole()->fresh()->total_amount)->toBe($snapshot)
        ->and(IntegrationOutboxEvent::where('event_type', 'spp.settlement_batch')->count())->toBe(1);
});

// ── SAFETY ───────────────────────────────────────────────────────────────────

test('settlement terlambat setelah close masuk overflow (perilaku tersertifikasi)', function () {
    $this->actingAs(mdcUser('tu'));
    mdcBill(['amount' => 500000]);
    mdcSettleAll();

    $this->post(route('spp-daily-close.close'));
    $batch1 = SppSettlementBatch::orderBy('id')->first()->fresh();
    expect($batch1->status)->toBe('CLOSED');

    // Pembayaran terlambat pada periode SAMA → batch overflow, batch tertutup tidak berubah.
    mdcBill(['amount' => 300000]);
    app(SppSettlementBatchService::class)->recordEligibleSettlement(StudentBill::latest('id')->first());

    expect($batch1->fresh()->status)->toBe('CLOSED')
        ->and($batch1->fresh()->total_amount)->toBe('500000.00');

    $overflow = SppSettlementBatch::where('id', '!=', $batch1->id)->sole();
    expect($overflow->status)->toBe('DRAFT')
        ->and($overflow->total_amount)->toBe('300000.00')
        ->and($overflow->idempotency_key)->not->toBe($batch1->idempotency_key);
});

test('batch tertutup immutable via model guard', function () {
    $this->actingAs(mdcUser('tu'));
    mdcBill();
    mdcSettleAll();
    $this->post(route('spp-daily-close.close'));

    $closed = SppSettlementBatch::sole()->fresh();
    $closed->total_amount = 1;
    expect(fn () => $closed->save())->toThrow(\LogicException::class);
});

// ── CLI CONVERGENCE / REGRESSION ─────────────────────────────────────────────

test('command CLI tetap bekerja dan konvergen pada service yang sama', function () {
    mdcBill();
    mdcSettleAll();

    $this->artisan('integration:spp-close-batches')->assertSuccessful();

    expect(SppSettlementBatch::sole()->fresh()->status)->toBe('CLOSED');
});

test('batch kosong tetap DRAFT dan tidak ditutup oleh UI', function () {
    $this->actingAs(mdcUser('tu'));
    SppSettlementBatch::create([
        'batch_id' => (string) \Illuminate\Support\Str::uuid(),
        'idempotency_key' => SppSettlementBatch::idempotencyKey('DAILY', '2026-09-15'),
        'source_system' => 'TU', 'category' => 'SPP', 'period_kind' => 'DAILY',
        'period_start' => '2026-09-15', 'period_end' => '2026-09-15',
        'status' => 'DRAFT', 'occurred_at' => now(),
    ]);

    $this->post(route('spp-daily-close.close'))->assertRedirect();

    expect(SppSettlementBatch::sole()->fresh()->status)->toBe('DRAFT');
});
