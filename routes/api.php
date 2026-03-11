<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\BotController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\WithdrawalController;
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
});

/* ==========================================================================
 * Telegram Bot webhook (secured by Telegram secret)
 * ======================================================================== */

Route::post('/bot/webhook', [BotController::class, 'handleWebhook'])
    ->middleware('telegram.verify');

// Onboarding bot webhook (separate bot for user registration)
Route::post('/bot/onboarding-webhook', [BotController::class, 'handleOnboardingWebhook']);

/* ==========================================================================
 * Onboarding (secured by Firebase Auth token)
 * ======================================================================== */

Route::middleware('firebase.token')->prefix('onboarding')->group(function () {
    Route::post('/generate-link', [OnboardingController::class, 'generateLink']);
    Route::get('/check-status', [OnboardingController::class, 'checkStatus']);
    Route::post('/skip', [OnboardingController::class, 'skip']);
});

/* ==========================================================================
 * Withdrawal confirmation (secured by Firebase Auth token)
 * ======================================================================== */

Route::middleware('firebase.token')->prefix('withdrawal')->group(function () {
    Route::get('/confirmation-status/{id}', [WithdrawalController::class, 'getStatus']);
    Route::post('/cancel/{id}', [WithdrawalController::class, 'cancel']);
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
});
