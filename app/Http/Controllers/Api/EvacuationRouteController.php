<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\EvacuationRouteResource;
use App\Models\EvacuationRoute;
use App\Models\MapNode;
use App\Services\AI\AIServiceClient;
use Illuminate\Http\Request;

class EvacuationRouteController extends Controller
{
    /**
     * Danh sách tuyến sơ tán
     * GET /api/evacuation-routes
     */
    public function index(Request $request)
    {
        $query = EvacuationRoute::with(['startNode', 'endNode', 'floodZone'])
            ->withCount('segments');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('flood_zone_id')) {
            $query->where('flood_zone_id', $request->flood_zone_id);
        }

        if ($request->boolean('safe_only', false)) {
            $query->where('is_safe', true);
        }

        $routes = $query->orderByDesc('is_primary')
            ->orderByDesc('safety_rating')
            ->paginate($request->integer('per_page', 15));

        return ApiResponse::success(
            EvacuationRouteResource::collection($routes)->response()->getData(true)
        );
    }

    /**
     * Chi tiết tuyến sơ tán
     * GET /api/evacuation-routes/{id}
     */
    public function show(int $id)
    {
        $route = EvacuationRoute::with(['startNode', 'endNode', 'floodZone', 'segments'])
            ->findOrFail($id);

        return ApiResponse::success(new EvacuationRouteResource($route));
    }

    /**
     * Tạo tuyến sơ tán mới
     * POST /api/evacuation-routes
     */
    public function store(Request $request, AIServiceClient $aiService)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_node_id' => 'required|exists:map_nodes,id',
            'end_node_id' => 'required|exists:map_nodes,id',
            'flood_zone_id' => 'nullable|exists:flood_zones,id',
            'is_primary' => 'boolean',
            'max_capacity' => 'nullable|integer|min:1',
            'color' => 'nullable|string|max:7',
        ]);

        $startNode = MapNode::findOrFail($data['start_node_id']);
        $endNode = MapNode::findOrFail($data['end_node_id']);

        $startLoc = $startNode->location;
        $endLoc = $endNode->location;

        $distance = 0;
        $estimatedTime = 0;
        $isSafe = true;
        $safetyRating = 1.0;

        if ($startLoc && $endLoc) {
            $aiResult = $aiService->optimizeRoute(
                (float)$startLoc['lat'], 
                (float)$startLoc['lng'], 
                (float)$endLoc['lat'], 
                (float)$endLoc['lng']
            );

            if ($aiResult) {
                $distance = $aiResult['total_distance_meters'] ?? $distance;
                $estimatedTime = $aiResult['estimated_time_seconds'] ?? $estimatedTime;
                $isSafe = $aiResult['is_safe'] ?? $isSafe;
                $safetyRating = $aiResult['safety_score'] ?? $safetyRating;
            }
        }

        // Tạm tạo route array
        $route = EvacuationRoute::create(array_merge($data, [
            'geometry' => \DB::raw("ST_MakeLine(
                (SELECT geometry FROM map_nodes WHERE id = {$data['start_node_id']}),
                (SELECT geometry FROM map_nodes WHERE id = {$data['end_node_id']})
            )"),
            'distance_m' => $distance,
            'estimated_time_seconds' => $estimatedTime,
            'is_safe' => $isSafe,
            'safety_rating' => $safetyRating,
            'status' => 'active',
        ]));

        return ApiResponse::created(
            new EvacuationRouteResource($route->load(['startNode', 'endNode'])),
            'Tạo tuyến sơ tán thành công'
        );
    }

    /**
     * Cập nhật tuyến sơ tán
     * PUT /api/evacuation-routes/{id}
     */
    public function update(Request $request, int $id)
    {
        $route = EvacuationRoute::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive,blocked,flooded',
            'is_primary' => 'sometimes|boolean',
            'max_capacity' => 'nullable|integer|min:1',
            'color' => 'nullable|string|max:7',
        ]);

        $route->update($data);

        return ApiResponse::success(
            new EvacuationRouteResource($route->fresh()),
            'Cập nhật tuyến sơ tán thành công'
        );
    }

    /**
     * Xóa tuyến sơ tán
     * DELETE /api/evacuation-routes/{id}
     */
    public function destroy(int $id)
    {
        $route = EvacuationRoute::findOrFail($id);
        $route->delete();

        return ApiResponse::success(null, 'Xóa tuyến sơ tán thành công');
    }
}
