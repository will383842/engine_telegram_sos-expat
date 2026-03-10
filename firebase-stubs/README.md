# Firebase Telegram Stubs

Lightweight Cloud Function stubs that forward Firestore events and admin calls to the Laravel Telegram Engine API at `https://telegram.life-expat.com/api/`.

## Architecture

```
Firestore event → Firebase Cloud Function (stub) → HTTP POST → Laravel API
Admin dashboard → callable Cloud Function (stub) → HTTP POST/GET/PUT → Laravel API
```

Each stub is 3-5 lines of logic. No business logic lives here — everything is handled by the Laravel engine.

## Functions Overview

| # | Function | Type | Region | Laravel Route |
|---|----------|------|--------|---------------|
| 1 | `telegramOnUserRegistration` | Trigger (onCreate) | europe-west3 | POST `/api/events/user-registered` |
| 2 | `telegramOnCallCompleted` | Trigger (onUpdate) | europe-west3 | POST `/api/events/call-completed` |
| 3 | `telegramOnPaymentReceived` | Trigger (onWrite) | europe-west3 | POST `/api/events/payment-received` |
| 4 | `telegramOnPayPalPaymentReceived` | Trigger (onWrite) | europe-west3 | POST `/api/events/paypal-payment` |
| 5 | `telegramOnNewProvider` | Trigger (onCreate) | europe-west3 | POST `/api/events/new-provider` |
| 6 | `telegramOnNewContactMessage` | Trigger (onCreate) | europe-west3 | POST `/api/events/new-contact-message` |
| 7 | `telegramOnSecurityAlert` | Trigger (onCreate) | europe-west3 | POST `/api/events/security-alert` |
| 8 | `telegramOnNegativeReview` | Trigger (onCreate) | europe-west3 | POST `/api/events/negative-review` |
| 9 | `telegramOnWithdrawalRequest` | Trigger (onCreate) | europe-west3 | POST `/api/events/withdrawal-requested` |
| 10 | `telegramOnNewCaptainApplication` | Trigger (onCreate) | europe-west3 | POST `/api/events/captain-application` |
| 11 | `telegramDailyReport` | Scheduled (19:00 UTC) | europe-west3 | POST `/api/events/daily-report` |
| 12 | `telegram_sendTestNotification` | Callable | europe-west1 | POST `/api/admin/test-notification` |
| 13 | `telegram_updateConfig` | Callable | europe-west1 | PUT `/api/admin/config` |
| 14 | `telegram_getConfig` | Callable | europe-west1 | GET `/api/admin/config` |
| 15 | `telegram_getChatId` | Callable | europe-west1 | POST `/api/admin/get-chat-id` |
| 16 | `telegram_validateBot` | Callable | europe-west1 | POST `/api/admin/validate-bot` |
| 17 | `telegram_updateTemplate` | Callable | europe-west1 | PUT `/api/admin/templates/{event}` |
| 18 | `telegram_getTemplates` | Callable | europe-west1 | GET `/api/admin/templates` |
| 19 | `telegram_createCampaign` | Callable | europe-west1 | POST `/api/admin/campaigns` |
| 20 | `telegram_getCampaigns` | Callable | europe-west1 | GET `/api/admin/campaigns` |
| 21 | `telegram_updateCampaign` | Callable | europe-west1 | PUT `/api/admin/campaigns/{id}` |
| 22 | `telegram_deleteCampaign` | Callable | europe-west1 | DELETE `/api/admin/campaigns/{id}` |
| 23 | `telegram_getLogs` | Callable | europe-west1 | GET `/api/admin/logs` |
| 24 | `telegram_getQueueStats` | Callable | europe-west1 | GET `/api/admin/queue-stats` |
| 25 | `telegram_getSubscriberStats` | Callable | europe-west1 | GET `/api/admin/subscriber-stats` |
| 26 | `telegram_reprocessEvent` | Callable | europe-west1 | POST `/api/admin/reprocess` |

### Deleted Functions (now handled by Laravel)

These queue/campaign processors are no longer needed as Firebase stubs:

- `processTelegramQueue` — Laravel handles queue processing natively
- `processTelegramCampaigns` — Laravel scheduled commands
- `monitorTelegramUsage` — Laravel monitoring

## Setup

### 1. Set the Engine API Secret

```bash
firebase functions:secrets:set ENGINE_API_SECRET
# Enter the shared secret that matches TELEGRAM_ENGINE_SECRET in Laravel's .env
```

### 2. Import in your index.ts

```typescript
// In sos/firebase/functions/src/index.ts (or wherever you export functions)

export {
  // Triggers
  telegramOnUserRegistration,
  telegramOnCallCompleted,
  telegramOnPaymentReceived,
  telegramOnPayPalPaymentReceived,
  telegramOnNewProvider,
  telegramOnNewContactMessage,
  telegramOnSecurityAlert,
  telegramOnNegativeReview,
  telegramOnWithdrawalRequest,
  telegramOnNewCaptainApplication,
  // Scheduled
  telegramDailyReport,
  // Admin callables
  telegram_sendTestNotification,
  telegram_updateConfig,
  telegram_getConfig,
  telegram_getChatId,
  telegram_validateBot,
  telegram_updateTemplate,
  telegram_getTemplates,
  telegram_createCampaign,
  telegram_getCampaigns,
  telegram_updateCampaign,
  telegram_deleteCampaign,
  telegram_getLogs,
  telegram_getQueueStats,
  telegram_getSubscriberStats,
  telegram_reprocessEvent,
} from './telegramStubs';
```

### 3. Deploy

```bash
cd sos/firebase/functions
rm -rf lib && npm run build
firebase deploy --only functions:telegramOnUserRegistration,functions:telegramOnCallCompleted,...
```

Or deploy all at once:

```bash
firebase deploy --only functions
```

## Security

- All requests include the `X-Engine-Secret` header
- The secret is stored in Firebase Secret Manager (`ENGINE_API_SECRET`)
- The Laravel API validates this header via middleware before processing any event
- Trigger stubs filter events before forwarding (e.g., only completed calls, only negative reviews)

## Payload Format

Every trigger forwards a JSON body with:

```json
{
  "userId": "abc123",       // or sessionId, paymentId, etc. (the document path param)
  "data": { ... }           // the Firestore document snapshot data
}
```

Callable stubs forward `request.data` as-is and return the Laravel API response directly.

## Laravel API Side

The Laravel API must implement matching routes. Example for the events middleware group:

```php
// routes/api.php
Route::prefix('events')->middleware('verify-engine-secret')->group(function () {
    Route::post('user-registered', [EventController::class, 'userRegistered']);
    Route::post('call-completed', [EventController::class, 'callCompleted']);
    Route::post('payment-received', [EventController::class, 'paymentReceived']);
    // ... etc.
});

Route::prefix('admin')->middleware('verify-engine-secret')->group(function () {
    Route::get('config', [AdminController::class, 'getConfig']);
    Route::put('config', [AdminController::class, 'updateConfig']);
    // ... etc.
});
```
