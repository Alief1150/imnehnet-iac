<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 32)->default('active')->after('role');
            $table->boolean('must_change_password')->default(false)->after('status');
            $table->timestamp('last_login_at')->nullable()->after('must_change_password');
        });

        Schema::table('labs', function (Blueprint $table) {
            $table->uuid('external_id')->nullable()->unique()->after('id');
            $table->string('compute_host')->default('lab-env')->after('container_name');
            $table->string('base_image')->default('images:ubuntu/24.04')->after('compute_host');
            $table->string('profile')->default('default')->after('base_image');
            $table->string('desired_state', 32)->default('running')->after('status');
            $table->string('actual_state', 32)->default('unknown')->after('desired_state');
            $table->string('operation_state', 32)->default('idle')->after('actual_state');
            $table->string('access_host')->nullable()->after('web_port');
            $table->string('access_username')->nullable()->after('access_host');
            $table->timestamp('access_verified_at')->nullable()->after('access_username');
            $table->timestamp('last_verified_at')->nullable()->after('access_verified_at');
            $table->string('security_policy')->default('default-student-isolation')->after('storage_limit');
            $table->string('isolation_status', 32)->default('not_configured')->after('security_policy');
            $table->text('last_error_message')->nullable()->after('isolation_status');
            $table->softDeletes();
            $table->unique('user_id');
        });

        DB::table('labs')
            ->whereNull('external_id')
            ->orderBy('id')
            ->each(fn (object $lab) => DB::table('labs')
                ->where('id', $lab->id)
                ->update(['external_id' => (string) Str::uuid()]));

        Schema::table('labs', function (Blueprint $table) {
            $table->uuid('external_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('labs', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->dropSoftDeletes();
            $table->dropUnique(['external_id']);
            $table->dropColumn([
                'external_id',
                'compute_host',
                'base_image',
                'profile',
                'desired_state',
                'actual_state',
                'operation_state',
                'access_host',
                'access_username',
                'access_verified_at',
                'last_verified_at',
                'security_policy',
                'isolation_status',
                'last_error_message',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['status', 'must_change_password', 'last_login_at']);
        });
    }
};
