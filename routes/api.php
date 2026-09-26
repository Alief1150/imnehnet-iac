<?php

use App\Http\Controllers\Api\V1\InfrastructureStatusController;
use App\Http\Controllers\Api\V1\LabController;
use App\Http\Controllers\Api\V1\LabLifecycleController;
use App\Http\Middleware\EnsureActiveUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['web', 'auth:web', EnsureActiveUser::class])->group(function (): void {
    Route::get('/me', function (Request $request) {
        $user = $request->user();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'status' => $user->status->value,
            ],
        ]);
    });

    Route::get('/labs/{labId}', [LabController::class, 'show']);
    Route::post('/labs/provision', [LabController::class, 'provision']);
    Route::post('/labs/{labId}/{action}', LabLifecycleController::class)
        ->whereIn('action', ['start', 'stop', 'reset', 'delete']);
    Route::get('/infrastructure', InfrastructureStatusController::class);
});
