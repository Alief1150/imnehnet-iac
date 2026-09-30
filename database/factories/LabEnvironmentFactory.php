<?php

namespace Database\Factories;

use App\Enums\EnvironmentStatus;
use App\Enums\RuntimeStatus;
use App\Models\LabEnvironment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LabEnvironment>
 */
class LabEnvironmentFactory extends Factory
{
    protected $model = LabEnvironment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'lab-'.fake()->unique()->regexify('[a-z0-9]{6}');

        return [
            'container_name' => $name,
            'incus_remote' => 'lab-env',
            'incus_project' => 'default',
            'image' => 'images:ubuntu/24.04',
            'internal_ip' => '10.20.0.'.fake()->numberBetween(20, 200),
            'status' => EnvironmentStatus::Ready,
            'runtime_status' => RuntimeStatus::Running,
            'cpu_limit' => 1,
            'memory_limit_mb' => 1024,
            'disk_limit_gb' => null,
            'tofu_workspace' => LabEnvironment::workspaceFor($name),
            'terminal_enabled' => true,
        ];
    }

    public function provisioning(): static
    {
        return $this->state(fn (): array => [
            'status' => EnvironmentStatus::Provisioning,
            'runtime_status' => RuntimeStatus::Unknown,
        ]);
    }

    public function error(string $message = 'The infrastructure provider failed.'): static
    {
        return $this->state(fn (): array => [
            'status' => EnvironmentStatus::Error,
            'last_error' => $message,
        ]);
    }
}
