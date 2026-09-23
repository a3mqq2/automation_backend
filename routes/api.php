<?php

use App\Http\Controllers\Api\Admin\ActivityLogController as AdminActivityLogController;
use App\Http\Controllers\Api\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\Admin\ClientController;
use App\Http\Controllers\Api\Admin\LicenseKeyController;
use App\Http\Controllers\Api\Admin\StatsController;
use App\Http\Controllers\Api\Auth\ClientAuthController;
use App\Http\Controllers\Api\Auth\FacebookAuthController;
use App\Http\Controllers\Api\Client\ActivityLogController;
use App\Http\Controllers\Api\Client\AutomationRuleController;
use App\Http\Controllers\Api\Client\BotFlowController;
use App\Http\Controllers\Api\Client\ConnectedPageController;
use App\Http\Controllers\Api\Client\FacebookLinkController;
use App\Http\Controllers\Api\Client\LicenseActivationController;
use App\Http\Controllers\Api\Client\MediaController;
use App\Http\Controllers\Api\Client\PageConnectionController;
use App\Http\Controllers\Api\Client\PagePostController;
use App\Http\Controllers\Api\Client\ProductBrandController;
use App\Http\Controllers\Api\Client\ProductCategoryController;
use App\Http\Controllers\Api\Client\ProductController;
use App\Http\Controllers\Api\Client\PageController;
use App\Http\Controllers\Api\Client\ProfileController;
use App\Http\Controllers\Api\Client\SessionController;
use App\Http\Controllers\Api\Webhook\FacebookAccountController;
use App\Http\Controllers\Api\Webhook\MetaWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->controller(ClientAuthController::class)->group(function (): void {
    Route::post('register', 'register')->middleware('throttle:client-registration');
    Route::post('login', 'login')->middleware('throttle:client-login');
});

Route::prefix('auth/facebook')
    ->middleware('throttle:facebook-auth')
    ->controller(FacebookAuthController::class)
    ->group(function (): void {
        Route::get('redirect', 'redirect');
        Route::get('callback', 'callback');
    });

Route::middleware(['auth:sanctum', 'client'])->group(function (): void {
    Route::post('auth/logout', [SessionController::class, 'destroy']);
    Route::get('me', [ProfileController::class, 'show']);
    Route::post('auth/license-key/activate', [LicenseActivationController::class, 'store'])
        ->middleware('throttle:license-activation');

    Route::middleware('subscribed')->group(function (): void {
        Route::prefix('auth/facebook/link')
            ->middleware('throttle:facebook-auth')
            ->controller(FacebookLinkController::class)
            ->group(function (): void {
                Route::get('', 'redirect');
                Route::post('', 'store');
            });

        Route::get('pages', [PageController::class, 'index']);
        Route::get('pages/connected', [ConnectedPageController::class, 'index']);
        Route::get('pages/{pageId}', [ConnectedPageController::class, 'show'])->whereNumber('pageId');
        Route::post('pages/{pageId}/connect', [PageConnectionController::class, 'store'])->whereNumber('pageId');
        Route::delete('pages/{pageId}', [PageConnectionController::class, 'destroy'])->whereNumber('pageId');

        Route::apiResource('rules', AutomationRuleController::class)->parameters(['rules' => 'rule']);
        Route::apiResource('bot-flows', BotFlowController::class)->parameters(['bot-flows' => 'botFlow']);
        Route::post('bot-flows/{botFlow}/publish', [BotFlowController::class, 'publish']);
        Route::post('bot-flows/{botFlow}/simulate', [BotFlowController::class, 'simulate']);
        Route::get('bot-flows/{botFlow}/versions', [BotFlowController::class, 'versions']);
        Route::post('bot-flows/{botFlow}/versions/{version}/restore', [BotFlowController::class, 'restoreVersion']);

        Route::get('pages/{pageId}/posts', [PagePostController::class, 'index'])->whereNumber('pageId');

        Route::apiResource('product-categories', ProductCategoryController::class)->parameters(['product-categories' => 'category']);
        Route::apiResource('product-brands', ProductBrandController::class)->parameters(['product-brands' => 'brand']);
        Route::apiResource('products', ProductController::class)->parameters(['products' => 'product']);
        Route::post('products/{product}/posts', [ProductController::class, 'linkPost']);
        Route::delete('products/{product}/posts', [ProductController::class, 'unlinkPost']);

        Route::post('media', [MediaController::class, 'store']);
        Route::delete('media', [MediaController::class, 'destroy']);

        Route::get('activity-logs', [ActivityLogController::class, 'index']);
        Route::get('activity-logs/{activityLog}', [ActivityLogController::class, 'show']);
    });
});

Route::prefix('admin')->group(function (): void {
    Route::post('login', [AdminAuthController::class, 'login'])->middleware('throttle:admin-login');

    Route::middleware(['auth:sanctum', 'admin'])->group(function (): void {
        Route::post('logout', [AdminAuthController::class, 'logout']);
        Route::get('me', [AdminAuthController::class, 'me']);
        Route::get('stats', [StatsController::class, 'show']);

        Route::get('clients', [ClientController::class, 'index']);
        Route::get('clients/{client}', [ClientController::class, 'show']);

        Route::apiResource('license-keys', LicenseKeyController::class)
            ->only(['index', 'store', 'show', 'destroy'])
            ->parameters(['license-keys' => 'licenseKey']);

        Route::get('activity-logs', [AdminActivityLogController::class, 'index']);
        Route::get('activity-logs/{activityLog}', [AdminActivityLogController::class, 'show']);
    });
});

Route::get('webhook', [MetaWebhookController::class, 'verify']);
Route::post('webhook', [MetaWebhookController::class, 'receive'])->middleware('meta.signature');

Route::prefix('facebook')->controller(FacebookAccountController::class)->group(function (): void {
    Route::post('deauthorize', 'deauthorize');
    Route::post('data-deletion', 'requestDeletion');
    Route::get('data-deletion/status', 'deletionStatus');
});
