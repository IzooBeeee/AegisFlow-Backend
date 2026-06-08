<?php

/**
 * Firebase Configuration
 * 
 * Cấu hình Firebase Cloud Messaging cho Laravel Backend
 * 
 * Cách cài đặt:
 * 1. Tạo project Firebase tại https://console.firebase.google.com
 * 2. Thêm Android/iOS app vào project
 * 3. Lấy Server Key từ Project Settings > Cloud Messaging
 * 4. Điền các giá trị vào .env
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Server Key
    |--------------------------------------------------------------------------
    |
    | Lấy từ: Firebase Console > Project Settings > Cloud Messaging > Server Key
    | Hoặc: Firebase Console > Project Settings > Cloud Messaging > Web Push Certificates
    |
    */
    'server_key' => env('FIREBASE_SERVER_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Firebase Project ID
    |--------------------------------------------------------------------------
    |
    | Lấy từ: Firebase Console > Project Settings > General
    | Ví dụ: aegisflow-ai
    |
    */
    'project_id' => env('FIREBASE_PROJECT_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Firebase Credentials File
    |--------------------------------------------------------------------------
    |
    | Đường dẫn đến firebase_credentials.json
    | Tải từ: Firebase Console > Project Settings > Service Accounts > Generate new private key
    |
    */
    'credentials_path' => env('FIREBASE_CREDENTIALS_PATH', base_path('firebase-credentials.json')),

    /*
    |--------------------------------------------------------------------------
    | Default Notification Channel
    |--------------------------------------------------------------------------
    |
    | Android Notification Channel ID
    | Phải tạo channel này trong Android app
    |
    */
    'android_channel_id' => env('FIREBASE_ANDROID_CHANNEL_ID', 'aegisflow_alerts'),

    /*
    |--------------------------------------------------------------------------
    | Topics mặc định
    |--------------------------------------------------------------------------
    */
    'topics' => [
        'flood_warnings' => 'flood_warnings',
        'emergency_alerts' => 'emergency_alerts',
        'all_users' => 'all_users',
    ],

    /*
    |--------------------------------------------------------------------------
    | API Endpoints
    |--------------------------------------------------------------------------
    */
    'api' => [
        'fcm_send' => 'https://fcm.googleapis.com/fcm/send',
        'iid_subscribe' => 'https://iid.googleapis.com/iid/v1:batchAdd',
        'iid_unsubscribe' => 'https://iid.googleapis.com/iid/v1:batchRemove',
    ],
];
