<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection((string) config('infrastructure.environment_connection', 'imnehnet'));

        if ($schema->hasTable('lab_environments')
            && ! $schema->hasColumn('lab_environments', 'storage_pool')) {
            $schema->table('lab_environments', function (Blueprint $table): void {
                // NULL means a legacy instance: do not infer its current pool
                // from a feature flag or silently migrate its root disk.
                $table->string('storage_pool', 128)->nullable();
            });
        }
    }

    public function down(): void
    {
        // Deliberately retain the pool identity. Dropping it during rollback
        // could cause existing Btrfs containers to be treated as DIR.
    }
};
