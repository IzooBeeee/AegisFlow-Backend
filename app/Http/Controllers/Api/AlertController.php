<?php

namespace App\Http\Controllers\Api;

use App\Enums\AlertStatusEnum;
use App\Enums\AlertTypeEnum;
use App\Events\AlertCreated;
use App\Events\AlertResolved;
use App\Events\AlertUpdated;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\AlertResource;
use App\Models\Alert;
use App\Support\DaNangLandMask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AlertController extends Controller
{
    /**
     * Danh sách cảnh báo
     * GET /api/alerts
     */
    public function index(Request $request)
    {
        $query = Alert::with('issuer', 'relatedIncident')
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('alert_type')) {
            $query->where('alert_type', $request->alert_type);
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        if ($request->filled('district_id')) {
            $query->whereJsonContains('affected_districts', (string) $request->district_id);
        }

        // Scope alerts by citizen's location if user has role 'citizen'
        $user = $request->user();
        if ($user && $user->hasRole('citizen')) {
            $districts = $user->district_id ? [(string) $user->district_id] : [];
            if ($districts) {
                $query->where(function ($q) use ($districts) {
                    foreach ($districts as $district) {
                        $q->orWhereJsonContains('affected_districts', $district);
                    }
                });
            }
        }

        $alerts = $query->paginate($request->get('per_page', 20));

        return ApiResponse::paginate($alerts->setCollection(
            collect(AlertResource::collection($alerts->getCollection())->resolve())
        ));
    }

    /**
     * Chi tiết cảnh báo
     * GET /api/alerts/{id}
     */
    public function show(int $id)
    {
        $alert = Alert::with(['issuer', 'resolver', 'relatedIncident', 'relatedPrediction'])
            ->find($id);

        if (! $alert) {
            return ApiResponse::notFound('Không tìm thấy cảnh báo');
        }

        return ApiResponse::success(new AlertResource($alert));
    }

    /**
     * Tạo cảnh báo
     * POST /api/alerts
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'alert_type' => 'required|in:'.implode(',', AlertTypeEnum::values()),
            'severity' => 'required|in:low,medium,high,critical',
            'geometry' => 'nullable|string',
            'affected_districts' => 'nullable|array',
            'affected_districts.*' => 'exists:districts,id',
            'affected_wards' => 'nullable|array',
            'affected_flood_zones' => 'nullable|array',
            'affected_flood_zones.*' => 'exists:flood_zones,id',
            'radius_km' => 'nullable|numeric|min:0',
            'effective_from' => 'nullable|date',
            'effective_until' => 'nullable|date|after:effective_from',
            'related_prediction_id' => 'nullable|exists:predictions,id',
            'related_incident_id' => 'nullable|exists:incidents,id',
        ]);

        $user = $request->user();

        $alert = Alert::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'alert_type' => $data['alert_type'],
            'severity' => $data['severity'],
            'status' => AlertStatusEnum::ACTIVE->value,
            'affected_districts' => $data['affected_districts'] ?? [],
            'affected_wards' => $data['affected_wards'] ?? [],
            'affected_flood_zones' => $data['affected_flood_zones'] ?? [],
            'radius_km' => $data['radius_km'] ?? null,
            'effective_from' => $data['effective_from'] ?? null,
            'effective_until' => $data['effective_until'] ?? null,
            'related_prediction_id' => $data['related_prediction_id'] ?? null,
            'related_incident_id' => $data['related_incident_id'] ?? null,
            'source' => 'operator',
            'issued_by' => $user->id,
        ]);

        // Lưu geometry PostGIS
        if (! empty($data['geometry']) && DB::connection()->getDriverName() === 'pgsql') {
            try {
                DB::statement(
                    'UPDATE alerts SET geometry = ST_GeomFromText(?, 4326) WHERE id = ?',
                    [$data['geometry'], $alert->id]
                );
            } catch (\Exception $e) {
            }
        }

        // Broadcast event
        broadcast(new AlertCreated($alert->fresh()))->toOthers();

        return ApiResponse::created(new AlertResource($alert->fresh()), 'Cảnh báo đã được phát');
    }

    /**
     * Cập nhật trạng thái cảnh báo
     * PUT /api/alerts/{id}/status
     */
    public function updateStatus(Request $request, int $id)
    {
        $alert = Alert::find($id);

        if (! $alert) {
            return ApiResponse::notFound('Không tìm thấy cảnh báo');
        }

        $data = $request->validate([
            'status' => 'required|in:draft,active,updated,resolved,expired',
        ]);

        $user = $request->user();

        if ($data['status'] === AlertStatusEnum::RESOLVED->value) {
            $alert->resolve($user->id);
            broadcast(new AlertResolved($alert->fresh()))->toOthers();
        } else {
            $alert->update(['status' => $data['status']]);
            broadcast(new AlertUpdated($alert->fresh()))->toOthers();
        }

        return ApiResponse::success(new AlertResource($alert->fresh()), 'Cập nhật trạng thái thành công');
    }

    /**
     * GeoJSON cảnh báo đang hoạt động
     * GET /api/public/alerts/geojson
     */
    public function geojson(Request $request)
    {
        $query = Alert::active();

        // Scope alerts by user's district if citizen
        $user = $request->user();
        if ($user && $user->hasRole('citizen')) {
            if ($user->district_id) {
                $query->whereJsonContains('affected_districts', (string) $user->district_id);
            }
        }

        $alerts = $query->get();

        $features = $alerts->map(function ($alert) {
            $geometry = null;
            if (DB::connection()->getDriverName() === 'pgsql') {
                try {
                    $result = DB::selectOne(
                        'SELECT ST_AsGeoJSON(geometry) as geojson FROM alerts WHERE id = ?',
                        [$alert->id]
                    );
                } catch (\Exception $e) {
                    $result = null;
                }
                $geometry = $result?->geojson ? json_decode($result->geojson) : null;
            }

            return [
                'type' => 'Feature',
                'id' => $alert->id,
                'properties' => [
                    'id' => $alert->id,
                    'title' => $alert->title,
                    'alert_type' => $alert->alert_type,
                    'severity' => $alert->severity,
                    'status' => $alert->status,
                ],
                'geometry' => $geometry,
            ];
        })->filter(fn (array $feature) => DaNangLandMask::featureIsLikelyLand($feature))->values();

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }
}
