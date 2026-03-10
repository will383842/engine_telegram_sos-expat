<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AdminConfig;
use App\Services\MessageQueueService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly MessageQueueService $messageQueueService,
    ) {}

    /* ==================================================================
     * Event handlers
     * ================================================================ */

    /**
     * A new user registered on the platform.
     * Event type: new_registration
     */
    public function userRegistered(Request $request): JsonResponse
    {
        $data = $request->input('data', $request->all());
        $now = Carbon::now('Europe/Paris');

        $variables = [
            'ROLE_FR'  => $this->translateRole($data['role'] ?? 'unknown'),
            'EMAIL'    => $data['email'] ?? 'N/A',
            'PHONE'    => $data['phone'] ?? $data['phoneNumber'] ?? 'N/A',
            'COUNTRY'  => $data['country'] ?? 'N/A',
            'DATE'     => $now->format('d/m/Y'),
            'TIME'     => $now->format('H:i'),
        ];

        $this->notificationService->sendNotification('new_registration', $variables);

        return response()->json(['success' => true]);
    }

    /**
     * A call was completed between a client and a provider.
     * Event type: call_completed
     */
    public function callCompleted(Request $request): JsonResponse
    {
        $data = $request->input('data', $request->all());
        $now = Carbon::now('Europe/Paris');

        $variables = [
            'CLIENT_NAME'      => $data['clientName'] ?? 'N/A',
            'PROVIDER_NAME'    => $data['providerName'] ?? 'N/A',
            'PROVIDER_TYPE_FR' => $this->translateRole($data['providerType'] ?? $data['providerRole'] ?? 'unknown'),
            'DURATION_MINUTES' => (string) round(($data['duration'] ?? $data['durationSeconds'] ?? 0) / 60, 1),
            'DATE'             => $now->format('d/m/Y'),
            'TIME'             => $now->format('H:i'),
        ];

        $this->notificationService->sendNotification('call_completed', $variables);

        return response()->json(['success' => true]);
    }

    /**
     * A Stripe payment was received.
     * Event type: payment_received
     */
    public function paymentReceived(Request $request): JsonResponse
    {
        $data = $request->input('data', $request->all());
        $now = Carbon::now('Europe/Paris');

        $variables = [
            'TOTAL_AMOUNT'      => number_format(($data['amount'] ?? $data['amountCents'] ?? 0) / 100, 2),
            'COMMISSION_AMOUNT' => number_format(($data['commission'] ?? $data['commissionAmount'] ?? 0) / 100, 2),
            'DATE'              => $now->format('d/m/Y'),
            'TIME'              => $now->format('H:i'),
        ];

        $this->notificationService->sendNotification('payment_received', $variables);

        return response()->json(['success' => true]);
    }

    /**
     * A PayPal payment was received.
     * Reuses the payment_received template.
     */
    public function paypalPayment(Request $request): JsonResponse
    {
        $data = $request->input('data', $request->all());
        $now = Carbon::now('Europe/Paris');

        $variables = [
            'TOTAL_AMOUNT'      => number_format(($data['amount'] ?? $data['amountCents'] ?? 0) / 100, 2),
            'COMMISSION_AMOUNT' => number_format(($data['commission'] ?? $data['commissionAmount'] ?? 0) / 100, 2),
            'DATE'              => $now->format('d/m/Y'),
            'TIME'              => $now->format('H:i'),
        ];

        $this->notificationService->sendNotification('payment_received', $variables);

        return response()->json(['success' => true]);
    }

    /**
     * A new provider registered on the platform.
     * Event type: new_provider
     */
    public function newProvider(Request $request): JsonResponse
    {
        $data = $request->input('data', $request->all());
        $now = Carbon::now('Europe/Paris');

        $variables = [
            'PROVIDER_NAME'    => trim(($data['firstName'] ?? '') . ' ' . ($data['lastName'] ?? '')) ?: 'N/A',
            'PROVIDER_TYPE_FR' => $this->translateRole($data['providerType'] ?? $data['role'] ?? 'unknown'),
            'EMAIL'            => $data['email'] ?? 'N/A',
            'PHONE'            => $data['phone'] ?? $data['phoneNumber'] ?? 'N/A',
            'COUNTRY'          => $data['country'] ?? 'N/A',
            'DATE'             => $now->format('d/m/Y'),
            'TIME'             => $now->format('H:i'),
        ];

        $this->notificationService->sendNotification('new_provider', $variables);

        return response()->json(['success' => true]);
    }

    /**
     * A contact message was submitted via the website.
     * Event type: new_contact_message
     */
    public function newContactMessage(Request $request): JsonResponse
    {
        $data = $request->input('data', $request->all());
        $now = Carbon::now('Europe/Paris');

        $variables = [
            'SENDER_NAME'     => $data['name'] ?? $data['senderName'] ?? 'N/A',
            'SENDER_EMAIL'    => $data['email'] ?? $data['senderEmail'] ?? 'N/A',
            'SUBJECT'         => $data['subject'] ?? 'N/A',
            'MESSAGE_PREVIEW' => mb_substr($data['message'] ?? '', 0, 200),
            'DATE'            => $now->format('d/m/Y'),
            'TIME'            => $now->format('H:i'),
        ];

        $this->notificationService->sendNotification('new_contact_message', $variables);

        return response()->json(['success' => true]);
    }

    /**
     * A security alert was triggered (suspicious login, etc.).
     * Event type: security_alert
     */
    public function securityAlert(Request $request): JsonResponse
    {
        $data = $request->input('data', $request->all());
        $now = Carbon::now('Europe/Paris');

        $variables = [
            'ALERT_TYPE_FR' => $data['alertType'] ?? $data['type'] ?? 'Alerte',
            'USER_EMAIL'    => $data['userEmail'] ?? $data['email'] ?? 'N/A',
            'IP_ADDRESS'    => $data['ipAddress'] ?? $data['ip'] ?? 'N/A',
            'COUNTRY'       => $data['country'] ?? 'N/A',
            'DETAILS'       => mb_substr($data['details'] ?? $data['description'] ?? '', 0, 300),
            'DATE'          => $now->format('d/m/Y'),
            'TIME'          => $now->format('H:i'),
        ];

        $this->notificationService->sendNotification('security_alert', $variables);

        return response()->json(['success' => true]);
    }

    /**
     * A provider received a negative review.
     * Event type: negative_review
     */
    public function negativeReview(Request $request): JsonResponse
    {
        $data = $request->input('data', $request->all());
        $now = Carbon::now('Europe/Paris');

        $variables = [
            'CLIENT_NAME'     => $data['clientName'] ?? 'N/A',
            'PROVIDER_NAME'   => $data['providerName'] ?? 'N/A',
            'RATING'          => (string) ($data['rating'] ?? 'N/A'),
            'COMMENT_PREVIEW' => mb_substr($data['comment'] ?? '', 0, 200),
            'DATE'            => $now->format('d/m/Y'),
            'TIME'            => $now->format('H:i'),
        ];

        $this->notificationService->sendNotification('negative_review', $variables);

        return response()->json(['success' => true]);
    }

    /**
     * An affiliate requested a withdrawal.
     * Event type: withdrawal_request
     */
    public function withdrawalRequested(Request $request): JsonResponse
    {
        $data = $request->input('data', $request->all());
        $now = Carbon::now('Europe/Paris');

        $variables = [
            'USER_NAME'       => $data['userName'] ?? $data['name'] ?? 'N/A',
            'USER_TYPE_FR'    => $this->translateRole($data['userType'] ?? $data['role'] ?? 'unknown'),
            'AMOUNT'          => number_format(($data['amount'] ?? $data['amountCents'] ?? 0) / 100, 2) . '$',
            'PAYMENT_METHOD'  => $data['paymentMethod'] ?? 'N/A',
            'PAYMENT_DETAILS' => $data['paymentDetails'] ?? 'N/A',
            'COUNTRY'         => $data['country'] ?? 'N/A',
            'ADMIN_URL'       => $data['adminUrl'] ?? 'https://sos-expat.com/admin/payments',
            'DATE'            => $now->format('d/m/Y'),
            'TIME'            => $now->format('H:i'),
        ];

        $this->notificationService->sendNotification('withdrawal_request', $variables);

        return response()->json(['success' => true]);
    }

    /**
     * Someone applied to become a Captain (team leader).
     * Event type: captain_application
     */
    public function captainApplication(Request $request): JsonResponse
    {
        $data = $request->input('data', $request->all());
        $now = Carbon::now('Europe/Paris');

        $variables = [
            'CANDIDATE_NAME'     => $data['candidateName'] ?? $data['name'] ?? 'N/A',
            'WHATSAPP'           => $data['whatsapp'] ?? $data['phone'] ?? 'N/A',
            'COUNTRY'            => $data['country'] ?? 'N/A',
            'MOTIVATION_PREVIEW' => mb_substr($data['motivation'] ?? '', 0, 200),
            'HAS_CV'             => ($data['hasCv'] ?? false) ? 'Oui' : 'Non',
            'DATE'               => $now->format('d/m/Y'),
            'TIME'               => $now->format('H:i'),
        ];

        $this->notificationService->sendNotification('captain_application', $variables);

        return response()->json(['success' => true]);
    }

    /**
     * A withdrawal status changed (approved, failed, etc.).
     * Sends an inline message directly (no template needed).
     */
    public function withdrawalStatusChanged(Request $request): JsonResponse
    {
        $data = $request->input('data', $request->all());
        $now = Carbon::now('Europe/Paris');

        $statusLabels = [
            'pending'    => '⏳ En attente',
            'validating' => '🔍 Validation',
            'approved'   => '✅ Approuve',
            'processing' => '⚙️ En cours',
            'completed'  => '✅ Termine',
            'failed'     => '❌ Echoue',
            'cancelled'  => '🚫 Annule',
        ];

        $status = $data['status'] ?? 'unknown';
        $amount = number_format(($data['amount'] ?? 0) / 100, 2);
        $label = $statusLabels[$status] ?? $status;

        $message = "💸 Mise a jour retrait\n\n"
            . "Statut: {$label}\n"
            . "Montant: {$amount}$\n"
            . "📅 {$now->format('d/m/Y')} a {$now->format('H:i')}";

        $chatId = AdminConfig::current()->recipient_chat_id ?? '';

        if (!empty($chatId)) {
            $this->messageQueueService->enqueue(
                chatId: $chatId,
                message: $message,
                source: 'withdrawal_status_changed',
            );
        }

        return response()->json(['success' => true]);
    }

    /* ==================================================================
     * Helpers
     * ================================================================ */

    /**
     * Translate a role code to a French label.
     */
    private function translateRole(string $role): string
    {
        return match (strtolower($role)) {
            'chatter'                  => 'Chatter',
            'influencer'               => 'Influenceur',
            'blogger'                  => 'Blogueur',
            'groupadmin', 'group_admin' => 'Admin Groupe',
            'partner'                  => 'Partenaire',
            'lawyer'                   => 'Avocat',
            'expat'                    => 'Expert expatriation',
            'client'                   => 'Client',
            'admin'                    => 'Administrateur',
            default                    => ucfirst($role),
        };
    }
}
