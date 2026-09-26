<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('labs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('container_name')->unique();
            $table->string('ip_address')->nullable();
            $table->string('network')->default('10.20.0.0/24');
            $table->enum('status', ['PENDING', 'PROVISIONING', 'READY', 'RUNNING', 'STOPPED', 'ERROR'])->default('PENDING');
            $table->integer('ssh_port')->nullable();
            $table->integer('web_port')->nullable();
            $table->integer('cpu_limit');
            $table->integer('memory_limit');
            $table->integer('storage_limit');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('labs');
    }
};
