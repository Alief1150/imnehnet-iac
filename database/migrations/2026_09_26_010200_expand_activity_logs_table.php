<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreignId('actor_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->string('action', 96)->after('actor_id');
            $table->string('resource_type')->nullable()->after('action');
            $table->string('resource_id')->nullable()->after('resource_type');
            $table->string('status', 32)->after('resource_id');
            $table->text('description')->after('status');
            $table->json('metadata')->nullable()->after('description');
            $table->string('ip_address', 45)->nullable()->after('metadata');
            $table->text('user_agent')->nullable()->after('ip_address');

            $table->index(['resource_type', 'resource_id']);
            $table->index(['action', 'created_at']);
            $table->dropColumn('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->timestamp('updated_at')->nullable();
            $table->dropIndex(['action', 'created_at']);
            $table->dropIndex(['resource_type', 'resource_id']);
            $table->dropConstrainedForeignId('actor_id');
            $table->dropColumn([
                'action',
                'resource_type',
                'resource_id',
                'status',
                'description',
                'metadata',
                'ip_address',
                'user_agent',
            ]);
        });
    }
};
