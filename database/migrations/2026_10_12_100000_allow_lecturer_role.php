<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Let the users table actually hold a lecturer.
 *
 * ── Why ─────────────────────────────────────────────────────────────────────────────
 *
 * UserRole gained a Lecturer case so that an academic could open a shell on an
 * infrastructure node. The enum alone was not enough: users.role is a database ENUM of
 * ['admin', 'student'] on MySQL and the same list as a CHECK constraint on SQLite, so every
 * attempt to store a lecturer was rejected by the database.
 *
 *   SQLSTATE[23000]: Integrity constraint violation: 19 CHECK constraint failed: role
 *
 * That means the capability the role was added for was unreachable. canOperateInfrastructure()
 * returned true for a role no account could ever hold, which is the worst kind of dead code:
 * it reads as a finished feature and cannot be exercised outside a hand-edited row. The test
 * that found it was asserting the split between operating infrastructure and administering
 * the portal, and could not create the user it needed.
 *
 * ── Ordering ─────────────────────────────────────────────────────────────────────────
 *
 * Lecturer sits between admin and student, matching the declaration order in UserRole. The
 * order of an ENUM has no effect on existing rows — MySQL stores the ordinal and remaps it
 * by value when the column is redefined — and both values already in the table are kept in
 * the new definition.
 *
 * ── Connection ───────────────────────────────────────────────────────────────────────
 *
 * users is on the default connection, so this uses the default Schema builder rather than
 * naming one. Unlike terminal_sessions it is not on the environment connection, and a
 * migration that names the wrong one alters the wrong database without saying so.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', ['admin', 'lecturer', 'student'])
                ->default('student')
                ->change();
        });
    }

    public function down(): void
    {
        // Refused rather than performed. Narrowing the column cannot hold a lecturer, so the
        // only way this could succeed is by deleting or demoting the accounts that are one.
        // A down() that destroys rows to fit the schema is worse than one that declines, and
        // this table holds real accounts.
        if (Schema::getConnection()->table('users')->where('role', 'lecturer')->exists()) {
            throw new RuntimeException(
                'Cannot narrow users.role while lecturer accounts exist. Demote them first.'
            );
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', ['admin', 'student'])
                ->default('student')
                ->change();
        });
    }
};