<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columns the terminal session needs beyond the original grant record.
 *
 * The credential is stored encrypted rather than hashed because a reconnect has
 * to hand the still-running ttyd its original secret, not merely recognise it.
 * The resolved shell uid is recorded rather than assumed, so the uid a session
 * was actually opened with is auditable after the fact instead of having to be
 * re-derived from a configuration value that may since have changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('infrastructure.environment_connection'))->table(
            'terminal_sessions',
            function (Blueprint $table): void {
                $table->text('credential')->nullable()->after('ttyd_port');
                $table->unsignedSmallInteger('shell_uid')->nullable()->after('credential');
                $table->string('shell_user', 32)->nullable()->after('shell_uid');
            },
        );
    }

    public function down(): void
    {
        Schema::connection(config('infrastructure.environment_connection'))->table(
            'terminal_sessions',
            function (Blueprint $table): void {
                $table->dropColumn(['credential', 'shell_uid', 'shell_user']);
            },
        );
    }
};
