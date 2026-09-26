<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProvisionLabRequest;
use App\Http\Resources\LabOperationResource;
use App\Http\Resources\LabResource;
use App\Models\Lab;
use App\Models\User;
use App\Services\LabProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class LabController extends Controller
{
    public function show(Request $request, int $labId): LabResource
    {
        $lab = Lab::query()
            ->when(! $request->user()->isAdmin(), fn ($query) => $query->where('user_id', $request->user()->id))
            ->findOrFail($labId);
        Gate::authorize('view', $lab);

        return new LabResource($lab);
    }

    public function provision(
        ProvisionLabRequest $request,
        LabProvisioningService $service,
    ): JsonResponse {
        $student = User::query()->findOrFail($request->integer('user_id'));
        $operation = $service->request($request->user(), $student, $request->validated());

        return (new LabOperationResource($operation))
            ->response()
            ->setStatusCode(202);
    }
}
