<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class LabResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'desired_state' => $this->desired_state->value,
            'actual_state' => $this->actual_state->value,
            'operation_state' => $this->operation_state->value,
            'limits' => [
                'cpu' => $this->cpu_limit,
                'memory_mb' => $this->memory_limit,
                'storage_gb' => $this->storage_limit,
            ],
            'last_verified_at' => $this->last_verified_at?->toAtomString(),
            'access' => $this->when($this->access_verified_at !== null, [
                'ssh_command' => $this->sshCommand(),
                'verified_at' => $this->access_verified_at?->toAtomString(),
            ]),
        ];
    }
}
