<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\InfrastructureStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InfrastructureStatusController extends Controller
{
    public function __invoke(Request $request, InfrastructureStatusService $status): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        return response()->json(['data' => $status->summary()]);
    }
}
