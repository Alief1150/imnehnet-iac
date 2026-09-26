<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LabOperationResource;
use App\Models\Lab;
use App\Services\LabLifecycleService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LabLifecycleController extends Controller
{
    public function __invoke(
        Request $request,
        int $labId,
        string $action,
        LabLifecycleService $service,
    ): JsonResponse {
        $lab = Lab::query()
            ->when(! $request->user()->isAdmin(), fn ($query) => $query->where('user_id', $request->user()->id))
            ->findOrFail($labId);
        try {
            $operation = $service->request($request->user(), $lab, $action);
        } catch (DomainException $exception) {
            $code = $exception->getMessage() === 'Another lab operation is already active.'
                ? 'operation_conflict'
                : 'invalid_state_transition';

            return response()->json([
                'error' => [
                    'code' => $code,
                    'message' => 'The requested lifecycle action is not available for the current lab state.',
                ],
            ], 409);
        }

        return (new LabOperationResource($operation))
            ->response()
            ->setStatusCode(202);
    }
}
