<?php

/**
 * Student Chat Page (GET /siswa/chat) — Route + Render Tests
 *
 * The chat page is a thin UI shell; message behavior lives in the existing
 * chat.messages / chat.send / chat.unread endpoints (unchanged).
 */

use App\Models\Student;

test('authenticated student can view the dedicated chat page', function () {
    $student = Student::factory()->create();

    $this->actingAs($student, 'siswa')
         ->get(route('siswa.chat'))
         ->assertStatus(200)
         ->assertSee('Hubungi TU');
});

test('chat page reuses the shared chat widget partial (single implementation)', function () {
    $student = Student::factory()->create();

    $res = $this->actingAs($student, 'siswa')->get(route('siswa.chat'));
    $res->assertStatus(200);
    // The shared chatWidget() definition is present exactly once on the page.
    $this->assertSame(1, substr_count($res->getContent(), 'function chatWidget()'));
});

test('unauthenticated request to chat page redirects to siswa login', function () {
    $this->get(route('siswa.chat'))
         ->assertRedirect(route('siswa.login'));
});
