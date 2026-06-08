<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services — AI & OAuth
    |--------------------------------------------------------------------------
    */

    'ai' => [
        'url' => env('AI_SERVICE_URL', 'http://localhost:5005'),
        'key' => env('AI_SERVICE_KEY'),
        'timeout' => env('AI_PREDICTION_TIMEOUT', 30),
        'endpoints' => [
            'predict_flood' => '/api/predict/flood',
            'predict_rainfall' => '/api/predict-risk',
            'simulate' => '/api/simulate',
        ],
    ],

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_MODEL', 'llama-3.1-8b-instant'),
        'base_url' => 'https://api.groq.com/openai/v1',
        'timeout' => 30,
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URL'),
    ],

    'fcm' => [
        'server_key' => env('FCM_SERVER_KEY'),
        'project_id' => env('FCM_PROJECT_ID'),
    ],

    'openweather' => [
        'key' => env('OPENWEATHER_API_KEY', ''),
        'base_url' => 'https://api.openweathermap.org/data/2.5',
    ],

    'openmap' => [
        'key' => env('OPENMAP_API_KEY', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Flood Thresholds — Ngưỡng cảnh báo
    |--------------------------------------------------------------------------
    */

    'flood' => [
        'alert_threshold_m' => (float) env('FLOOD_ALERT_THRESHOLD_M', 1.5),
        'danger_threshold_m' => (float) env('FLOOD_DANGER_THRESHOLD_M', 3.0),
        'flood_threshold_m' => (float) env('FLOOD_FLOOD_THRESHOLD_M', 5.0),
        'sensor_reading_interval' => (int) env('SENSOR_READING_INTERVAL', 300),
        'sensor_offline_timeout' => (int) env('SENSOR_OFFLINE_TIMEOUT', 600),
        'prediction_interval_minutes' => 15,
        'max_prediction_horizon_hours' => 24,
    ],

];
