<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record the attacker as an environment too.
 *
 * A lab is two containers and only one of them is a victim, but the portal's terminal
 * machinery is built around LabEnvironment: it resolves a container name, resolves the
 * shell uid inside it, and opens a session against it. Rather than fork all of that for
 * one kind of container, the attacker is recorded as an environment on the attacker
 * plane.
 *
 * incus_remote already distinguishes the two halves and needs no new column to do it:
 * lab-env is the victim, lab-atk is the attacker. What is added here is the link from
 * the session, because the session is the thing that knows which environment is which —
 * and that link is what stops a terminal request naming a container of its own choosing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = (string) config('infrastructure.environment_connection', 'imnehnet');

        $schema = Schema::connection($connection);

        if (! $schema->hasTable('lab_sessions')) {
            return;
        }

        if (! $schema->hasColumn('lab_sessions', 'attacker_environment_id')) {
            $schema->table('lab_sessions', function (Blueprint $table): void {
                $table->unsignedBigInteger('attacker_environment_id')->nullable()->after('victim_environment_id');
            });
        }

        // Added as a separate statement because a foreign key cannot be added in the
        // same statement as the column on MySQL, and lab_sessions may already have rows
        // that the new key has nothing to say about. Null on delete, like the victim's:
        // if an environment row goes, the session loses the handle rather than the
        // history.
        $schema->table('lab_sessions', function (Blueprint $table): void {
            $table->foreign('attacker_environment_id')
                ->references('id')
                ->on('lab_environments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        $connection = (string) config('infrastructure.environment_connection', 'imnehnet');

        $schema = Schema::connection($connection);

        if (! $schema->hasTable('lab_sessions') || ! $schema->hasColumn('lab_sessions', 'attacker_environment_id')) {
            return;
        }

        $schema->table('lab_sessions', function (Blueprint $table): void {
            $table->dropForeign(['attacker_environment_id']);
            $table->dropColumn('attacker_environment_id');
        });
    }
};