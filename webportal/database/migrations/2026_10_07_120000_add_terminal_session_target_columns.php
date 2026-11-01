<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a terminal session say which kind of target it is.
 *
 * ── Why not a second table ────────────────────────────────────────────────────────
 *
 * A session is a session: a pid, a loopback port, a credential, a user, an expiry, and a
 * reaper that closes it. Nothing about that differs between a shell inside a student
 * container and a shell on a management host, so a second table would have meant a second
 * reaper and a second thing to forget to close — and a shell left running is the exact
 * failure this whole feature is built to avoid.
 *
 * ── Why not just leave container_name null ─────────────────────────────────────────
 *
 * Because that makes one column mean two things, and the ambiguity shows up as a bug
 * rather than as a smell. `container_name` populated is a container; null would have to
 * mean "a node", and every future query has to know which. Stated explicitly it is
 * answerable: ask the column, do not infer it.
 *
 * `target_node` is null for a container session, whose target is already recorded as a
 * lab_environment_id. Keeping the existing column for what it was named for means the
 * student terminal's own queries do not change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('infrastructure.environment_connection'))->table('terminal_sessions', function (Blueprint $table): void {
            $table->string('target_kind')
                ->default('container')
                ->after('lab_environment_id')
                ->comment('container = inside a lab environment; node = on an infrastructure host');

            $table->string('target_node')
                ->nullable()
                ->after('target_kind')
                ->comment('Infrastructure node the shell runs on, for a node session');
        });
    }

    public function down(): void
    {
        Schema::connection(config('infrastructure.environment_connection'))->table('terminal_sessions', function (Blueprint $table): void {
            $table->dropColumn(['target_kind', 'target_node']);
        });
    }
};