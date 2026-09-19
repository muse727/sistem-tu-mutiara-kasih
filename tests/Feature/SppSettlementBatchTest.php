<?php

/**
 * TU-FINANCE-BATCH-02 — SPP settlement batch tests.
 *
 * Covers: period inclusion/exclusion, unpaid exclusion, MULTI grain (one
 * payment / many bills), totals, duplicate-membership blocking, immutability
 * after CLOSED, lifecycle transitions, feature-flag behavior, dispatch payload,
 * payment-method breakdown, and retry. Finance is NOT contacted (Null/fake
 * transport); batch mode is enabled per-test.
 */

use App\Models\IntegrationOutboxEvent;
use App\Models\SppSettlementBatch;
use App\Models\SppSettlementBatchItem;
use App\Models\Student;
use App\Models\StudentBill;
use App\Services\Integration\IntegrationTransport;
use App\Services\Integration\OutboxDispatcher;
use App\Services\Integration\SppSettlementBatchService;
use App\Services\Integration\TransportResult;

function batchBill(array $overrides = []): StudentBill
{
    return StudentBill::factory()->create(array_merge([
        'student_id' => Student::factory()->create(['status' => 'active'])->id,
        'type' => 'SPP', 'status' => 'PAID', 'payment_method' => 'CASH',
        'paid_at' => \Carbon\Carbon::parse('2026-09-15 10:00:00', 'Asia/Jakarta'),
        'amount' => 500000,
    ], $overrides));
}

beforeEach(function () {
    config([
        'integration.enabled' => true,
        'integration.activated_at' => null,
        'integration.spp_batch_enabled' => true,
        'integration.spp_batch_activated_at' => null,
        'integration.spp_batch_period_kind' => 'DAILY',
    ]);
});

function svc(): SppSettlementBatchService
{
    return new SppSettlementBatchService();
}

test('flag disabled → no batch membership, batch table empty', function () {
    config(['integration.spp_batch_enabled' => false]);
    $bill = batchBill();
    svc()->recordEligibleSettlement($bill);
    expect(SppSettlementBatch::count())->toBe(0)
        ->and(SppSettlementBatchItem::count())->toBe(0);
});

test('batch includes correct period and total', function () {
    $b1 = batchBill(['amount' => 500000, 'paid_at' => '2026-09-15 09:00:00']);
    $b2 = batchBill(['amount' => 750000, 'paid_at' => '2026-09-15 18:00:00']);
    svc()->recordEligibleSettlement($b1);
    svc()->recordEligibleSettlement($b2);

    $batch = SppSettlementBatch::sole();
    expect($batch->period_kind)->toBe('DAILY')
        ->and($batch->period_start->toDateString())->toBe('2026-09-15')
        ->and($batch->total_amount)->toBe('1250000.00')
        ->and($batch->bill_count)->toBe(2)
        ->and($batch->payment_count)->toBe(2) // two separate cash receipts
        ->and($batch->status)->toBe('DRAFT');
});

test('excludes unpaid bills', function () {
    $unpaid = batchBill(['status' => 'UNPAID', 'paid_at' => null]);
    svc()->recordEligibleSettlement($unpaid);
    expect(SppSettlementBatch::count())->toBe(0);
});

test('excludes out-of-period bills from a different day batch', function () {
    $dayA = batchBill(['paid_at' => '2026-09-15 10:00:00']);
    $dayB = batchBill(['paid_at' => '2026-09-16 10:00:00']);
    svc()->recordEligibleSettlement($dayA);
    svc()->recordEligibleSettlement($dayB);
    expect(SppSettlementBatch::count())->toBe(2)
        ->and(SppSettlementBatch::pluck('period_start')->map->toDateString()->all())
        ->toEqualCanonicalizing(['2026-09-15', '2026-09-16']);
});

test('respects activation boundary — pre-boundary bills not batched', function () {
    config(['integration.spp_batch_activated_at' => '2026-09-16T00:00:00+07:00']);
    $old = batchBill(['paid_at' => '2026-09-15 10:00:00']); // before boundary
    $new = batchBill(['paid_at' => '2026-09-16 10:00:00']); // after boundary
    svc()->recordEligibleSettlement($old);
    svc()->recordEligibleSettlement($new);
    expect(SppSettlementBatch::count())->toBe(1)
        ->and(SppSettlementBatch::sole()->period_start->toDateString())->toBe('2026-09-16');
});

test('MULTI payment counts as ONE payment with MANY bills', function () {
    $orderId = 'MULTI-1_2_3_4-abc123-1789000000';
    foreach ([1, 2, 3, 4] as $i) {
        $bill = batchBill([
            'amount' => 500000, 'payment_method' => 'MIDTRANS',
            'midtrans_order_id' => $orderId,
        ]);
        svc()->recordEligibleSettlement($bill);
    }
    $batch = SppSettlementBatch::sole();
    expect($batch->bill_count)->toBe(4)
        ->and($batch->payment_count)->toBe(1)
        ->and($batch->total_amount)->toBe('2000000.00');
});

test('duplicate membership blocked — a bill joins exactly one batch', function () {
    $bill = batchBill();
    svc()->recordEligibleSettlement($bill);
    svc()->recordEligibleSettlement($bill); // re-settle attempt
    expect(SppSettlementBatchItem::where('student_bill_id', $bill->id)->count())->toBe(1);
});

test('payment-method breakdown preserved (cash vs bank)', function () {
    svc()->recordEligibleSettlement(batchBill(['amount' => 400000, 'payment_method' => 'CASH']));
    svc()->recordEligibleSettlement(batchBill([
        'amount' => 600000, 'payment_method' => 'MIDTRANS',
        'midtrans_order_id' => 'BILL-9-abc-1',
    ]));
    $batch = SppSettlementBatch::sole();
    $breakdown = collect($batch->breakdown);
    $cash = $breakdown->firstWhere('payment_method', 'CASH');
    $online = $breakdown->firstWhere('payment_method', 'MIDTRANS');
    expect($cash['amount'])->toBe('400000.00')
        ->and($online['amount'])->toBe('600000.00')
        ->and($online['provider'])->toBe('midtrans');
});

test('close freezes snapshot; closed batch is immutable', function () {
    svc()->recordEligibleSettlement(batchBill(['amount' => 500000]));
    $batch = SppSettlementBatch::sole();
    svc()->close($batch);
    $closed = $batch->fresh();
    expect($closed->status)->toBe('CLOSED');

    $closed->total_amount = 1; // attempt to mutate frozen accounting snapshot
    expect(fn () => $closed->save())->toThrow(\LogicException::class);
});

test('lifecycle DRAFT → CLOSED → outbox event built with deterministic key + payload', function () {
    svc()->recordEligibleSettlement(batchBill(['amount' => 500000, 'paid_at' => '2026-09-15 10:00:00']));
    $batch = SppSettlementBatch::sole();
    svc()->close($batch);
    $event = svc()->buildOutboxEvent($batch->fresh());

    expect($event)->not->toBeNull()
        ->and($event->event_type)->toBe('spp.settlement_batch')
        ->and($event->idempotency_key)->toBe('TU:SPP:DAILY:2026-09-15')
        ->and($event->source_record_id)->toBe((string) $batch->batch_id)
        ->and($event->payload['total_amount'])->toBe('500000.00')
        ->and($event->payload['payment_count'])->toBe(1)
        ->and($event->payload['bill_count'])->toBe(1);
});

test('buildOutboxEvent is idempotent (firstOrCreate)', function () {
    svc()->recordEligibleSettlement(batchBill());
    $batch = SppSettlementBatch::sole();
    svc()->close($batch);
    $e1 = svc()->buildOutboxEvent($batch->fresh());
    $e2 = svc()->buildOutboxEvent($batch->fresh());
    expect(IntegrationOutboxEvent::where('event_type', 'spp.settlement_batch')->count())->toBe(1)
        ->and($e1->id)->toBe($e2->id);
});

test('dispatcher marks batch SENT/ACKNOWLEDGED via fake transport; retry preserved', function () {
    svc()->recordEligibleSettlement(batchBill());
    $batch = SppSettlementBatch::sole();
    svc()->close($batch);
    svc()->buildOutboxEvent($batch->fresh());

    // Fake transport: first retryable, then delivered.
    $fake = new class implements IntegrationTransport {
        public int $calls = 0;
        public function deliver(IntegrationOutboxEvent $event): TransportResult
        {
            $this->calls++;
            return $this->calls === 1
                ? TransportResult::retryable('network-error')
                : TransportResult::delivered();
        }
    };
    $dispatcher = new OutboxDispatcher($fake);
    $dispatcher->dispatch(1); // retryable
    expect($batch->fresh()->status)->toBe('FAILED_RETRYABLE');
    $dispatcher->dispatch(1); // delivered
    expect($batch->fresh()->status)->toBe('ACKNOWLEDGED')
        ->and($batch->fresh()->acknowledged_at)->not->toBeNull();
});

test('batch mode enabled → batched bill\'s per-bill spp.payment is NOT dispatched for accounting', function () {
    // The bill IS a batch member, so its trace per-bill event is skipped (batch
    // is the sole accounting posting). F-2: dispatch gate keys off membership.
    $bill = batchBill();
    svc()->recordEligibleSettlement($bill); // makes the bill a batch member
    expect(SppSettlementBatchItem::where('student_bill_id', $bill->id)->exists())->toBeTrue();

    IntegrationOutboxEvent::create([
        'event_id' => (string) \Illuminate\Support\Str::uuid(),
        'event_type' => 'spp.payment', 'event_version' => '1.0', 'contract_version' => '1.0',
        'source_system' => 'TU', 'source_record_id' => (string) $bill->id,
        'idempotency_key' => "TU:spp.payment:{$bill->id}",
        'occurred_at' => now(), 'payload' => [], 'delivery_status' => 'PENDING', 'attempt_count' => 0,
    ]);
    $fake = new class implements IntegrationTransport {
        public int $calls = 0;
        public function deliver(IntegrationOutboxEvent $event): TransportResult
        {
            $this->calls++;
            return TransportResult::delivered();
        }
    };
    (new OutboxDispatcher($fake))->dispatch(10);
    expect($fake->calls)->toBe(0) // batched bill's per-bill event skipped
        ->and(IntegrationOutboxEvent::where('event_type', 'spp.payment')->sole()->delivery_status)->toBe('PENDING');
});

test('batch mode disabled → per-bill spp.payment IS dispatched (unchanged behavior)', function () {
    config(['integration.spp_batch_enabled' => false]);
    $bill = batchBill();
    IntegrationOutboxEvent::create([
        'event_id' => (string) \Illuminate\Support\Str::uuid(),
        'event_type' => 'spp.payment', 'event_version' => '1.0', 'contract_version' => '1.0',
        'source_system' => 'TU', 'source_record_id' => (string) $bill->id,
        'idempotency_key' => "TU:spp.payment:{$bill->id}",
        'occurred_at' => now(), 'payload' => [], 'delivery_status' => 'PENDING', 'attempt_count' => 0,
    ]);
    $fake = new class implements IntegrationTransport {
        public int $calls = 0;
        public function deliver(IntegrationOutboxEvent $event): TransportResult
        {
            $this->calls++;
            return TransportResult::delivered();
        }
    };
    (new OutboxDispatcher($fake))->dispatch(10);
    expect($fake->calls)->toBe(1);
});

// ── F-1 (BATCH-03-FIX) — CLOSED batch race ──────────────────────────────────

test('F-1: late settlement after close routes to a new overflow batch, never mutates closed batch', function () {
    $day = '2026-09-15';
    // First payment → closes into batch #1.
    $b1 = batchBill(['amount' => 500000, 'paid_at' => "{$day} 09:00:00"]);
    svc()->recordEligibleSettlement($b1);
    $batch1 = SppSettlementBatch::sole();
    svc()->close($batch1);
    $batch1 = $batch1->fresh();
    expect($batch1->status)->toBe('CLOSED');
    $frozenTotal = $batch1->total_amount;
    $frozenBillCount = $batch1->bill_count;

    // Late payment in the SAME period arrives after close.
    $b2 = batchBill(['amount' => 300000, 'paid_at' => "{$day} 18:00:00"]);
    svc()->recordEligibleSettlement($b2);

    // The closed batch must be untouched (frozen snapshot).
    $batch1After = SppSettlementBatch::find($batch1->id);
    expect($batch1After->status)->toBe('CLOSED')
        ->and($batch1After->total_amount)->toBe($frozenTotal)
        ->and($batch1After->bill_count)->toBe($frozenBillCount);

    // The late settlement lands in a NEW overflow batch for the same period.
    expect(SppSettlementBatch::count())->toBe(2);
    $overflow = SppSettlementBatch::where('id', '!=', $batch1->id)->sole();
    expect($overflow->status)->toBe('DRAFT')
        ->and($overflow->period_start->toDateString())->toBe($day)
        ->and($overflow->total_amount)->toBe('300000.00')
        ->and($overflow->bill_count)->toBe(1)
        ->and($overflow->idempotency_key)->not->toBe($batch1->idempotency_key);

    // Each bill is in exactly one batch.
    expect(SppSettlementBatchItem::where('student_bill_id', $b1->id)->sole()->spp_settlement_batch_id)->toBe($batch1->id);
    expect(SppSettlementBatchItem::where('student_bill_id', $b2->id)->sole()->spp_settlement_batch_id)->toBe($overflow->id);
});

test('F-1: closed batch snapshot is never recomputed by a late settlement', function () {
    $day = '2026-09-16';
    $b1 = batchBill(['amount' => 100000, 'paid_at' => "{$day} 08:00:00"]);
    svc()->recordEligibleSettlement($b1);
    $batch1 = SppSettlementBatch::sole();
    svc()->close($batch1);
    svc()->buildOutboxEvent($batch1->fresh()); // payload frozen at close

    $b2 = batchBill(['amount' => 200000, 'paid_at' => "{$day} 20:00:00"]);
    svc()->recordEligibleSettlement($b2);

    // Batch #1 payload must still reflect ONLY its original members.
    $event = IntegrationOutboxEvent::where('event_type', 'spp.settlement_batch')
        ->where('source_record_id', (string) $batch1->batch_id)->sole();
    expect($event->payload['total_amount'])->toBe('100000.00')
        ->and($event->payload['bill_count'])->toBe(1);
});

test('F-1: multiple late settlements reuse the single open overflow batch', function () {
    $day = '2026-09-17';
    $b1 = batchBill(['amount' => 100000, 'paid_at' => "{$day} 08:00:00"]);
    svc()->recordEligibleSettlement($b1);
    svc()->close(SppSettlementBatch::sole());

    $b2 = batchBill(['amount' => 200000, 'paid_at' => "{$day} 12:00:00"]);
    $b3 = batchBill(['amount' => 300000, 'paid_at' => "{$day} 13:00:00"]);
    svc()->recordEligibleSettlement($b2);
    svc()->recordEligibleSettlement($b3);

    // Exactly one overflow batch holds both late settlements.
    expect(SppSettlementBatch::count())->toBe(2);
    $overflow = SppSettlementBatch::where('status', 'DRAFT')->sole();
    expect($overflow->total_amount)->toBe('500000.00')
        ->and($overflow->bill_count)->toBe(2);
});

test('F-1: at most one DRAFT (open) batch exists per period', function () {
    $day = '2026-09-18';
    // Two bills same period while open → one DRAFT batch.
    svc()->recordEligibleSettlement(batchBill(['paid_at' => "{$day} 09:00:00"]));
    svc()->recordEligibleSettlement(batchBill(['paid_at' => "{$day} 10:00:00"]));
    expect(SppSettlementBatch::where('period_start', $day)->where('status', 'DRAFT')->count())->toBe(1);
});

// ── F-2 (BATCH-03-FIX) — flag-flip gap ──────────────────────────────────────

function mkPerBillEvent(\App\Models\StudentBill $bill): void
{
    IntegrationOutboxEvent::create([
        'event_id' => (string) \Illuminate\Support\Str::uuid(),
        'event_type' => 'spp.payment', 'event_version' => '1.0', 'contract_version' => '1.0',
        'source_system' => 'TU', 'source_record_id' => (string) $bill->id,
        'idempotency_key' => "TU:spp.payment:{$bill->id}",
        'occurred_at' => now(), 'payload' => [], 'delivery_status' => 'PENDING', 'attempt_count' => 0,
    ]);
}

function countingTransport(): object
{
    return new class implements IntegrationTransport {
        public int $calls = 0;
        public function deliver(IntegrationOutboxEvent $event): TransportResult
        {
            $this->calls++;
            return TransportResult::delivered();
        }
    };
}

test('F-2: ON→OFF flip — bill batched while ON is never re-dispatched per-bill (no double-post)', function () {
    $bill = batchBill();
    svc()->recordEligibleSettlement($bill);   // batched while ON
    mkPerBillEvent($bill);

    config(['integration.spp_batch_enabled' => false]); // flip OFF
    $t = countingTransport();
    (new OutboxDispatcher($t))->dispatch(10);

    // Batched bill's per-bill event must NOT be dispatched (the batch posts it).
    expect($t->calls)->toBe(0);
});

test('F-2: OFF→ON flip — bill settled while OFF (not batched) is still dispatched per-bill (no gap)', function () {
    config(['integration.spp_batch_enabled' => false]);
    $bill = batchBill();
    svc()->recordEligibleSettlement($bill);   // no-op while OFF → NOT batched
    expect(SppSettlementBatchItem::where('student_bill_id', $bill->id)->exists())->toBeFalse();
    mkPerBillEvent($bill);

    config(['integration.spp_batch_enabled' => true]); // flip ON
    $t = countingTransport();
    (new OutboxDispatcher($t))->dispatch(10);

    // Un-batched bill's per-bill event still dispatches → no accounting gap.
    expect($t->calls)->toBe(1);
});

test('F-2: mixed — batched bill skipped while un-batched bill dispatched in same run', function () {
    $batched = batchBill();
    svc()->recordEligibleSettlement($batched);         // batch member
    mkPerBillEvent($batched);

    config(['integration.spp_batch_enabled' => false]);
    $legacy = batchBill();
    svc()->recordEligibleSettlement($legacy);          // no-op → not a member
    mkPerBillEvent($legacy);

    $t = countingTransport();
    (new OutboxDispatcher($t))->dispatch(10);
    expect($t->calls)->toBe(1); // only the un-batched (legacy) bill dispatches
});

// ── TU-FINANCE-BATCH-04 — reconciliation snapshot (READ-ONLY) ───────────────

function snap(\App\Models\SppSettlementBatch $b): array
{
    return (new \App\Services\Integration\SppSettlementBatchReconciliationSnapshot())->build($b);
}

test('snapshot: exact total match + traceability references', function () {
    $b1 = batchBill(['amount' => 500000, 'paid_at' => '2026-09-15 09:00:00']);
    svc()->recordEligibleSettlement($b1);
    $batch = SppSettlementBatch::sole();
    $s = snap($batch);

    expect($s['total_amount'])->toBe('500000.00')
        ->and($s['live_total_amount'])->toBe('500000.00')
        ->and($s['bill_count'])->toBe(1)
        ->and($s['live_bill_count'])->toBe(1)
        ->and($s['payment_count'])->toBe(1)
        ->and($s['idempotency_key'])->toBe($batch->idempotency_key)
        ->and($s['batch_id'])->toBe((string) $batch->batch_id)
        ->and($s['members'][0]['student_bill_id'])->toBe($b1->id);
});

test('snapshot: MULTI one payment / many bills does not inflate payment_count', function () {
    $orderId = 'MULTI-1_2_3_4-abc-1789000000';
    foreach ([1,2,3,4] as $i) {
        svc()->recordEligibleSettlement(batchBill([
            'amount' => 500000, 'payment_method' => 'MIDTRANS', 'midtrans_order_id' => $orderId,
        ]));
    }
    $s = snap(SppSettlementBatch::sole());
    expect($s['payment_count'])->toBe(1)         // ONE operational payment
        ->and($s['live_payment_count'])->toBe(1)
        ->and($s['bill_count'])->toBe(4)         // many bills
        ->and($s['live_bill_count'])->toBe(4)
        ->and($s['total_amount'])->toBe('2000000.00');
});

test('snapshot: 12-month MULTI aggregates correctly', function () {
    $orderId = 'MULTI-12-xyz-1789000001';
    foreach (range(1,12) as $i) {
        svc()->recordEligibleSettlement(batchBill([
            'amount' => 500000, 'payment_method' => 'MIDTRANS', 'midtrans_order_id' => $orderId,
        ]));
    }
    $s = snap(SppSettlementBatch::sole());
    expect($s['payment_count'])->toBe(1)
        ->and($s['bill_count'])->toBe(12)
        ->and($s['total_amount'])->toBe('6000000.00');
});

test('snapshot: payment-method breakdown buckets present and sum to total', function () {
    svc()->recordEligibleSettlement(batchBill(['amount' => 400000, 'payment_method' => 'CASH']));
    svc()->recordEligibleSettlement(batchBill([
        'amount' => 600000, 'payment_method' => 'MIDTRANS', 'midtrans_order_id' => 'BILL-9-abc-1',
    ]));
    $s = snap(SppSettlementBatch::sole());
    $sum = array_reduce($s['breakdown'], fn ($c, $b) => bcadd($c, $b['amount'], 2), '0.00');
    expect($sum)->toBe('1000000.00')
        ->and($s['total_amount'])->toBe('1000000.00')
        ->and(collect($s['breakdown'])->pluck('payment_method')->all())->toEqualCanonicalizing(['CASH','MIDTRANS']);
});

test('snapshot: CLOSED batch carries frozen snapshot + status', function () {
    svc()->recordEligibleSettlement(batchBill(['amount' => 700000]));
    $batch = SppSettlementBatch::sole();
    svc()->close($batch);
    $s = snap($batch->fresh());
    expect($s['status'])->toBe('CLOSED')
        ->and($s['total_amount'])->toBe('700000.00');
});

test('snapshot: overflow batch is a separate valid batch', function () {
    $day = '2026-09-20';
    svc()->recordEligibleSettlement(batchBill(['amount' => 100000000, 'paid_at' => "{$day} 08:00:00"]));
    svc()->close(SppSettlementBatch::sole());
    svc()->recordEligibleSettlement(batchBill(['amount' => 5000000, 'paid_at' => "{$day} 20:00:00"]));

    expect(SppSettlementBatch::count())->toBe(2);
    $main = SppSettlementBatch::where('status', 'CLOSED')->sole();
    $overflow = SppSettlementBatch::where('status', 'DRAFT')->sole();
    $ms = snap($main);
    $os = snap($overflow);
    expect($ms['total_amount'])->toBe('100000000.00')
        ->and($os['total_amount'])->toBe('5000000.00')
        ->and($os['period_start'])->toBe($day)
        ->and($os['idempotency_key'])->not->toBe($ms['idempotency_key']);
});

test('snapshot: build is read-only (no state change on repeat)', function () {
    svc()->recordEligibleSettlement(batchBill(['amount' => 500000]));
    $batch = SppSettlementBatch::sole();
    $before = $batch->fresh()->toArray();
    $s1 = snap($batch);
    $s2 = snap($batch->fresh());
    expect($s1)->toBe($s2)
        ->and($batch->fresh()->toArray())->toBe($before); // nothing mutated
});
