/**
 * Telegram Engine — Firebase Cloud Function Stubs
 *
 * These stubs replace the old monolithic Telegram functions.
 * Each one simply forwards events via HTTP POST to the Laravel API
 * at https://telegram.life-expat.com/api/
 *
 * USAGE: Import and re-export from your main index.ts:
 *   export { telegramOnUserRegistration, telegramOnCallCompleted, ... } from './telegramStubs';
 */

import { onDocumentCreated, onDocumentUpdated, onDocumentWritten } from "firebase-functions/v2/firestore";
import { onSchedule } from "firebase-functions/v2/scheduler";
import { onCall, HttpsError } from "firebase-functions/v2/https";
import { defineSecret } from "firebase-functions/params";
import { logger } from "firebase-functions";

// ============================================================================
// CONFIG
// ============================================================================

const ENGINE_API_SECRET = defineSecret("ENGINE_API_SECRET");
const API_BASE = "https://telegram.life-expat.com/api";

const TRIGGER_REGION = "europe-west3" as const;
const CALLABLE_REGION = "europe-west1" as const;

/** Minimal trigger config — lightweight forwarding stubs */
const stubTriggerConfig = {
  region: TRIGGER_REGION,
  memory: "256MiB" as const,
  cpu: 0.083,
  maxInstances: 10,
  minInstances: 0,
  concurrency: 1,
};

/** Minimal callable config */
const stubCallableConfig = {
  region: CALLABLE_REGION,
  memory: "256MiB" as const,
  cpu: 0.083,
  maxInstances: 5,
  minInstances: 0,
  concurrency: 1,
  cors: [
    "https://sos-expat.com",
    "https://www.sos-expat.com",
    "https://ia.sos-expat.com",
    "http://localhost:5173",
    "http://localhost:5174",
  ],
};

/** Minimal scheduled config */
const stubScheduledConfig = {
  region: TRIGGER_REGION,
  memory: "256MiB" as const,
  cpu: 0.083,
  maxInstances: 1,
  minInstances: 0,
  concurrency: 1,
};

// ============================================================================
// HELPERS
// ============================================================================

async function forwardToEngine(
  path: string,
  method: "GET" | "POST" | "PUT",
  body?: unknown,
  secret?: string
): Promise<unknown> {
  try {
    const res = await fetch(`${API_BASE}${path}`, {
      method,
      headers: {
        "Content-Type": "application/json",
        "X-Engine-Secret": secret || "",
      },
      body: method !== "GET" ? JSON.stringify(body ?? {}) : undefined,
    });
    const data = await res.json();
    if (!res.ok) {
      logger.error(`Engine API error ${res.status} on ${path}`, data);
    }
    return data;
  } catch (err) {
    logger.error(`Failed to forward to Engine API ${path}`, err);
    return { error: "forwarding_failed" };
  }
}

// ============================================================================
// FIRESTORE TRIGGERS (europe-west3)
// ============================================================================

/** 1. New user registration */
export const telegramOnUserRegistration = onDocumentCreated(
  { ...stubTriggerConfig, document: "users/{userId}", secrets: [ENGINE_API_SECRET] },
  async (event) => {
    await forwardToEngine("/events/user-registered", "POST",
      { userId: event.params.userId, data: event.data?.data() },
      ENGINE_API_SECRET.value());
  }
);

/** 2. Call completed */
export const telegramOnCallCompleted = onDocumentUpdated(
  { ...stubTriggerConfig, document: "call_sessions/{sessionId}", secrets: [ENGINE_API_SECRET] },
  async (event) => {
    const after = event.data?.after.data();
    if (after?.status !== "completed") return;
    await forwardToEngine("/events/call-completed", "POST",
      { sessionId: event.params.sessionId, data: after },
      ENGINE_API_SECRET.value());
  }
);

/** 3. Stripe payment received */
export const telegramOnPaymentReceived = onDocumentWritten(
  { ...stubTriggerConfig, document: "payments/{paymentId}", secrets: [ENGINE_API_SECRET] },
  async (event) => {
    const after = event.data?.after.data();
    if (!after || !["succeeded", "completed"].includes(after.status)) return;
    await forwardToEngine("/events/payment-received", "POST",
      { paymentId: event.params.paymentId, data: after },
      ENGINE_API_SECRET.value());
  }
);

/** 4. PayPal payment received */
export const telegramOnPayPalPaymentReceived = onDocumentWritten(
  { ...stubTriggerConfig, document: "paypal_orders/{orderId}", secrets: [ENGINE_API_SECRET] },
  async (event) => {
    const after = event.data?.after.data();
    if (!after || after.status !== "COMPLETED") return;
    await forwardToEngine("/events/paypal-payment", "POST",
      { orderId: event.params.orderId, data: after },
      ENGINE_API_SECRET.value());
  }
);

/** 5. New provider profile */
export const telegramOnNewProvider = onDocumentCreated(
  { ...stubTriggerConfig, document: "sos_profiles/{profileId}", secrets: [ENGINE_API_SECRET] },
  async (event) => {
    await forwardToEngine("/events/new-provider", "POST",
      { profileId: event.params.profileId, data: event.data?.data() },
      ENGINE_API_SECRET.value());
  }
);

/** 6. New contact message */
export const telegramOnNewContactMessage = onDocumentCreated(
  { ...stubTriggerConfig, document: "contact_messages/{messageId}", secrets: [ENGINE_API_SECRET] },
  async (event) => {
    await forwardToEngine("/events/new-contact-message", "POST",
      { messageId: event.params.messageId, data: event.data?.data() },
      ENGINE_API_SECRET.value());
  }
);

/** 7. Security alert */
export const telegramOnSecurityAlert = onDocumentCreated(
  { ...stubTriggerConfig, document: "security_alerts/{alertId}", secrets: [ENGINE_API_SECRET] },
  async (event) => {
    await forwardToEngine("/events/security-alert", "POST",
      { alertId: event.params.alertId, data: event.data?.data() },
      ENGINE_API_SECRET.value());
  }
);

/** 8. Negative review (rating <= 2) */
export const telegramOnNegativeReview = onDocumentCreated(
  { ...stubTriggerConfig, document: "reviews/{reviewId}", secrets: [ENGINE_API_SECRET] },
  async (event) => {
    const data = event.data?.data();
    if (!data || data.rating > 2) return;
    await forwardToEngine("/events/negative-review", "POST",
      { reviewId: event.params.reviewId, data },
      ENGINE_API_SECRET.value());
  }
);

/** 9. Withdrawal request */
export const telegramOnWithdrawalRequest = onDocumentCreated(
  { ...stubTriggerConfig, document: "payment_withdrawals/{withdrawalId}", secrets: [ENGINE_API_SECRET] },
  async (event) => {
    await forwardToEngine("/events/withdrawal-requested", "POST",
      { withdrawalId: event.params.withdrawalId, data: event.data?.data() },
      ENGINE_API_SECRET.value());
  }
);

/** 10. New captain application */
export const telegramOnNewCaptainApplication = onDocumentCreated(
  { ...stubTriggerConfig, document: "captain_applications/{applicationId}", secrets: [ENGINE_API_SECRET] },
  async (event) => {
    await forwardToEngine("/events/captain-application", "POST",
      { applicationId: event.params.applicationId, data: event.data?.data() },
      ENGINE_API_SECRET.value());
  }
);

// ============================================================================
// SCHEDULED (europe-west3)
// ============================================================================

/** 11. Daily report — every day at 19:00 UTC */
export const telegramDailyReport = onSchedule(
  { ...stubScheduledConfig, schedule: "0 19 * * *", secrets: [ENGINE_API_SECRET] },
  async () => {
    await forwardToEngine("/events/daily-report", "POST", {}, ENGINE_API_SECRET.value());
  }
);

// ============================================================================
// CALLABLE ADMIN FUNCTIONS (europe-west1)
// ============================================================================

/** 12. Send test notification */
export const telegram_sendTestNotification = onCall(
  { ...stubCallableConfig, secrets: [ENGINE_API_SECRET] },
  async (request) => {
    return forwardToEngine("/admin/test-notification", "POST", request.data, ENGINE_API_SECRET.value());
  }
);

/** 13. Update config */
export const telegram_updateConfig = onCall(
  { ...stubCallableConfig, secrets: [ENGINE_API_SECRET] },
  async (request) => {
    return forwardToEngine("/admin/config", "PUT", request.data, ENGINE_API_SECRET.value());
  }
);

/** 14. Get config */
export const telegram_getConfig = onCall(
  { ...stubCallableConfig, secrets: [ENGINE_API_SECRET] },
  async (request) => {
    return forwardToEngine("/admin/config", "GET", undefined, ENGINE_API_SECRET.value());
  }
);

/** 15. Get chat ID */
export const telegram_getChatId = onCall(
  { ...stubCallableConfig, secrets: [ENGINE_API_SECRET] },
  async (request) => {
    return forwardToEngine("/admin/get-chat-id", "POST", request.data, ENGINE_API_SECRET.value());
  }
);

/** 16. Validate bot */
export const telegram_validateBot = onCall(
  { ...stubCallableConfig, secrets: [ENGINE_API_SECRET] },
  async (request) => {
    return forwardToEngine("/admin/validate-bot", "POST", request.data, ENGINE_API_SECRET.value());
  }
);

/** 17. Update template */
export const telegram_updateTemplate = onCall(
  { ...stubCallableConfig, secrets: [ENGINE_API_SECRET] },
  async (request) => {
    const event = request.data?.event;
    return forwardToEngine(`/admin/templates/${event}`, "PUT", request.data, ENGINE_API_SECRET.value());
  }
);

/** 18. Get templates */
export const telegram_getTemplates = onCall(
  { ...stubCallableConfig, secrets: [ENGINE_API_SECRET] },
  async (request) => {
    return forwardToEngine("/admin/templates", "GET", undefined, ENGINE_API_SECRET.value());
  }
);

/** 19. Create campaign */
export const telegram_createCampaign = onCall(
  { ...stubCallableConfig, secrets: [ENGINE_API_SECRET] },
  async (request) => {
    return forwardToEngine("/admin/campaigns", "POST", request.data, ENGINE_API_SECRET.value());
  }
);

/** 20. Get campaigns */
export const telegram_getCampaigns = onCall(
  { ...stubCallableConfig, secrets: [ENGINE_API_SECRET] },
  async (request) => {
    return forwardToEngine("/admin/campaigns", "GET", undefined, ENGINE_API_SECRET.value());
  }
);

/** 21. Update campaign */
export const telegram_updateCampaign = onCall(
  { ...stubCallableConfig, secrets: [ENGINE_API_SECRET] },
  async (request) => {
    const id = request.data?.campaignId;
    return forwardToEngine(`/admin/campaigns/${id}`, "PUT", request.data, ENGINE_API_SECRET.value());
  }
);

/** 22. Delete campaign */
export const telegram_deleteCampaign = onCall(
  { ...stubCallableConfig, secrets: [ENGINE_API_SECRET] },
  async (request) => {
    const id = request.data?.campaignId;
    return forwardToEngine(`/admin/campaigns/${id}`, "POST", { _method: "DELETE" }, ENGINE_API_SECRET.value());
  }
);

/** 23. Get logs */
export const telegram_getLogs = onCall(
  { ...stubCallableConfig, secrets: [ENGINE_API_SECRET] },
  async (request) => {
    return forwardToEngine("/admin/logs", "GET", undefined, ENGINE_API_SECRET.value());
  }
);

/** 24. Get queue stats */
export const telegram_getQueueStats = onCall(
  { ...stubCallableConfig, secrets: [ENGINE_API_SECRET] },
  async (request) => {
    return forwardToEngine("/admin/queue-stats", "GET", undefined, ENGINE_API_SECRET.value());
  }
);

/** 25. Get subscriber stats */
export const telegram_getSubscriberStats = onCall(
  { ...stubCallableConfig, secrets: [ENGINE_API_SECRET] },
  async (request) => {
    return forwardToEngine("/admin/subscriber-stats", "GET", undefined, ENGINE_API_SECRET.value());
  }
);

/** 26. Reprocess failed event */
export const telegram_reprocessEvent = onCall(
  { ...stubCallableConfig, secrets: [ENGINE_API_SECRET] },
  async (request) => {
    return forwardToEngine("/admin/reprocess", "POST", request.data, ENGINE_API_SECRET.value());
  }
);
