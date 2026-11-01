<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The guided CTF domain: a course, its objectives, the lab sessions that run them
 * and the progress recorded against each.
 *
 * These tables live on the same connection as lab_environments, for a reason that
 * is not tidiness. A lab session owns a victim container, and lab_environments is
 * the record of that container. Putting the session anywhere else would mean either
 * a cross-database foreign key, which MySQL will not honour, or a copied column
 * that can disagree with the row it was copied from. Same connection, real key, one
 * source of truth for "which container is the victim".
 *
 * What is deliberately absent is the flag. The flag exists in exactly one place,
 * inside the victim container, and is read from there at the moment of validation.
 * A column here would put it in the portal database and in every backup of the
 * portal database, which is a different and much larger secret than the one the
 * student is asked to find.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection(config('infrastructure.environment_connection'));

        if (! $schema->hasTable('courses')) {
            $schema->create('courses', function (Blueprint $table): void {
                $table->id();
                $table->string('slug', 64)->unique();
                $table->string('title', 128);
                $table->string('difficulty', 32)->default('Beginner');
                $table->text('summary')->nullable();
                $table->timestamps();
            });
        }

        if (! $schema->hasTable('objectives')) {
            $schema->create('objectives', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('course_id');

                // Sequence is the progression order. It is unique per course
                // because two objectives claiming the same position would make
                // "the next objective" ambiguous, and the whole model is
                // sequential.
                $table->unsignedSmallInteger('sequence');
                $table->string('title', 128);
                $table->text('description');
                $table->text('hint')->nullable();

                $table->enum('validation_type', ['event', 'flag'])->default('event');

                // Which record on the victim proves this objective, and what to
                // look for in it. Null for the flag objective, which is proven by
                // comparison rather than by an event.
                $table->string('evidence_type', 48)->nullable();
                $table->string('evidence_value', 255)->nullable();

                $table->timestamps();

                $table->unique(['course_id', 'sequence']);
                $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            });
        }

        if (! $schema->hasTable('lab_sessions')) {
            $schema->create('lab_sessions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('course_id');

                // The victim is an ordinary environment. Reusing the existing table
                // means the lifecycle service, the terminal machinery and the
                // operations log already know how to drive it, and the session adds
                // the pairing rather than replacing anything.
                $table->unsignedBigInteger('victim_environment_id')->nullable();

                $table->string('attacker_container', 64);
                $table->string('attacker_remote', 64)->default('lab-atk');
                $table->string('attacker_ip', 45)->nullable();

                // Recorded alongside the environment reference rather than read
                // through it. The validator compares an access-log entry against a
                // single column without a join, and an operator reading a session
                // row should not have to open another table to see the pairing.
                $table->string('victim_container', 64)->nullable();
                $table->string('victim_remote', 64)->default('lab-env');
                $table->string('victim_ip', 45)->nullable();

                $table->enum('status', [
                    'CREATING', 'PROVISIONING', 'CONFIGURING', 'READY', 'RUNNING',
                    'COMPLETED', 'FAILED', 'STOPPED', 'RESETTING', 'DELETING',
                ])->default('CREATING');

                // Which objective is in play, as a sequence number rather than an
                // id, so the column is meaningful without a join and survives an
                // objective being renumbered.
                $table->unsignedSmallInteger('current_objective')->default(1);

                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
                $table->foreign('victim_environment_id')
                    ->references('id')->on('lab_environments')
                    ->nullOnDelete();

                // A student has at most one session per course in flight. Enforced
                // here rather than in a policy because it is the one rule that has
                // to hold when two requests arrive at once, and a check that only
                // lives in application code does not.
                $table->unique(['user_id', 'course_id', 'attacker_container']);
            });
        }

        if (! $schema->hasTable('objective_progress')) {
            $schema->create('objective_progress', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('lab_session_id');
                $table->unsignedBigInteger('objective_id');

                $table->enum('status', ['LOCKED', 'ACTIVE', 'COMPLETED'])->default('LOCKED');
                $table->timestamp('completed_at')->nullable();

                // What proved it: the log line or the comparison that was accepted.
                // Kept so an operator can answer "why does this say completed"
                // without going back to the victim's logs, which will have rotated.
                $table->json('evidence')->nullable();

                $table->timestamps();

                $table->unique(['lab_session_id', 'objective_id']);
                $table->foreign('lab_session_id')->references('id')->on('lab_sessions')->cascadeOnDelete();
                $table->foreign('objective_id')->references('id')->on('objectives')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection(config('infrastructure.environment_connection'));

        // Reverse dependency order. Progress and sessions reference the other two.
        $schema->dropIfExists('objective_progress');
        $schema->dropIfExists('lab_sessions');
        $schema->dropIfExists('objectives');
        $schema->dropIfExists('courses');
    }
};