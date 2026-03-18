<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\BotController;
use App\Http\Controllers\BotManagementController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\JobTrackerController;
use App\Http\Controllers\Marketing\AdminResourceController;
use App\Http\Controllers\Marketing\AffiliateResourceController;
use App\Http\Controllers\Marketing\PressResourceController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\TemplateController;
use Illuminate\Support\Facades\Route;

/* ==========================================================================
 * Firebase trigger events (secured by API secret)
 * ======================================================================== */

Route::middleware('firebase.webhook')->prefix('events')->group(function () {
    Route::post('/user-registered', [EventController::class, 'userRegistered']);
    Route::post('/call-completed', [EventController::class, 'callCompleted']);
    Route::post('/payment-received', [EventController::class, 'paymentReceived']);
    Route::post('/paypal-payment', [EventController::class, 'paypalPayment']);
    Route::post('/withdrawal-requested', [EventController::class, 'withdrawalRequested']);
    Route::post('/new-provider', [EventController::class, 'newProvider']);
    Route::post('/new-contact-message', [EventController::class, 'newContactMessage']);
    Route::post('/security-alert', [EventController::class, 'securityAlert']);
    Route::post('/negative-review', [EventController::class, 'negativeReview']);
    Route::post('/captain-application', [EventController::class, 'captainApplication']);
    Route::post('/user-feedback', [EventController::class, 'userFeedback']);
    Route::post('/partner-application', [EventController::class, 'partnerApplication']);
    Route::post('/withdrawal-status', [EventController::class, 'withdrawalStatusChanged']);
    Route::post('/payout-failed', [EventController::class, 'payoutFailed']);
    Route::post('/service-balance-alert', [EventController::class, 'serviceBalanceAlert']);
});

/* ==========================================================================
 * Telegram Bot webhook (secured by Telegram secret)
 * ======================================================================== */

Route::post('/bot/webhook', [BotController::class, 'handleWebhook'])
    ->middleware('telegram.verify');

// Onboarding bot webhook (separate bot for user registration)
Route::post('/bot/onboarding-webhook', [BotController::class, 'handleOnboardingWebhook'])
    ->middleware('telegram.verify');

/* ==========================================================================
 * Onboarding (secured by Firebase Auth token)
 * ======================================================================== */

Route::middleware('firebase.token')->prefix('onboarding')->group(function () {
    Route::post('/generate-link', [OnboardingController::class, 'generateLink']);
    Route::get('/check-status', [OnboardingController::class, 'checkStatus']);
    Route::post('/skip', [OnboardingController::class, 'skip']);
});

/* ==========================================================================
 * Admin (secured by Firebase Auth + admin role check)
 * ======================================================================== */

Route::middleware('firebase.admin')->prefix('admin')->group(function () {
    // Configuration
    Route::get('/config', [AdminController::class, 'getConfig']);
    Route::put('/config', [AdminController::class, 'updateConfig']);

    // Bot management
    Route::post('/validate-bot', [AdminController::class, 'validateBot']);
    Route::post('/get-chat-id', [AdminController::class, 'getChatId']);
    Route::post('/test-notification', [AdminController::class, 'sendTest']);

    // Templates
    Route::get('/templates', [TemplateController::class, 'index']);
    Route::put('/templates/{event}', [TemplateController::class, 'update']);

    // Logs
    Route::get('/logs', [LogController::class, 'index']);

    // Queue management
    Route::get('/queue-stats', [QueueController::class, 'stats']);
    Route::post('/reprocess-dead-letters', [QueueController::class, 'reprocess']);

    // Subscriber stats
    Route::get('/subscriber-stats', [SubscriberController::class, 'stats']);

    // Campaigns
    Route::get('/campaigns', [CampaignController::class, 'index']);
    Route::post('/campaigns', [CampaignController::class, 'create']);
    Route::get('/campaigns/{id}', [CampaignController::class, 'show']);
    Route::post('/campaigns/{id}/cancel', [CampaignController::class, 'cancel']);

    // Telegram groups (static routes first to avoid {id} catch)
    Route::get('/groups', [GroupController::class, 'index']);
    Route::post('/groups', [GroupController::class, 'store']);
    Route::post('/groups/seed', [GroupController::class, 'seed']);
    Route::post('/groups/generate-continent', [GroupController::class, 'generateContinent']);
    Route::post('/groups/generate-language', [GroupController::class, 'generateLanguage']);
    Route::put('/groups/{id}', [GroupController::class, 'update']);
    Route::delete('/groups/{id}', [GroupController::class, 'destroy']);
    Route::post('/groups/{id}/managers', [GroupController::class, 'addManager']);
    Route::delete('/groups/{id}/managers/{managerId}', [GroupController::class, 'removeManager']);

    // Bot management
    Route::get('/bots', [BotManagementController::class, 'index']);
    Route::put('/bots/{id}', [BotManagementController::class, 'update']);
    Route::post('/bots/{id}/validate', [BotManagementController::class, 'validateBot']);
    Route::post('/bots/{id}/test', [BotManagementController::class, 'test']);

    // Marketing resources (admin CRUD)
    Route::prefix('marketing')->group(function () {
        Route::get('/resources', [AdminResourceController::class, 'index']);
        Route::post('/resources', [AdminResourceController::class, 'store']);
        Route::post('/resources/upload', [AdminResourceController::class, 'upload']);
        Route::patch('/resources/bulk', [AdminResourceController::class, 'bulk']);
        Route::patch('/resources/reorder', [AdminResourceController::class, 'reorder']);
        Route::get('/resources/stats', [AdminResourceController::class, 'stats']);
        Route::put('/resources/{id}', [AdminResourceController::class, 'update']);
        Route::delete('/resources/{id}', [AdminResourceController::class, 'destroy']);
    });
});

/* ==========================================================================
 * Marketing resources for affiliates (secured by Firebase Auth + role)
 * ======================================================================== */

Route::middleware(['firebase.affiliate', 'throttle:120,1'])->prefix('marketing')->group(function () {
    Route::get('/resources', [AffiliateResourceController::class, 'index']);
    Route::post('/resources/{id}/download', [AffiliateResourceController::class, 'download']);
    Route::post('/resources/{id}/copy', [AffiliateResourceController::class, 'copy']);
    Route::get('/resources/{id}/processed', [AffiliateResourceController::class, 'processed']);
});

/* ==========================================================================
 * Press resources (public — no auth)
 * ======================================================================== */

Route::middleware('throttle:60,1')->prefix('press')->group(function () {
    Route::get('/resources', [PressResourceController::class, 'index']);
    Route::get('/resources/{id}/download', [PressResourceController::class, 'download']);
});

/* ==========================================================================
 * Job Ads Tracker (secured by API secret — same as Firebase webhooks)
 * ======================================================================== */

Route::middleware('firebase.webhook')->prefix('job-tracker')->group(function () {
    // Stats
    Route::get('/stats', [JobTrackerController::class, 'stats']);

    // Ads — static routes FIRST (before {id} catch-all)
    Route::get('/ads', [JobTrackerController::class, 'listAds']);
    Route::post('/ads', [JobTrackerController::class, 'createAd']);
    Route::post('/ads/bulk-status', [JobTrackerController::class, 'bulkUpdateStatus']);
    Route::post('/ads/bulk-trash', [JobTrackerController::class, 'bulkTrash']);
    Route::post('/ads/import', [JobTrackerController::class, 'importAds']);
    Route::delete('/trash/empty', [JobTrackerController::class, 'emptyTrash']);

    // Ads — parametric routes AFTER static routes
    Route::put('/ads/{id}', [JobTrackerController::class, 'updateAd']);
    Route::delete('/ads/{id}/trash', [JobTrackerController::class, 'trashAd']);
    Route::post('/ads/{id}/restore', [JobTrackerController::class, 'restoreAd']);
    Route::delete('/ads/{id}', [JobTrackerController::class, 'destroyAd']);

    // Sites — static routes first
    Route::get('/sites', [JobTrackerController::class, 'listSites']);
    Route::post('/sites', [JobTrackerController::class, 'createSite']);
    Route::put('/sites/{id}', [JobTrackerController::class, 'updateSite']);
    Route::delete('/sites/{id}', [JobTrackerController::class, 'destroySite']);
});
