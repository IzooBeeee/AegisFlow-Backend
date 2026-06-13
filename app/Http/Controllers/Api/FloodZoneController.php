<?php

namespace App\Http\Controllers\Api;

use App\Enums\FloodZoneRiskEnum;
use App\Enums\FloodZoneStatusEnum;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\FloodZoneResource;
use App\Models\FloodZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FloodZoneController extends Controller
{
    /**
     * Danh sách vùng ngập
     * GET /api/flood-zones
     */
    public function index(Request $request)
    {
        $query = FloodZone::with('district', 'sensors')
            ->active()
            ->orderBy('risk_level', 'desc');

        if ($request->filled('district_id')) {
            $query->where('district_id', $request->district_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('risk_level')) {
            $query->where('risk_level', $request->risk_level);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $zones = $query->paginate($request->get('per_page', 20));

        return ApiResponse::paginate($zones->setCollection(
            collect(FloodZoneResource::collection($zones->getCollection())->resolve())
        ));
    }

    /**
     * Chi tiết vùng ngập
     * GET /api/flood-zones/{id}
     */
    public function show(int $id)
    {
        $zone = FloodZone::with(['district', 'sensors', 'incidents', 'evacuationRoutes'])
            ->find($id);

        if (! $zone) {
            return ApiResponse::notFound('Không tìm thấy vùng ngập');
        }

        return ApiResponse::success(new FloodZoneResource($zone));
    }

    /**
     * Tạo vùng ngập mới
     * POST /api/flood-zones
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:flood_zones,slug',
            'description' => 'nullable|string',
            'geometry' => 'nullable|string', // GeoJSON string hoặc WKT
            'district_id' => 'nullable|exists:districts,id',
            'ward_ids' => 'nullable|array',
            'ward_ids.*' => 'exists:wards,id',
            'risk_level' => 'required|in:'.implode(',', FloodZoneRiskEnum::values()),
            'alert_threshold_m' => 'nullable|numeric|min:0',
            'danger_threshold_m' => 'nullable|numeric|min:0',
            'color' => 'nullable|string|max:7',
            'opacity' => 'nullable|numeric|min:0|max:1',
        ]);

        $zone = FloodZone::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'district_id' => $data['district_id'] ?? null,
            'ward_ids' => $data['ward_ids'] ?? null,
            'risk_level' => $data['risk_level'],
            'alert_threshold_m' => $data['alert_threshold_m'] ?? 1.5,
            'danger_threshold_m' => $data['danger_threshold_m'] ?? 3.0,
            'color' => $data['color'] ?? '#f79009',
            'opacity' => $data['opacity'] ?? 0.3,
            'is_active' => true,
            'status' => FloodZoneStatusEnum::MONITORING->value,
        ]);

        // Lưu geometry PostGIS
        if (! empty($data['geometry']) && DB::connection()->getDriverName() === 'pgsql') {
            try {
            DB::statement(
                            'UPDATE flood_zones SET geometry = ST_GeomFromText(?, 4326) WHERE id = ?',
                            [$data['geometry'], $zone->id]
                        );
        } catch (\Exception $e) {}
        }

        return ApiResponse::created(new FloodZoneResource($zone->fresh()));
    }

    /**
     * Cập nhật vùng ngập
     * PUT /api/flood-zones/{id}
     */
    public function update(Request $request, int $id)
    {
        $zone = FloodZone::find($id);

        if (! $zone) {
            return ApiResponse::notFound('Không tìm thấy vùng ngập');
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'geometry' => 'nullable|string',
            'district_id' => 'nullable|exists:districts,id',
            'ward_ids' => 'nullable|array',
            'risk_level' => 'nullable|in:'.implode(',', FloodZoneRiskEnum::values()),
            'current_water_level_m' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:'.implode(',', FloodZoneStatusEnum::values()),
            'alert_threshold_m' => 'nullable|numeric|min:0',
            'danger_threshold_m' => 'nullable|numeric|min:0',
            'color' => 'nullable|string|max:7',
            'opacity' => 'nullable|numeric|min:0|max:1',
            'is_active' => 'nullable|boolean',
        ]);

        $zone->update($data);

        if (! empty($data['geometry']) && DB::connection()->getDriverName() === 'pgsql') {
            try {
            DB::statement(
                            'UPDATE flood_zones SET geometry = ST_GeomFromText(?, 4326) WHERE id = ?',
                            [$data['geometry'], $zone->id]
                        );
        } catch (\Exception $e) {}
        }

        return ApiResponse::success(new FloodZoneResource($zone->fresh()), 'Cập nhật thành công');
    }

    /**
     * Xóa vùng ngập (soft delete)
     * DELETE /api/flood-zones/{id}
     */
    public function destroy(int $id)
    {
        $zone = FloodZone::find($id);

        if (! $zone) {
            return ApiResponse::notFound('Không tìm thấy vùng ngập');
        }

        $zone->delete();

        return ApiResponse::deleted('Xóa vùng ngập thành công');
    }

    /**
     * GeoJSON tất cả vùng ngập
     * GET /api/flood-zones/geojson
     */
    public function geojson(Request $request)
    {
        $query = FloodZone::active();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $zones = $query->get();

        $features = $zones->map(fn ($zone) => $zone->toGeoJson());

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }
}
