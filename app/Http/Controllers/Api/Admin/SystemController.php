<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;

use App\Helpers\ApiResponse;
use App\Models\Incident;
use App\Models\RescueRequest;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;

class SystemController extends Controller
{
    protected array $defaults = [
        'ai.prediction.enabled' => [
            'value' => true,
            'type' => 'boolean',
            'group' => 'ai',
            'description' => 'Bật chạy dự báo AI tự động bằng scheduler',
        ],
        'ai.prediction.interval_minutes' => [
            'value' => 15,
            'type' => 'integer',
            'group' => 'ai',
            'description' => 'Khoảng cách giữa hai lần chạy dự báo AI tự động',
        ],
        'ai.prediction.horizon_minutes' => [
            'value' => 60,
            'type' => 'integer',
            'group' => 'ai',
            'description' => 'Khung thời gian AI dự báo về phía trước',
        ],
        'ai.prediction.seasonality_enabled' => [
            'value' => true,
            'type' => 'boolean',
            'group' => 'ai',
            'description' => 'Cho phép AI dùng mẫu lịch sử theo tháng/năm',
        ],
    ];

    /**
     * Thống kê hệ thống
     * GET /api/admin/stats
     */
    public function stats()
    {
        return ApiResponse::success([
            'users' => [
                'total' => User::count(),
                'active' => User::where('is_active', true)->count(),
            ],
            'incidents' => [
                'total' => Incident::count(),
                'active' => Incident::active()->count(),
                'resolved' => Incident::whereIn('status', ['resolved', 'closed'])->count(),
                'critical' => Incident::critical()->active()->count(),
            ],
            'rescue_requests' => [
                'total' => RescueRequest::count(),
                'pending' => RescueRequest::pending()->count(),
                'completed' => RescueRequest::where('status', 'completed')->count(),
            ],
        ]);
    }

    /**
     * Activity logs
     * GET /api/admin/logs
     */
    public function logs(Request $request)
    {
        $query = \Spatie\Activitylog\Models\Activity::with('causer')
            ->orderBy('created_at', 'desc');

        if ($request->filled('causer_id')) {
            $query->where('causer_id', $request->causer_id);
        }

        $logs = $query->paginate($request->get('per_page', 30));

        $data = $logs->map(fn ($log) => [
            'id' => $log->id,
            'description' => $log->description,
            'causer' => $log->causer ? [
                'id' => $log->causer->id,
                'name' => $log->causer->name,
            ] : null,
            'subject_type' => $log->subject_type,
            'subject_id' => $log->subject_id,
            'properties' => $log->properties,
            'created_at' => $log->created_at?->toIso8601String(),
        ]);

        return ApiResponse::paginate($logs->setCollection($data));
    }

    /**
     * System settings
     * GET /api/admin/system-settings
     */
    public function settings()
    {
        return ApiResponse::success($this->formattedSettings());
    }

    /**
     * Update system settings
     * PUT /api/admin/system-settings
     */
    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'ai_prediction_enabled' => 'nullable|boolean',
            'ai_prediction_interval_minutes' => 'nullable|integer|in:1,5,10,15,30,60',
            'ai_prediction_horizon_minutes' => 'nullable|integer|in:15,30,60,120,240,1440',
            'ai_seasonality_enabled' => 'nullable|boolean',
        ]);

        $mapping = [
            'ai_prediction_enabled' => 'ai.prediction.enabled',
            'ai_prediction_interval_minutes' => 'ai.prediction.interval_minutes',
            'ai_prediction_horizon_minutes' => 'ai.prediction.horizon_minutes',
            'ai_seasonality_enabled' => 'ai.prediction.seasonality_enabled',
        ];

        foreach ($mapping as $inputKey => $settingKey) {
            if (! array_key_exists($inputKey, $data)) {
                continue;
            }

            $meta = $this->defaults[$settingKey];
            SystemSetting::setValue(
                $settingKey,
                $data[$inputKey],
                $meta['type'],
                $meta['group'],
                $meta['description']
            );
        }

        return ApiResponse::success($this->formattedSettings(), 'Đã cập nhật cấu hình hệ thống');
    }

    protected function formattedSettings(): array
    {
        foreach ($this->defaults as $key => $meta) {
            if (! SystemSetting::where('key', $key)->exists()) {
                SystemSetting::setValue($key, $meta['value'], $meta['type'], $meta['group'], $meta['description']);
            }
        }

        return [
            'ai' => [
                'prediction_enabled' => SystemSetting::getValue('ai.prediction.enabled', true),
                'prediction_interval_minutes' => SystemSetting::getValue('ai.prediction.interval_minutes', 15),
                'prediction_horizon_minutes' => SystemSetting::getValue('ai.prediction.horizon_minutes', 60),
                'seasonality_enabled' => SystemSetting::getValue('ai.prediction.seasonality_enabled', true),
                'last_run_at' => SystemSetting::getValue('ai.prediction.last_run_at'),
            ],
        ];
    }
}
