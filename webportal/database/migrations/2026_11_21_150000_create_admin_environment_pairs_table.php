<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $connection = (string) config('infrastructure.environment_connection', 'imnehnet');
        Schema::connection($connection)->create('admin_environment_pairs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('name', 40);
            $table->unsignedBigInteger('attacker_environment_id')->nullable()->unique();
            $table->unsignedBigInteger('victim_environment_id')->nullable()->unique();
            $table->string('status', 24)->default('QUEUED');
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        // Explicitly fail closed: a rollback must never erase pair ownership
        // while its Incus containers and OpenTofu workspaces might still exist.
        throw new RuntimeException('Manual infrastructure-safe teardown required before dropping admin_environment_pairs.');
    }
};
