<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\FloodZone;
use App\Models\Incident;
use App\Models\RescueRequest;
use App\Models\RescueTeam;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    /**
     * Tổng quan dashboard
     * GET /api/analytics/overview
     */
    public function overview(Request $request)
    {
        $districtId = $request->get('district_id');

        $periodDays = match ($request->get('period', '7d')) {
            '24h' => 1,
            '30d' => 30,
            '90d' => 90,
            default => 7,
        };
        $since = now()->subDays($periodDays);

        // Counts filtered by period
        $totalIncidents = Incident::where('created_at', '>=', $since)
            ->when($districtId, fn ($q) => $q->where('district_id', $districtId))->count();
        $activeIncidents = Incident::where('created_at', '>=', $since)
            ->when($districtId, fn ($q) => $q->where('district_id', $districtId))
            ->active()->count();
        $criticalIncidents = Incident::where('created_at', '>=', $since)
            ->when($districtId, fn ($q) => $q->where('district_id', $districtId))
            ->critical()->active()->count();

        $pendingRequests = RescueRequest::where('created_at', '>=', $since)
            ->when($districtId, fn ($q) => $q->where('district_id', $districtId))
            ->pending()->count();
        $criticalRequests = RescueRequest::where('created_at', '>=', $since)
            ->when($districtId, fn ($q) => $q->where('district_id', $districtId))
            ->critical()->pending()->count();

        $floodedZones = FloodZone::when($districtId, fn ($q) => $q->where('district_id', $districtId))
            ->flooded()->count();
        $alertZones = FloodZone::when($districtId, fn ($q) => $q->where('district_id', $districtId))
            ->where('status', 'alert')->count();

        // Available rescue teams
        $availableTeams = RescueTeam::available()
            ->when($districtId, fn ($q) => $q->where('district_id', $districtId))
            ->count();

        // Resolution rate within period
        $resolvedIncidents = Incident::where('created_at', '>=', $since)
            ->when($districtId, fn ($q) => $q->where('district_id', $districtId))
            ->whereIn('status', ['resolved', 'closed'])->count();
        $resolutionRate = $totalIncidents > 0 ? round(($resolvedIncidents / $totalIncidents) * 100, 1) : 0;

        // Severity distribution within period
        $severityDist = Incident::where('created_at', '>=', $since)
            ->when($districtId, fn ($q) => $q->where('district_id', $districtId))
            ->selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity');

        // Trend within period
        $incidentTrend = Incident::where('created_at', '>=', $since)
            ->when($districtId, fn ($q) => $q->where('district_id', $districtId))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Top flooded zones
        $topFloodedZones = FloodZone::active()
            ->when($districtId, fn ($q) => $q->where('district_id', $districtId))
            ->orderByDesc('current_water_level_m')
            ->limit(5)
            ->get()
            ->map(fn ($z) => [
                'id' => $z->id,
                'name' => $z->name,
                'water_level_m' => $z->current_water_level_m,
                'risk_level' => $z->risk_level,
                'status' => $z->status,
            ]);

        return ApiResponse::success([
            'incidents' => [
                'total' => $totalIncidents,
                'active' => $activeIncidents,
                'critical' => $criticalIncidents,
                'resolution_rate' => $resolutionRate,
                'distribution' => $severityDist,
                'trend_7d' => $incidentTrend,
            ],
            'rescue_requests' => [
                'pending' => $pendingRequests,
                'critical' => $criticalRequests,
            ],
            'flood_zones' => [
                'flooded' => $floodedZones,
                'alert' => $alertZones,
                'top_water_levels' => $topFloodedZones,
            ],
            'rescue_teams' => [
                'available' => $availableTeams,
            ],
            'generated_at' => now()->toIso8601String(),
        ]);
    }
}
