<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\NotificationTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    /**
     * Default template variable definitions per event type.
     */
    private const EVENT_VARIABLES = [
        'new_registration'   => ['ROLE_FR', 'EMAIL', 'PHONE', 'COUNTRY', 'DATE', 'TIME'],
        'call_completed'     => ['CLIENT_NAME', 'PROVIDER_NAME', 'PROVIDER_TYPE_FR', 'DURATION_MINUTES', 'TOTAL_AMOUNT', 'COMMISSION_AMOUNT', 'DATE', 'TIME'],
        'payment_received'   => ['CLIENT_NAME', 'PROVIDER_NAME', 'TOTAL_AMOUNT', 'COMMISSION_AMOUNT', 'DATE', 'TIME'],
        'daily_report'       => ['DAILY_CA', 'DAILY_COMMISSION', 'REGISTRATION_COUNT', 'CALL_COUNT', 'DATE'],
        'new_provider'       => ['PROVIDER_NAME', 'PROVIDER_TYPE_FR', 'COUNTRY', 'DATE', 'TIME'],
        'new_contact_message'=> ['SENDER_NAME', 'SENDER_EMAIL', 'SUBJECT', 'MESSAGE_PREVIEW', 'DATE', 'TIME'],
        'negative_review'    => ['CLIENT_NAME', 'PROVIDER_NAME', 'RATING', 'COMMENT_PREVIEW', 'DATE', 'TIME'],
        'security_alert'     => ['ALERT_TYPE_FR', 'USER_EMAIL', 'IP_ADDRESS', 'COUNTRY', 'DETAILS', 'DATE', 'TIME'],
        'withdrawal_request' => ['USER_NAME', 'USER_TYPE_FR', 'AMOUNT', 'PAYMENT_METHOD', 'DATE', 'TIME'],
        'captain_application'=> ['CANDIDATE_NAME', 'WHATSAPP', 'COUNTRY', 'MOTIVATION_PREVIEW', 'HAS_CV', 'DATE', 'TIME'],
    ];

    /**
     * Return all templates as a flat Record<eventId, TemplateData>.
     *
     * Frontend expects: { templates: { [eventId]: { eventId, enabled, template, variables, updatedAt, isDefault } } }
     */
    public function index(): JsonResponse
    {
        $dbTemplates = NotificationTemplate::all();

        // Group by event_type, take first (or primary language)
        $byEvent = [];
        foreach ($dbTemplates as $t) {
            // Keep one template per event_type (prefer custom over default)
            if (!isset($byEvent[$t->event_type]) || $t->is_custom) {
                $byEvent[$t->event_type] = $t;
            }
        }

        // Build flat output matching frontend TemplateData interface
        $templates = [];

        // Include all known event types even if no DB template exists
        $allEventTypes = array_unique(array_merge(
            array_keys(self::EVENT_VARIABLES),
            array_keys($byEvent),
        ));

        foreach ($allEventTypes as $eventType) {
            $t = $byEvent[$eventType] ?? null;
            $templates[$eventType] = [
                'eventId'   => $eventType,
                'enabled'   => true, // Default enabled; could be stored in AdminConfig notifications
                'template'  => $t?->template ?? '',
                'variables' => self::EVENT_VARIABLES[$eventType] ?? [],
                'updatedAt' => $t?->updated_at?->toIso8601String(),
                'isDefault' => $t === null || !$t->is_custom,
            ];
        }

        return response()->json([
            'templates' => $templates,
        ]);
    }

    /**
     * Create or update a template for a given event type.
     *
     * Frontend sends: { template?: string, enabled?: boolean }
     */
    public function update(string $event, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template' => 'nullable|string|max:4096',
            'enabled'  => 'nullable|boolean',
        ]);

        $data = ['is_custom' => true];
        if (isset($validated['template'])) {
            $data['template'] = $validated['template'];
        }

        $template = NotificationTemplate::updateOrCreate(
            [
                'event_type' => $event,
                'language'   => 'fr', // Default language for admin UI
            ],
            $data,
        );

        return response()->json([
            'success'  => true,
            'template' => [
                'eventId'   => $template->event_type,
                'enabled'   => true,
                'template'  => $template->template,
                'variables' => self::EVENT_VARIABLES[$event] ?? [],
                'updatedAt' => $template->updated_at?->toIso8601String(),
                'isDefault' => !$template->is_custom,
            ],
        ]);
    }
}
