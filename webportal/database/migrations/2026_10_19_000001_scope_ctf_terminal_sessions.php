<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection(config('infrastructure.environment_connection'));
        foreach (['purpose', 'lab_session_id', 'ctf_epoch'] as $column) {
            if (! $schema->hasColumn('terminal_sessions', $column)) {
                $schema->table('terminal_sessions', function (Blueprint $table) use ($column): void {
                    if ($column === 'purpose') {
                        $table->string('purpose', 20)->default('sandbox')->index();
                    } elseif ($column === 'lab_session_id') {
                        $table->unsignedBigInteger('lab_session_id')->nullable()->index();
                    } else {
                        $table->string('ctf_epoch', 36)->nullable();
                    }
                });
            }
        }
        if (! $schema->hasColumn('lab_sessions', 'ctf_epoch')) {
            $schema->table('lab_sessions', fn (Blueprint $table) => $table->string('ctf_epoch', 36)->nullable());
        }
    }

    public function down(): void
    {
        $schema = Schema::connection(config('infrastructure.environment_connection'));
        $schema->table('terminal_sessions', fn (Blueprint $table) => $table->dropColumn(['purpose', 'lab_session_id', 'ctf_epoch']));
        $schema->table('lab_sessions', fn (Blueprint $table) => $table->dropColumn('ctf_epoch'));
    }
};
