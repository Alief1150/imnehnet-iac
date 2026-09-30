<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Short-lived records that authorise a web terminal session.
 *
 * The browser never receives a launch command or a container name it can
 * freely choose. It receives a session id, and the server resolves the
 * container from this row, so an administrator cannot open a shell on an
 * environment they were not granted, and the session expires on its own if
 * the tab is closed without logging out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('imnehnet')->create('terminal_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->index();
            $table->unsignedBigInteger('lab_environment_id')->index();
            $table->string('container_name', 64);
            $table->string('pid', 32)->nullable();
            $table->string('ttyd_port', 8)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['lab_environment_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('imnehnet')->dropIfExists('terminal_sessions');
    }
};
