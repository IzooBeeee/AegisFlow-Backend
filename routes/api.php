<?php

use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\SystemController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\AIChatController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EvacuationRouteController;
use App\Http\Controllers\Api\FcmTokenController;
use App\Http\Controllers\Api\FloodZoneController;
use App\Http\Controllers\Api\IncidentController;
use App\Http\Controllers\Api\MapController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PredictionController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\RescueRequestController;
use App\Http\Controllers\Api\RescueTeamController;
use App\Http\Controllers\Api\SensorController;
use App\Http\Controllers\Api\SensorDataController;
use App\Http\Controllers\Api\ShelterController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\WeatherDataController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — AegisFlow AI
|--------------------------------------------------------------------------
*/

// ============================================================
// PUBLIC ROUTES (Không cần đăng nhập)
// ============================================================
Route::prefix('public')->group(function () {
    Route::get('incidents', [IncidentController::class, 'publicList']);
    Route::get('flood-zones/geojson', [FloodZoneController::class, 'geojson']);
    Route::get('alerts/geojson', [AlertController::class, 'geojson']);
    Route::get('map/shelters', [MapController::class, 'shelters']);
});

Route::prefix('map/geocode')->group(function () {
    Route::get('forward', [MapController::class, 'geocodeForward']);
    Route::get('reverse', [MapController::class, 'geocodeReverse']);
});

// ============================================================
// AUTH ROUTES
// ============================================================
Route::prefix('auth')->group(function () {
    Route::middleware('throttle:auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('register', [AuthController::class, 'register']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('accept-otp-password', [AuthController::class, 'verifyOtp']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
        Route::post('verify-email', [AuthController::class, 'verifyEmail']);
        Route::post('resend-otp', [AuthController::class, 'resendOtp']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
        Route::post('avatar', [AuthController::class, 'uploadAvatar']);
        Route::delete('account', [AuthController::class, 'deleteAccount']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
    });
});

// ============================================================
// AUTHENTICATED ROUTES (Cần đăng nhập)
// ============================================================
Route::middleware('auth:sanctum')->group(function () {

    // ── Incidents ──────────────────────────────────────────
    Route::apiResource('incidents', IncidentController::class)
        ->only('index', 'show', 'store', 'update');

    // ── Rescue Requests ─────────────────────────────────────
    Route::get('rescue-requests/pending', [RescueRequestController::class, 'pending']);
    Route::put('rescue-requests/{id}/assign', [RescueRequestController::class, 'assign']);
    Route::put('rescue-requests/{id}/status', [RescueRequestController::class, 'updateStatus']);
    Route::post('rescue-requests/{id}/rate', [RescueRequestController::class, 'rate']);
    Route::apiResource('rescue-requests', RescueRequestController::class)
        ->only('index', 'show', 'store', 'update');

    // ── Flood Zones ─────────────────────────────────────────
    Route::get('flood-zones/geojson', [FloodZoneController::class, 'geojson']);
    Route::apiResource('flood-zones', FloodZoneController::class)
        ->only('index', 'show', 'store', 'update', 'destroy');

    // ── Sensors ─────────────────────────────────────────────
    Route::get('sensors', [SensorController::class, 'index']);
    Route::get('sensors/{id}', [SensorController::class, 'show']);
    Route::get('sensors/{id}/readings', [SensorController::class, 'readings']);

    // ── Weather Data ───────────────────────────────────────
    Route::get('weather/current', [WeatherDataController::class, 'current']);
    Route::get('weather/history', [WeatherDataController::class, 'history']);
    Route::get('weather/summary', [WeatherDataController::class, 'summary']);

    // ── Rescue Teams ────────────────────────────────────────
    Route::apiResource('rescue-teams', RescueTeamController::class)
        ->only('index', 'show');
    Route::put('rescue-teams/{id}/location', [RescueTeamController::class, 'updateLocation']);

    // ── Upload ────────────────────────────────────────────
    Route::post('upload', [UploadController::class, 'store']);

    // ── Shelters ───────────────────────────────────────────
    Route::apiResource('shelters', ShelterController::class)
        ->only('index', 'show', 'store');
    Route::put('shelters/{id}/occupancy', [ShelterController::class, 'updateOccupancy']);

    // ── Predictions ─────────────────────────────────────────
    Route::get('predictions', [PredictionController::class, 'index']);
    Route::get('predictions/{id}', [PredictionController::class, 'show']);
    Route::put('predictions/{id}/verify', [PredictionController::class, 'verify']);

    // ── Recommendations ──────────────────────────────────────
    Route::get('recommendations', [RecommendationController::class, 'index']);
    Route::get('recommendations/{id}', [RecommendationController::class, 'show']);
    Route::put('recommendations/{id}/approve', [RecommendationController::class, 'approve']);
    Route::put('recommendations/{id}/reject', [RecommendationController::class, 'reject']);
    Route::post('recommendations/{id}/analyze', [RecommendationController::class, 'analyze']);

    // ── Alerts ──────────────────────────────────────────────
    Route::apiResource('alerts', AlertController::class)
        ->only('index', 'show', 'store', 'update');
    Route::put('alerts/{id}/status', [AlertController::class, 'updateStatus']);

    // ── Notifications ──────────────────────────────────────
    Route::get('notifications/unread', [NotificationController::class, 'unread']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::put('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::put('notifications/{id}/read', [NotificationController::class, 'markAsRead']);

    // ── FCM Device Management ──────────────────────────────
    Route::prefix('fcm')->group(function () {
        Route::post('register', [FcmTokenController::class, 'register']);
        Route::get('devices', [FcmTokenController::class, 'listDevices']);
        Route::put('devices/{id}', [FcmTokenController::class, 'updateDevice']);
        Route::delete('devices/{id}', [FcmTokenController::class, 'deleteDevice']);
        Route::delete('token/{token}', [FcmTokenController::class, 'deleteByToken']);
        Route::post('refresh', [FcmTokenController::class, 'refreshToken']);
        Route::post('subscribe', [FcmTokenController::class, 'subscribeTopic']);
        Route::post('unsubscribe', [FcmTokenController::class, 'unsubscribeTopic']);
    });

    // ── Map ────────────────────────────────────────────────
    Route::prefix('map')->group(function () {
        Route::get('all', [MapController::class, 'all']);
        Route::get('incidents', [MapController::class, 'incidents']);
        Route::get('flood-zones', [MapController::class, 'floodZones']);
        Route::get('rescue-teams', [MapController::class, 'rescueTeams']);
        Route::get('shelters', [MapController::class, 'shelters']);
        Route::get('flood-reports', [MapController::class, 'floodReports']);
        Route::get('sensor-stations', [MapController::class, 'sensorStations']);
        Route::get('flood-events', [MapController::class, 'floodEvents']);
    });

    // ── Analytics ──────────────────────────────────────────
    Route::get('analytics/overview', [AnalyticsController::class, 'overview']);

    // ── Evacuation Routes ──────────────────────────────────
    Route::get('evacuation-routes', [EvacuationRouteController::class, 'index']);
    Route::get('evacuation-routes/{id}', [EvacuationRouteController::class, 'show']);

    // ── AI Chat (Groq) ──────────────────────────────────────
    Route::post('ai/chat', [AIChatController::class, 'chat']);
    Route::get('ai/status', [AIChatController::class, 'status']);

    // ============================================================
    // OPERATOR ROUTES (role: city_admin, rescue_operator, rescue_team)
    // ============================================================
    Route::middleware('role_or_permission:city_admin|rescue_operator|rescue_team')->group(function () {

        // Trigger AI prediction
        Route::post('predictions/trigger', [PredictionController::class, 'trigger']);

        // Sensor data ingestion
        Route::post('sensor-data', [SensorDataController::class, 'ingest']);
        Route::post('sensor-data/batch', [SensorDataController::class, 'batchIngest']);

        // CRUD Flood Zones
        Route::apiResource('flood-zones', FloodZoneController::class)
            ->only('store', 'update', 'destroy');

        // CRUD Alerts
        Route::apiResource('alerts', AlertController::class)
            ->only('store', 'update');

        // CRUD Rescue Requests (quản lý)
        Route::put('rescue-requests/{id}/assign', [RescueRequestController::class, 'assign']);
        Route::put('rescue-requests/{id}/status', [RescueRequestController::class, 'updateStatus']);

        // Weather data ingestion
        Route::post('weather', [WeatherDataController::class, 'store']);
        Route::post('weather/batch', [WeatherDataController::class, 'batchStore']);

        // CRUD Evacuation Routes
        Route::post('evacuation-routes', [EvacuationRouteController::class, 'store']);
        Route::put('evacuation-routes/{id}', [EvacuationRouteController::class, 'update']);
        Route::delete('evacuation-routes/{id}', [EvacuationRouteController::class, 'destroy']);
    });

    // ============================================================
    // ADMIN ROUTES (role: city_admin)
    // ============================================================
    Route::middleware('role:city_admin')->prefix('admin')->group(function () {

        // Roles & Permissions
        Route::get('roles', [RoleController::class, 'index']);
        Route::get('permissions', [RoleController::class, 'permissions']);
        Route::put('roles/{id}/permissions', [RoleController::class, 'syncPermissions']);

        // User management
        Route::get('users/{id}/permissions', [UserController::class, 'permissions']);
        Route::put('users/{id}/permissions', [UserController::class, 'syncPermissions']);
        Route::apiResource('users', UserController::class);
        Route::get('stats', [SystemController::class, 'stats']);
        Route::get('logs', [SystemController::class, 'logs']);
        Route::get('system-settings', [SystemController::class, 'settings']);
        Route::put('system-settings', [SystemController::class, 'updateSettings']);
    });
});
