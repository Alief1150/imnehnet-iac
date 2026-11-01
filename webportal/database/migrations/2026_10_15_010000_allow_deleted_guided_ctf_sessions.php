<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(config('infrastructure.environment_connection'))
            ->table('lab_sessions', function (Blueprint $table): void {
                $table->enum('status', [
                    'CREATING', 'PROVISIONING', 'CONFIGURING',
                    'READY', 'RUNNING', 'COMPLETED', 'FAILED',
                    'STOPPED', 'RESETTING', 'DELETING', 'DELETED',
                ])->default('CREATING')->change();
            });

        if (! Schema::connection(config('infrastructure.environment_connection'))
            ->hasColumn('lab_sessions', 'last_error')) {
            Schema::connection(config('infrastructure.environment_connection'))
                ->table('lab_sessions', function (Blueprint $table): void {
                    $table->text('last_error')->nullable();
                });
        }
    }

    public function down(): void
    {
        throw new RuntimeException(
            'This migration is intentionally irreversible because historical '
            .'lab sessions may use the DELETED status.'
        );
    }
};
