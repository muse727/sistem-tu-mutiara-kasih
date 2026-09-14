<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Phase 09B.1 — Stable student external identity (student_uuid).
 *
 * Adds an opaque, immutable, globally-unique identifier to every student.
 *
 * Design:
 *   - students.id remains the internal primary key and is untouched.
 *   - student_uuid is an ADDITIONAL business/integration identity. It is
 *     generated once, never regenerated, never derived from name or NIS, and
 *     survives name/NIS/class/academic-year changes and soft delete/restore.
 *   - The UNIQUE constraint is the final authority against duplicate identity,
 *     protecting against concurrent creation/import races.
 *
 * Backfill:
 *   - Every existing row (including soft-deleted) receives exactly one UUID.
 *   - Rows are NOT recreated; students.id is unchanged; no duplicates created.
 *   - A pre-flight uniqueness assertion guarantees no uuid is assigned twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Add the column (nullable first so we can backfill safely).
        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'student_uuid')) {
                $table->uuid('student_uuid')->nullable()->after('id');
            }
        });

        // 2. Backfill existing rows — including soft-deleted (unscoped query).
        $now   = now();
        $uuids = [];
        DB::table('students')->select('id')->orderBy('id')->chunk(500, function ($rows) use (&$uuids, $now) {
            foreach ($rows as $row) {
                $uuid = (string) Str::uuid();

                // Defensive: a UUID collision is astronomically unlikely, but the
                // final UNIQUE constraint is the real authority. Track in-memory
                // for an early, explicit failure during the backfill itself.
                if (isset($uuids[$uuid])) {
                    throw new \RuntimeException("student_uuid collision during backfill: {$uuid}");
                }
                $uuids[$uuid] = true;

                DB::table('students')->where('id', $row->id)
                    ->update(['student_uuid' => $uuid, 'updated_at' => $now]);
            }
        });

        // 3. Enforce the UNIQUE constraint (final authority on identity).
        Schema::table('students', function (Blueprint $table) {
            $table->unique('student_uuid', 'students_student_uuid_unique');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'student_uuid')) {
                $table->dropUnique('students_student_uuid_unique');
                $table->dropColumn('student_uuid');
            }
        });
    }
};
