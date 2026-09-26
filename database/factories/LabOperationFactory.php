<?php

namespace Database\Factories;

use App\Enums\OperationStatus;
use App\Models\Lab;
use App\Models\LabOperation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LabOperation>
 */
class LabOperationFactory extends Factory
{
    protected $model = LabOperation::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'lab_id' => Lab::factory(),
            'requested_by' => User::factory(),
            'type' => 'provision',
            'status' => OperationStatus::Queued,
            'idempotency_key' => (string) Str::uuid(),
        ];
    }
}
