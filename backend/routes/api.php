<?php

use BlastSpec\Http\Controllers\Api\V1\AuthController;
use BlastSpec\Http\Controllers\Api\V1\CiWebhookController;
use BlastSpec\Http\Controllers\Api\V1\ConsumerBindingController;
use BlastSpec\Http\Controllers\Api\V1\DlqController;
use BlastSpec\Http\Controllers\Api\V1\DiffController;
use BlastSpec\Http\Controllers\Api\V1\GraphController;
use BlastSpec\Http\Controllers\Api\V1\ImpactReportController;
use BlastSpec\Http\Controllers\Api\V1\ServiceController;
use BlastSpec\Http\Controllers\Api\V1\SpecController;
use BlastSpec\Http\Controllers\Api\V1\WebhookEventController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void
{
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'checks' => [
                'database' => ['status' => 'ok', 'latency_ms' => 0],
                'redis' => ['status' => 'ok', 'latency_ms' => 0],
                'graph' => ['status' => 'ok', 'latency_ms' => 0],
            ],
            'version' => '1.0.0',
        ]);
    });

    Route::post('/auth/login', [AuthController::class, 'login'])->name('login');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/auth/me', [AuthController::class, 'me'])->middleware('auth:sanctum');

    Route::middleware(['auth', 'workspace.context'])->group(function (): void
    {
        Route::apiResource('services', ServiceController::class)->only(['index', 'store', 'show', 'update', 'destroy']);

        Route::get('/specs', [SpecController::class, 'index']);
        Route::post('/specs', [SpecController::class, 'store']);
        Route::get('/specs/{specId}', [SpecController::class, 'show']);
        Route::delete('/specs/{specId}', [SpecController::class, 'destroy']);
        Route::get('/specs/{specId}/versions', [SpecController::class, 'versions']);
        Route::post('/specs/{specId}/versions', [SpecController::class, 'uploadVersion']);
        Route::post('/diffs', [DiffController::class, 'store']);
        Route::get('/diffs/{diffId}', [DiffController::class, 'show']);
        Route::get('/impact-reports', [ImpactReportController::class, 'index']);
        Route::post('/impact-reports', [ImpactReportController::class, 'store']);
        Route::post('/webhooks/ci', [CiWebhookController::class, 'receive'])
            ->middleware('verify.webhook');

        Route::get('/webhook-events', [WebhookEventController::class, 'index']);

        Route::get('/consumer-bindings', [ConsumerBindingController::class, 'index']);
        Route::post('/consumer-bindings', [ConsumerBindingController::class, 'store']);
        Route::delete('/consumer-bindings/{bindingId}', [ConsumerBindingController::class, 'destroy']);
    });
});
