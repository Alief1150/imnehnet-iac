<?php

use App\Enums\EnvironmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The infrastructure database already owns lab_environments in production.
 *
 * This migration therefore never drops or recreates it: where the table is
 * present only the missing columns are added, so an operator's environment
 * inventory survives a deploy. Where it is absent, which is the case for the
 * throwaway database the test suite runs on, the table is created so the
 * suite exercises the same model against the same schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection(config('infrastructure.environment_connection'));

        if (! $schema->hasTable('lab_environments')) {
            $schema->create('lab_environments', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('container_name', 64);
                $table->string('incus_remote', 64)->default('lab-env');
                $table->string('incus_project', 64)->default('default');
                $table->string('image', 128)->default('images:ubuntu/24.04');
                $table->string('internal_ip', 45)->nullable();
                $table->enum('status', array_column(EnvironmentStatus::cases(), 'value'))
                    ->default(EnvironmentStatus::Provisioning->value);
                $table->string('runtime_status', 32)->default('Unknown');
                $table->smallInteger('cpu_limit')->default(1);
                $table->integer('memory_limit_mb')->default(1024);
                $table->integer('disk_limit_gb')->nullable();
                $table->string('tofu_workspace', 128)->nullable();
                $table->boolean('terminal_enabled')->default(false);
                $table->text('last_error')->nullable();
                $table->timestamp('provisioned_at')->nullable();
                $table->timestamps();
            });

            return;
        }

        // Additive only. Every column the portal needs is optional at this
        // point so a partial automation schema does not fail the deploy.
        $columns = [
            'user_id' => 'unsignedBigInteger',
            'incus_remote' => 'string',
            'incus_project' => 'string',
            'image' => 'string',
            'internal_ip' => 'string',
            'runtime_status' => 'string',
            'cpu_limit' => 'smallInteger',
            'memory_limit_mb' => 'integer',
            'disk_limit_gb' => 'integer',
            'tofu_workspace' => 'string',
            'terminal_enabled' => 'boolean',
            'last_error' => 'text',
            'provisioned_at' => 'timestamp',
        ];

        foreach ($columns as $column => $type) {
            if (! $schema->hasColumn('lab_environments', $column)) {
                $schema->table('lab_environments', function (Blueprint $table) use ($column, $type): void {
                    $table->{$type}($column)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        // Intentionally empty. Dropping the authoritative infrastructure
        // inventory on rollback would destroy environments that are still
        // running on the compute plane.
    }
};
