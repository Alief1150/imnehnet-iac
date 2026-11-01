<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('lab_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('type', 32);
            $table->string('status', 32)->default('queued');
            $table->uuid('idempotency_key')->unique();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['lab_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_operations');
    }
};
