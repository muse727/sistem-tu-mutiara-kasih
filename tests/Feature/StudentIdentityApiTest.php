<?php

use App\Models\Student;

/**
 * Phase 09B.1 — Parent API exposes the stable identity.
 *
 * Verifies the parent/mobile API returns student_uuid alongside the existing
 * backward-compatible fields (id, nis, name, class_name) without removing or
 * renaming any of them.
 */

test('parent api login returns student_uuid and preserves legacy fields', function () {
    $student = Student::factory()->create([
        'nis'          => '777000111',
        'status'       => 'active',
        'parent_phone' => '081999888777',
    ]);

    $response = $this->postJson('/api/login', [
        'nis'   => '777000111',
        'phone' => '081999888777',
    ]);

    $response->assertOk();
    $response->assertJsonStructure([
        'token',
        'student' => ['id', 'student_uuid', 'name', 'nis', 'class_name'],
    ]);

    expect($response->json('student.student_uuid'))->toBe($student->student_uuid);
    expect($response->json('student.nis'))->toBe('777000111'); // backward compat
    expect($response->json('student.id'))->toBe($student->id); // internal id still present
});
