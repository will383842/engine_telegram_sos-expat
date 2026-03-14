<?php

return [
    // Trustpilot business profile URL
    'profile_url' => env('TRUSTPILOT_PROFILE_URL', 'https://www.trustpilot.com/review/sos-expat.com'),

    // Schedule: day of week (0=Sunday, 1=Monday, ..., 6=Saturday)
    'report_day' => (int) env('TRUSTPILOT_REPORT_DAY', 1), // Monday

    // Schedule: time (HH:MM, Europe/Paris timezone)
    'report_time' => env('TRUSTPILOT_REPORT_TIME', '09:00'),

    // Bot slug to send the report through
    'bot_slug' => env('TRUSTPILOT_BOT_SLUG', 'main'),

    // HTTP request timeout in seconds
    'timeout' => 30,

    // User-Agent for the scraping request
    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
];
