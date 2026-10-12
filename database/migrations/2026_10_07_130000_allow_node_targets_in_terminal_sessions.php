<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Let a terminal session name a node instead of a container.
 *
 * ── Why ─────────────────────────────────────────────────────────────────────────────
 *
 * terminal_sessions began as the table for student shells, and every column was written
 * around that: lab_environment_id and container_name are both NOT NULL, because a student
 * shell always had one of each. The administrative console reuses the table rather than
 * adding a second one — the two kinds of session are the same kind of thing, and a second
 * table would mean two places to ask "is there a live shell here", which is exactly the
 * question both the reaper and the one-session-per-user rule have to ask.
 *
 * Reusing it means an administrative session has no LabEnvironment and no container name
 * at all. It is identified by target_kind = 'node' plus target_node. So the two columns
 * have to be nullable: "no container" is the ordinary case for an administrative session,
 * not an exceptional one, and a NOT NULL column cannot record the ordinary case.
 *
 * target_kind stays NOT NULL with its default of 'container', so every row written before
 * this migration keeps naming a container, and a row written by code that predates the
 * column is a container session rather than an ambiguous one.
 *
 * ── On the index ────────────────────────────────────────────────────────────────────
 *
 * lab_environment_id carries an index that is left in place. A container session still
 * benefits from it, and MySQL indexes a nullable column without complaint — dropping the
 * index to avoid NULLs would trade a real cost for a cosmetic tidiness.
 *
 * ── On down() ───────────────────────────────────────────────────────────────────────
 *
 * The old shape cannot represent a node session, so the reverse direction removes those
 * rows rather than inventing a container for them. Fabricating a lab_environment_id here
 * would put a row in the table that names an environment which was never used, and the
 * next person to read it would believe a container had been opened.
 */
return new class extends Migration
{

    public function up(): void
    {
        Schema::connection(config('infrastructure.environment_connection'))->table('terminal_sessions', function (Blueprint $table): void {
            $table->bigInteger('lab_environment_id')->nullable()->change();
            $table->string('container_name', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Deleted with the query builder, not inside a schema callback: a Blueprint
        // describes columns, it does not delete rows.
        DB::connection(config('infrastructure.environment_connection'))->table('terminal_sessions')
            ->where('target_kind', 'node')
            ->delete();

        Schema::connection(config('infrastructure.environment_connection'))->table('terminal_sessions', function (Blueprint $table): void {
            $table->bigInteger('lab_environment_id')->nullable(false)->change();
            $table->string('container_name', 64)->nullable(false)->change();
        });
    }
};
