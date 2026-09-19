<?php

/**
 * TU-STUDENT-READ-CONTRACT — Finance-facing READ-ONLY student endpoint tests.
 *
 * Covers the certified TU-FINANCE HMAC scheme (valid/invalid/expired/replay/
 * unknown-key), student_uuid identity, bounded search, approved field set,
 * sensitive-field exclusion, and soft-delete behavior. Signs test requests
 * with the same canonical spec as docs/TU-FINANCE-HMAC-SPEC.md.
 */

use App\Models\Kelas;
use App\Models\Student;
use App\Services\Integration\HmacRequestVerifier;

const FS_SECRET = 'test-finance-inbound-secret';
const FS_KEY_ID = 'tu-local';
const FS_PATH = '/api/integration/finance/students';

beforeEach(function () {
    config(['integration.finance_hmac_keys' => [FS_KEY_ID => FS_SECRET]]);
});

/**
 * Sign a request with the exact certified canonical string and perform it.
 * GET requests carry an empty body — its SHA-256 is the empty-string digest.
 */
function fsGet(string $pathWithQuery, ?int $timestamp = null, ?string $keyId = FS_KEY_ID, ?string $signature = null)
{
    $timestamp = $timestamp ?? time();
    $path = parse_url($pathWithQuery, PHP_URL_PATH) ?: $pathWithQuery;

    if ($signature === null) {
        $canonical = $timestamp."\nGET\n".$path."\n".hash('sha256', '');
        $signature = base64_encode(hash_hmac('sha256', $canonical, FS_SECRET, true));
    }

    // Custom headers must be passed as HTTP_* server vars on call().
    // HTTP_ACCEPT forces JSON semantics (422 validation, not 302 redirect).
    $server = [
        'HTTP_X_TU_KEY_ID' => $keyId ?? '',
        'HTTP_X_TU_TIMESTAMP' => (string) $timestamp,
        'HTTP_X_TU_SIGNATURE' => $signature,
        'HTTP_ACCEPT' => 'application/json',
    ];

    // Send the query string the same way it is canonicalized (path + query),
    // so the server sees exactly the bytes we signed for.
    $parts = parse_url($pathWithQuery);
    $query = [];
    parse_str($parts['query'] ?? '', $query);

    return test()->call('GET', $path, $query, [], [], $server, null);
}

function fsStudent(array $attrs = []): Student
{
    return Student::factory()->create($attrs);
}

// ─── AUTH ────────────────────────────────────────────────────────────────────

test('valid HMAC request is accepted', function () {
    $student = fsStudent();

    fsGet(FS_PATH.'?student_uuid='.$student->student_uuid)
        ->assertOk()
        ->assertJsonPath('status', 'success');
});

test('request without any HMAC headers is rejected', function () {
    test()->getJson(FS_PATH.'?name=ab')
        ->assertUnauthorized()
        ->assertJsonPath('result', 'ERROR')
        ->assertJsonPath('code', HmacRequestVerifier::ERR_INVALID_SIGNATURE);
});

test('invalid signature is rejected', function () {
    fsGet(FS_PATH.'?name=ab', signature: base64_encode('forged'))
        ->assertUnauthorized()
        ->assertJsonPath('code', HmacRequestVerifier::ERR_INVALID_SIGNATURE);
});

test('signature computed over the wrong path is rejected', function () {
    $ts = time();
    $canonical = $ts."\nGET\n".FS_PATH."/wrong\n".hash('sha256', '');
    $forged = base64_encode(hash_hmac('sha256', $canonical, FS_SECRET, true));

    fsGet(FS_PATH.'?name=ab', signature: $forged)
        ->assertUnauthorized()
        ->assertJsonPath('code', HmacRequestVerifier::ERR_INVALID_SIGNATURE);
});

test('expired timestamp is rejected', function () {
    fsGet(FS_PATH.'?name=ab', timestamp: time() - 400)
        ->assertUnauthorized()
        ->assertJsonPath('code', HmacRequestVerifier::ERR_INVALID_TIMESTAMP);
});

test('far-future timestamp is rejected', function () {
    fsGet(FS_PATH.'?name=ab', timestamp: time() + 400)
        ->assertUnauthorized()
        ->assertJsonPath('code', HmacRequestVerifier::ERR_INVALID_TIMESTAMP);
});

test('non-numeric timestamp is rejected', function () {
    test()->withHeaders([
        'X-TU-Key-Id' => FS_KEY_ID,
        'X-TU-Timestamp' => 'not-a-number',
        'X-TU-Signature' => base64_encode('x'),
    ])->getJson(FS_PATH.'?name=ab')
        ->assertUnauthorized()
        ->assertJsonPath('code', HmacRequestVerifier::ERR_INVALID_TIMESTAMP);
});

test('unknown key id is rejected', function () {
    $ts = time();
    $canonical = $ts."\nGET\n".FS_PATH."\n".hash('sha256', '');
    $sig = base64_encode(hash_hmac('sha256', $canonical, FS_SECRET, true));

    fsGet(FS_PATH.'?name=ab', keyId: 'unknown-key', signature: $sig)
        ->assertUnauthorized()
        ->assertJsonPath('code', HmacRequestVerifier::ERR_UNKNOWN_KEY);
});

test('in-window replay of an identical request is accepted (read-only, timestamp-bound)', function () {
    $student = fsStudent();
    $url = FS_PATH.'?student_uuid='.$student->student_uuid;

    fsGet($url)->assertOk();
    fsGet($url)->assertOk(); // same signed request replayed in-window
});

test('unconfigured verifier secret rejects the request', function () {
    config(['integration.finance_hmac_keys' => []]);

    fsGet(FS_PATH.'?name=ab')
        ->assertUnauthorized()
        ->assertJsonPath('code', HmacRequestVerifier::ERR_UNKNOWN_KEY);
});

// ─── IDENTITY ────────────────────────────────────────────────────────────────

test('lookup by student_uuid returns the student with the exact uuid', function () {
    $student = fsStudent(['nis' => 'ID-LOOKUP-1']);

    $response = fsGet(FS_PATH.'/'.$student->student_uuid)->assertOk();

    expect($response->json('data.student_uuid'))->toBe($student->student_uuid);
});

test('nonexistent uuid returns 404', function () {
    fsGet(FS_PATH.'/'.\Illuminate\Support\Str::uuid())
        ->assertNotFound()
        ->assertJsonPath('status', 'error');
});

test('malformed uuid returns 404 (not 500)', function () {
    fsGet(FS_PATH.'/not-a-uuid')->assertNotFound();
});

test('nis search matches but uuid remains the returned identity', function () {
    $student = fsStudent(['nis' => 'NIS-EXACT-77']);
    $other = fsStudent(['nis' => 'NIS-EXACT-78']);

    $response = fsGet(FS_PATH.'?nis=NIS-EXACT-77')->assertOk();

    expect($response->json('data.records'))->toHaveCount(1)
        ->and($response->json('data.records.0.student_uuid'))->toBe($student->student_uuid)
        ->and($response->json('data.records.0.student_uuid'))->not->toBe($other->student_uuid);
});

test('nisn search matches but uuid remains the returned identity', function () {
    $student = fsStudent(['nisn' => '9988776655']);

    $response = fsGet(FS_PATH.'?nisn=9988776655')->assertOk();

    expect($response->json('data.records'))->toHaveCount(1)
        ->and($response->json('data.records.0.student_uuid'))->toBe($student->student_uuid);
});

// ─── SEARCH ──────────────────────────────────────────────────────────────────

test('list search requires at least one filter (no unbounded dump)', function () {
    fsGet(FS_PATH)->assertUnprocessable();
});

test('name search is partial and returns only matches', function () {
    fsStudent(['name' => 'Zulaikha Ramadhani']);
    fsStudent(['name' => 'Bambang Pamungkas']);

    $response = fsGet(FS_PATH.'?name=Zulaikha')->assertOk();

    expect($response->json('data.records'))->toHaveCount(1)
        ->and($response->json('data.records.0.name'))->toBe('Zulaikha Ramadhani');
});

test('status filter returns only matching statuses', function () {
    fsStudent(['status' => 'active']);
    fsStudent(['status' => 'graduated']);

    $response = fsGet(FS_PATH.'?status=graduated')->assertOk();

    foreach ($response->json('data.records') as $record) {
        expect($record['status'])->toBe('graduated');
    }
});

test('invalid status value is rejected with 422', function () {
    fsGet(FS_PATH.'?status=not-a-status')->assertUnprocessable();
});

test('results are paginated with bounds metadata', function () {
    Student::factory()->count(3)->create(['status' => 'active']);

    $response = fsGet(FS_PATH.'?status=active&per_page=2&page=1')->assertOk();

    expect($response->json('data.records'))->toHaveCount(2)
        ->and($response->json('data.pagination.per_page'))->toBe(2)
        ->and($response->json('data.pagination.total'))->toBe(3)
        ->and($response->json('data.pagination.last_page'))->toBe(2);
});

test('per_page above the hard cap is rejected (anti-dump bound)', function () {
    fsGet(FS_PATH.'?status=active&per_page=5000')->assertUnprocessable();
});

// ─── DATA ────────────────────────────────────────────────────────────────────

test('only approved fields are returned and sensitive fields are never exposed', function () {
    $student = fsStudent();

    $record = fsGet(FS_PATH.'/'.$student->student_uuid)->assertOk()->json('data');

    expect(array_keys($record))->toEqualCanonicalizing([
        'student_uuid', 'nis', 'nisn', 'name', 'kelas', 'rombel', 'status', 'is_active', 'deleted_at',
    ]);

    // Belt-and-braces: no sensitive/internal field may appear in the payload.
    $json = json_encode($record);
    foreach (['password', 'custom_password', 'remember_token', 'parent_phone', 'fcm_token', 'birth', 'gender', 'address', 'email'] as $forbidden) {
        expect($json)->not->toContain('"'.$forbidden.'"');
    }
});

test('kelas and rombel display labels are included', function () {
    $kelas = Kelas::create(['nama_kelas' => '6A (Unggulan)', 'tingkat' => 6, 'is_aktif' => true]);
    $student = fsStudent(['kelas_id' => $kelas->id]);

    $record = fsGet(FS_PATH.'/'.$student->student_uuid)->assertOk()->json('data');

    expect($record['kelas'])->toBe('6A (Unggulan)');
});

test('is_active reflects the lifecycle status', function () {
    $active = fsStudent(['status' => 'active']);
    $alumni = fsStudent(['status' => 'alumni']);

    expect(fsGet(FS_PATH.'/'.$active->student_uuid)->json('data.is_active'))->toBeTrue()
        ->and(fsGet(FS_PATH.'/'.$alumni->student_uuid)->json('data.is_active'))->toBeFalse();
});

// ─── SOFT DELETE / LIFECYCLE ─────────────────────────────────────────────────

test('soft-deleted students are excluded from list search', function () {
    $student = fsStudent(['nis' => 'NIS-DEL-1']);
    $student->delete();

    $response = fsGet(FS_PATH.'?nis=NIS-DEL-1')->assertOk();

    expect($response->json('data.records'))->toHaveCount(0)
        ->and($response->json('data.pagination.total'))->toBe(0);
});

test('soft-deleted student returns 404 on show by default', function () {
    $student = fsStudent();
    $student->delete();

    fsGet(FS_PATH.'/'.$student->student_uuid)->assertNotFound();
});

test('soft-deleted student is available via explicit historical lookup and marked', function () {
    $student = fsStudent();
    $student->delete();

    $record = fsGet(FS_PATH.'/'.$student->student_uuid.'?include_trashed=1')->assertOk()->json('data');

    expect($record['student_uuid'])->toBe($student->student_uuid)
        ->and($record['deleted_at'])->not->toBeNull();
});

test('active student reports deleted_at as null', function () {
    $student = fsStudent();

    expect(fsGet(FS_PATH.'/'.$student->student_uuid)->json('data.deleted_at'))->toBeNull();
});

// ─── SECURITY ────────────────────────────────────────────────────────────────

test('browser-style session request without HMAC cannot use the endpoint', function () {
    $student = fsStudent();

    // Even with a logged-in web user, the HMAC middleware runs before anything.
    test()->actingAs(\App\Models\User::factory()->create(['role' => 'admin']))
        ->getJson(FS_PATH.'/'.$student->student_uuid)
        ->assertUnauthorized();
});

test('endpoint performs no writes (read-only surface)', function () {
    $student = fsStudent();
    $before = Student::withTrashed()->count();

    fsGet(FS_PATH.'/'.$student->student_uuid)->assertOk();
    fsGet(FS_PATH.'?nis='.$student->nis)->assertOk();

    expect(Student::withTrashed()->count())->toBe($before);
});
