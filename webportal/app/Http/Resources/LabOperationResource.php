<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class LabOperationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lab_id' => $this->lab_id,
            'type' => $this->type,
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toAtomString(),
            'started_at' => $this->started_at?->toAtomString(),
            'finished_at' => $this->finished_at?->toAtomString(),
        ];
    }
}
