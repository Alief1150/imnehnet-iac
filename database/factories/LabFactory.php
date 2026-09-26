<?php

namespace Database\Factories;

use App\Enums\LabActualState;
use App\Enums\LabDesiredState;
use App\Enums\LabOperationState;
use App\Models\Lab;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Lab>
 */
class LabFactory extends Factory
{
    protected $model = Lab::class;

    public function definition(): array
    {
        $suffix = fake()->unique()->numberBetween(1000, 9999);

        return [
            'user_id' => User::factory(),
            'external_id' => (string) Str::uuid(),
            'name' => "Cybersecurity Lab {$suffix}",
            'container_name' => "imn-lab-{$suffix}",
            'compute_host' => 'lab-env',
            'base_image' => 'images:ubuntu/24.04',
            'profile' => 'default',
            'network' => 'incusbr0',
            'status' => 'PENDING',
            'desired_state' => LabDesiredState::Running,
            'actual_state' => LabActualState::Absent,
            'operation_state' => LabOperationState::Idle,
            'cpu_limit' => 1,
            'memory_limit' => 1024,
            'storage_limit' => 10,
            'security_policy' => 'default-student-isolation',
            'isolation_status' => 'not_configured',
        ];
    }
}
