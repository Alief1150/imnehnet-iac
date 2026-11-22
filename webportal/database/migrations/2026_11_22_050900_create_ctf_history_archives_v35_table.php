<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ctf_history_archives_v35', function (Blueprint $table) {
            $table->unsignedBigInteger('lab_session_id')->primary();
            $table->unsignedBigInteger('archived_by')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ctf_history_archives_v35');
    }
};
