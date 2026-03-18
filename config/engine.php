<?php

return [
    'api_secret' => env('ENGINE_API_SECRET'),
    'frontend_url' => env('FRONTEND_URL', 'https://sos-expat.com'),

    // Firebase project
    'firebase_project_id' => env('FIREBASE_PROJECT_ID', 'sos-urgently-ac307'),

    // Anthropic API for CV analysis
    'anthropic_api_key' => env('ANTHROPIC_API_KEY', ''),
    'cv_analysis_model' => env('CV_ANALYSIS_MODEL', 'claude-sonnet-4-20250514'),
];
