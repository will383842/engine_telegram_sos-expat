<?php

return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),

    // Rate limiting
    'rate_limit' => [
        'max_messages_per_second' => 30,
        'batch_size' => 25,
        'send_delay_ms' => 50,
    ],

    // Queue
    'queue' => [
        'max_retries' => 3,
        'backoff_seconds' => [5, 10, 20],
        'cleanup_days' => 7,
        'alert_depth' => 100,
    ],

    // Message
    'max_message_length' => 4096,

    // Withdrawal confirmation
    'withdrawal' => [
        'expiry_minutes' => 15,
    ],

    // Onboarding
    'onboarding' => [
        'link_expiry_hours' => 24,
        'bonus_amount_cents' => 5000,       // $50
        'unlock_threshold_cents' => 15000,   // $150
    ],

    // Daily report timezone
    'timezone' => 'Europe/Paris',

    // Notification event types (12 total)
    'events' => [
        'new_registration',
        'call_completed',
        'payment_received',
        'daily_report',
        'new_provider',
        'new_contact_message',
        'negative_review',
        'security_alert',
        'withdrawal_request',
        'captain_application',
        'user_feedback',
        'partner_application',
    ],

    // Supported languages
    'languages' => ['fr', 'en', 'es', 'de', 'pt', 'ru', 'zh', 'hi', 'ar'],
];
