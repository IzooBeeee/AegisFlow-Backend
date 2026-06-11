<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\CallAIPrediction;
use App\Models\SystemSetting;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks — AegisFlow AI
|--------------------------------------------------------------------------
*/

// Kiểm tra mỗi phút, nhưng chỉ chạy dự báo AI theo interval được cấu hình trong system_settings
Schedule::call(function () {
    if (! SystemSetting::getValue('ai.prediction.enabled', true)) {
        return;
    }

    $interval = max(1, (int) SystemSetting::getValue('ai.prediction.interval_minutes', 15));
    $horizon = (int) SystemSetting::getValue('ai.prediction.horizon_minutes', 60);
    $lastRunAt = SystemSetting::getValue('ai.prediction.last_run_at');

    if ($lastRunAt && now()->diffInMinutes(\Illuminate\Support\Carbon::parse($lastRunAt)) < $interval) {
        return;
    }

    SystemSetting::setValue('ai.prediction.last_run_at', now()->toIso8601String(), 'string', 'ai', 'Lần chạy dự báo AI gần nhất');
    CallAIPrediction::dispatch(null, null, $horizon);
})
    ->everyMinute()
    ->name('ai-prediction')
    ->withoutOverlapping();

// Fetch dữ liệu thời tiết từ OpenWeatherMap mỗi 30 phút
Schedule::job(new \App\Jobs\FetchWeatherDataJob)
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->name('fetch-weather-data');

// Đồng bộ dữ liệu Đà Nẵng (muangap) mỗi 15 phút
Schedule::command('sync:danang-data')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->name('sync-danang-data');

// Kiểm tra sức khỏe cảm biến mỗi 10 phút
Schedule::call(function () {
    \App\Models\Sensor::where('is_active', true)
        ->where('last_reading_at', '<', now()->subMinutes(10))
        ->update(['status' => 'offline']);
})->everyTenMinutes()->name('sensor-health-check');

// Dọn dẹp predictions hết hạn (hàng ngày)
Schedule::call(function () {
    \App\Models\Prediction::where('status', 'generated')
        ->where('created_at', '<', now()->subDays(2))
        ->update(['status' => 'expired']);
})->daily()->name('cleanup-expired-predictions');

// Dọn dẹp alerts hết hạn
Schedule::call(function () {
    \App\Models\Alert::where('status', 'active')
        ->whereNotNull('effective_until')
        ->where('effective_until', '<', now())
        ->update(['status' => 'expired']);
})->hourly()->name('cleanup-expired-alerts');
