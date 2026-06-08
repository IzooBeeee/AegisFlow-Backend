<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;

use App\Helpers\ApiResponse;
use App\Models\Incident;
use App\Models\RescueRequest;
use App\Models\User;
use Illuminate\Http\Request;

class SystemController extends Controller
{
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
}
