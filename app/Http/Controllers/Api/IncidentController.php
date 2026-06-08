<?php

namespace App\Http\Controllers\Api;

use App\Enums\AlertStatusEnum;
use App\Enums\AlertTypeEnum;
use App\Enums\IncidentSourceEnum;
use App\Enums\IncidentStatusEnum;
use App\Events\IncidentCreated;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Incident;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IncidentController extends Controller
{
    /**
     * Danh sách sự cố (public — giới hạn fields)
     * GET /api/public/incidents
     */
    public function publicList(Request $request)
    {
        $incidents = Incident::active()
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $features = $incidents->map(fn ($i) => $i->toGeoJson());

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    /**
     * Danh sách sự cố (auth)
     * GET /api/incidents
     */
    public function index(Request $request)
    {
        $query = Incident::with(['district', 'assignee', 'floodZone'])
            ->orderBy('created_at', 'desc');

        // Filter by current user's reports
        if ($request->boolean('my')) {
            $query->where('reported_by', $request->user()->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($request->filled('district_id')) {
            $query->where('district_id', $request->district_id);
        }

        // Nearby filter by lat/lng
        if ($request->filled('lat') && $request->filled('lng')) {
            $lat = (float)$request->lat;
            $lng = (float)$request->lng;
            $radius = (float)$request->get('radius', 5); // km
            if (DB::connection()->getDriverName() === 'pgsql') {
                $radiusMeters = $radius * 1000;
                $query->selectRaw('*, (ST_Distance(geometry::geography, ST_MakePoint(?, ?)::geography) / 1000.0) AS distance', [$lng, $lat])
                    ->whereRaw('ST_DWithin(geometry::geography, ST_MakePoint(?, ?)::geography, ?)', [$lng, $lat, $radiusMeters])
                    ->orderBy('distance');
            } else {
                $query->selectRaw('*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance', [$lat, $lng, $lat])
                    ->having('distance', '<=', $radius)
                    ->orderBy('distance');
            }
        }

        $incidents = $query->paginate($request->get('per_page', 20));

        $data = $incidents->map(fn ($i) => $this->formatIncident($i));

        return ApiResponse::paginate($incidents->setCollection($data));
    }

    /**
     * Chi tiết sự cố
     * GET /api/incidents/{id}
     */
    public function show(int $id)
    {
        $incident = Incident::with([
            'district', 'ward', 'floodZone',
            'reporter', 'assignee', 'verifier',
            'events', 'predictions', 'recommendations',
        ])->find($id);

        if (! $incident) {
            return ApiResponse::notFound('Không tìm thấy sự cố');
        }

        return ApiResponse::success($this->formatIncident($incident, true));
    }

    /**
     * Tạo sự cố
     * POST /api/incidents
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:flood,heavy_rain,landslide,dam_failure,other',
            'severity' => 'required|in:low,medium,high,critical',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'nullable|string',
            'district_id' => 'nullable|exists:districts,id',
            'flood_zone_id' => 'nullable|exists:flood_zones,id',
            'water_level_m' => 'nullable|numeric|min:0',
            'rainfall_mm' => 'nullable|numeric|min:0',
            'photo_urls' => 'nullable|array',
        ]);

        $user = $request->user();
        $source = IncidentSourceEnum::CITIZEN->value;

        $incident = Incident::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'severity' => $data['severity'],
            'status' => IncidentStatusEnum::REPORTED->value,
            'source' => $source,
            'address' => $data['address'] ?? null,
            'district_id' => $data['district_id'] ?? null,
            'flood_zone_id' => $data['flood_zone_id'] ?? null,
            'water_level_m' => $data['water_level_m'] ?? null,
            'rainfall_mm' => $data['rainfall_mm'] ?? null,
            'photo_urls' => $data['photo_urls'] ?? [],
            'reported_by' => $user->id,
        ]);

        // Lưu PostGIS geometry
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'UPDATE incidents SET geometry = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                [$data['longitude'], $data['latitude'], $incident->id]
            );
        }

        // Map incident type to alert type
        $alertTypeMap = [
            'flood' => AlertTypeEnum::FLOOD_WARNING,
            'heavy_rain' => AlertTypeEnum::HEAVY_RAIN,
            'dam_failure' => AlertTypeEnum::DAM_WARNING,
            'landslide' => AlertTypeEnum::WEATHER,
            'other' => AlertTypeEnum::WEATHER,
        ];
        $alertType = $alertTypeMap[$data['type']] ?? AlertTypeEnum::WARNING;

        // Auto-create alert for citizen-reported incidents with high/critical severity
        if ($source === IncidentSourceEnum::CITIZEN->value && in_array($data['severity'], ['high', 'critical'])) {
            $alert = Alert::create([
                'title' => '[Từ báo cáo] '.$data['title'],
                'description' => $data['description'] ?? null,
                'alert_type' => $alertType->value,
                'severity' => $data['severity'],
                'status' => AlertStatusEnum::ACTIVE->value,
                'effective_from' => now(),
                'effective_until' => now()->addHours(6),
                'related_incident_id' => $incident->id,
                'source' => 'citizen_report',
                'issued_by' => $user->id,
            ]);

            if (isset($data['latitude']) && isset($data['longitude']) && DB::connection()->getDriverName() === 'pgsql') {
                DB::statement(
                    'UPDATE alerts SET geometry = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                    [$data['longitude'], $data['latitude'], $alert->id]
                );
            }
        }

        $incident->logEvent('created', 'Sự cố được tạo bởi '.$user->name, $user->id);

        // Broadcast event
        broadcast(new IncidentCreated($incident))->toOthers();

        return ApiResponse::created($this->formatIncident($incident), 'Sự cố đã được báo cáo');
    }

    /**
     * Cập nhật sự cố
     * PATCH /api/incidents/{id}
     */
    public function update(Request $request, int $id)
    {
        $incident = Incident::find($id);

        if (! $incident) {
            return ApiResponse::notFound('Không tìm thấy sự cố');
        }

        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:reported,verified,responding,resolved,closed',
            'severity' => 'nullable|in:low,medium,high,critical',
            'assigned_to' => 'nullable|exists:users,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'water_level_m' => 'nullable|numeric|min:0',
        ]);

        $oldStatus = $incident->status;
        $oldSeverity = $incident->severity;

        $incident->fill($data);
        if (($data['status'] ?? null) === IncidentStatusEnum::RESOLVED->value && ! $incident->resolved_at) {
            $incident->resolved_at = now();
        }
        $incident->save();

        // Cập nhật geometry
        if (isset($data['latitude'], $data['longitude']) && DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'UPDATE incidents SET geometry = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                [$data['longitude'], $data['latitude'], $incident->id]
            );
        }

        // Log events nếu có thay đổi
        if (isset($data['status']) && $data['status'] !== $oldStatus) {
            $incident->logEvent('status_changed', "Đổi trạng thái: {$oldStatus} → {$data['status']}", $request->user()->id);
        }

        if (isset($data['severity']) && $data['severity'] !== $oldSeverity) {
            $incident->logEvent('severity_updated', "Đổi mức độ: {$oldSeverity} → {$data['severity']}", $request->user()->id);
        }

        if (isset($data['assigned_to'])) {
            $incident->logEvent('assigned', 'Được gán cho người xử lý', $request->user()->id);
        }

        return ApiResponse::success($this->formatIncident($incident->fresh()), 'Cập nhật thành công');
    }

    /**
     * Format incident response
     */
    protected function formatIncident(Incident $incident, bool $detailed = false): array
    {
        $data = [
            'id' => $incident->id,
            'title' => $incident->title,
            'type' => $incident->type,
            'type_label' => $incident->translated('type'),
            'severity' => $incident->severity,
            'severity_label' => $incident->translated('severity'),
            'status' => $incident->status,
            'status_label' => $incident->translated('status'),
            'source' => $incident->source,
            'address' => $incident->address,
            'location' => $incident->location,
            'water_level_m' => $incident->water_level_m,
            'district' => $incident->district ? ['id' => $incident->district->id, 'name' => $incident->district->name] : null,
            'flood_zone' => $incident->floodZone ? ['id' => $incident->floodZone->id, 'name' => $incident->floodZone->name] : null,
            'assignee' => $incident->assignee ? ['id' => $incident->assignee->id, 'name' => $incident->assignee->name] : null,
            'created_at' => $incident->created_at?->toIso8601String(),
        ];

        if ($detailed) {
            $data['description'] = $incident->description;
            $data['rainfall_mm'] = $incident->rainfall_mm;
            $data['photo_urls'] = $incident->photo_urls ?? [];
            $data['reporter'] = $incident->reporter ? ['id' => $incident->reporter->id, 'name' => $incident->reporter->name] : null;
            $data['verifier'] = $incident->verifier ? ['id' => $incident->verifier->id, 'name' => $incident->verifier->name] : null;
            $data['verified_at'] = $incident->verified_at?->toIso8601String();
            $data['resolved_at'] = $incident->resolved_at?->toIso8601String();
            $data['events'] = $incident->events->map(fn ($e) => [
                'id' => $e->id,
                'event_type' => $e->event_type,
                'description' => $e->description,
                'actor' => $e->actor?->name,
                'created_at' => $e->created_at?->toIso8601String(),
            ]);
        }

        return $data;
    }
}
