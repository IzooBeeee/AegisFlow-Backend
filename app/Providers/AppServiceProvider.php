<?php

namespace App\Providers;

use App\Services\FcmPushService;
use App\Services\FloodAutoDetector;
use App\Services\RecommendationGenerator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Singletons cho Services
        $this->app->singleton(FloodAutoDetector::class, function () {
            return new FloodAutoDetector();
        });

        $this->app->singleton(RecommendationGenerator::class, function () {
            return new RecommendationGenerator();
        });

        // FCM Push Service
        $this->app->singleton(FcmPushService::class, function () {
            return new FcmPushService();
        });

        // Notification Broadcast Service
        $this->app->singleton(NotificationBroadcastService::class, function ($app) {
            return new NotificationBroadcastService(
                $app->make(FcmPushService::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ── Rate Limiting ──────────────────────────────────────

        // API chung: tăng lên 300 req/phút cho authenticated users, 60 cho guest
        RateLimiter::for('api', function (Request $request) {
            $limit = $request->user() ? 300 : 60;
            return Limit::perMinute($limit)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        // Auth endpoints: 10 requests/phút để chống brute-force
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by(
                $request->ip()
            );
        });

        // Sensor data ingestion: 300 requests/phút (throughput cao hơn)
        RateLimiter::for('sensor-data', function (Request $request) {
            return Limit::perMinute(300)->by(
                $request->user()?->id ?: $request->ip()
            );
        });
    }
}
