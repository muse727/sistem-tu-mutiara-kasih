<?php

/**
 * Security Regression — Midtrans single-bill payment authorization & input trust.
 *
 * Locks down behaviors previously verified via read-only probes (Task 8):
 *   1. Cross-student payment initiation on /siswa/tagihan/{bill}/pay → 403.
 *   2. Browser-forged amount/status/student_id/order_id are ignored (server
 *      uses the authoritative persisted bill state).
 *   3. Cross-student receipt/struk → 404 with no detail disclosure.
 *
 * These tests lock in the ACTUAL implementation — they do not mock away the
 * authorization or ownership logic.
 */

use App\Models\PaymentAttempt;
use App\Models\Student;
use App\Models\StudentBill;

function secStudent(): Student {
    return Student::factory()->create();
}

// ── 1. Cross-student single-bill payment initiation → 403 ────────────────────
test('student_cannot_create_payment_token_for_another_students_bill', function () {
    $studentA = secStudent();
    $studentB = secStudent();
    $billB    = StudentBill::factory()->create([
        'student_id' => $studentB->id,
        'status'     => 'UNPAID',
        'amount'     => 600000,
    ]);

    $attemptsBefore = PaymentAttempt::where('student_bill_id', $billB->id)->count();

    $response = $this->actingAs($studentA, 'siswa')
                     ->postJson("/siswa/tagihan/{$billB->id}/pay");

    // Authorization is enforced server-side — before any token creation.
    $response->assertStatus(403);

    // No provider token returned.
    expect($response->json('snap_token'))->toBeNull();

    // State assertions — Student B's bill is completely untouched.
    $billB->refresh();
    expect($billB->status)->toBe('UNPAID')
        ->and((float) $billB->amount)->toBe(600000.0)
        ->and($billB->payment_token)->toBeNull()
        ->and($billB->midtrans_order_id)->toBeNull();

    // No PaymentAttempt was created for Student B's bill by this request.
    expect(PaymentAttempt::where('student_bill_id', $billB->id)->count())->toBe($attemptsBefore);
});

// ── 2. Forged amount/status/student_id/order_id are ignored ─────────────────
test('payment_token_ignores_client_supplied_amount_and_status_fields', function () {
    $student  = secStudent();
    $attacker = secStudent(); // another student the forger tries to impersonate
    $bill     = StudentBill::factory()->create([
        'student_id' => $student->id,
        'status'     => 'UNPAID',
        'amount'     => 600000,
    ]);

    // Malicious browser-controlled fields — must ALL be ignored by the server.
    $this->actingAs($student, 'siswa')->postJson("/siswa/tagihan/{$bill->id}/pay", [
        'amount'       => 1000,
        'gross_amount' => 1000,
        'student_id'   => $attacker->id,
        'status'       => 'PAID',
        'order_id'     => 'ATTACKER-CONTROLLED',
        'payment_method' => 'FREE',
    ]);

    // The authoritative persisted bill state is completely unchanged.
    $bill->refresh();
    expect((float) $bill->amount)->toBe(600000.0)   // NOT the forged 1000
        ->and($bill->status)->toBe('UNPAID')          // NO forged PAID transition
        ->and($bill->student_id)->toBe($student->id)  // NOT the other student
        ->and($bill->payment_method)->not->toBe('FREE');

    // No PaymentAttempt was ever persisted with the forged amount/order.
    expect(PaymentAttempt::where('student_bill_id', $bill->id)
        ->where('order_id', 'ATTACKER-CONTROLLED')->count())->toBe(0);
    expect(PaymentAttempt::where('student_bill_id', $bill->id)
        ->where('gross_amount', 1000)->count())->toBe(0);
});

// ── 3. Cross-student receipt → 404, no detail disclosure ────────────────────
test('student_cannot_view_another_students_receipt', function () {
    $studentA = secStudent();
    $studentB = secStudent();
    $billB    = StudentBill::factory()->create([
        'student_id' => $studentB->id,
        'status'     => 'PAID',
        'amount'     => 600000,
    ]);

    $response = $this->actingAs($studentA, 'siswa')
                     ->get("/siswa/tagihan/{$billB->id}/struk");

    // 404 (not 403/200) — does not confirm the bill exists at all, and renders
    // no receipt body, so no amount/reference/identity can be disclosed.
    $response->assertStatus(404);

    // Student B's paid bill is not mutated by the attempt.
    $billB->refresh();
    expect($billB->status)->toBe('PAID')
        ->and((float) $billB->amount)->toBe(600000.0);
});
