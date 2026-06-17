<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\RescueTeam;
use Illuminate\Http\Request;

class RescueTeamController extends Controller
{
    /**
     * Danh sách đội cứu hộ
     * GET /api/rescue-teams
     */
    public function index(Request $request)
    {
        $query = RescueTeam::with('district')
            ->orderBy('status')
            ->orderBy('name');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('team_type')) {
            $query->where('team_type', $request->team_type);
        }

        if ($request->filled('district_id')) {
            $query->where('district_id', $request->district_id);
        }

        if ($request->boolean('available_only')) {
            $query->available();
        }

        $teams = $query->paginate($request->get('per_page', 20));

        $data = $teams->map(fn ($t) => $this->formatTeam($t));

        return ApiResponse::paginate($teams->setCollection($data));
    }

    /**
     * Chi tiết đội
     * GET /api/rescue-teams/{id}
     */
    public function show(int $id)
    {
        $team = RescueTeam::with(['district', 'members.user', 'assignedRequests'])
            ->find($id);

        if (! $team) {
            return ApiResponse::notFound('Không tìm thấy đội cứu hộ');
        }

        return ApiResponse::success($this->formatTeam($team, true));
    }

    /**
     * Cập nhật vị trí GPS
     * PUT /api/rescue-teams/{id}/location
     */
    public function updateLocation(Request $request, int $id)
    {
        $team = RescueTeam::find($id);

        if (! $team) {
            return ApiResponse::notFound('Không tìm thấy đội cứu hộ');
        }

        $data = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $team->updateLocation($data['latitude'], $data['longitude']);

        return ApiResponse::success([
            'latitude' => $team->current_latitude,
            'longitude' => $team->current_longitude,
            'updated_at' => $team->last_location_update?->toIso8601String(),
        ], 'Cập nhật vị trí thành công');
    }

    /**
     * Đội của người dùng hiện tại
     * GET /api/rescue-teams/my
     */
    public function myTeam(Request $request)
    {
        $member = \App\Models\RescueMember::with('team.district')
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$member || !$member->team) {
            return ApiResponse::error('Bạn chưa thuộc đội cứu hộ nào', 404);
        }

        return ApiResponse::success($this->formatTeam($member->team));
    }

    /**
     * Format team response
     */
    protected function formatTeam(RescueTeam $team, bool $detailed = false): array
    {
        $data = [
            'id' => $team->id,
            'name' => $team->name,
            'code' => $team->code,
            'team_type' => $team->team_type,
            'team_type_label' => $team->translated('team_type'),
            'specializations' => $team->specializations ?? [],
            'status' => $team->status,
            'status_label' => $team->translated('status'),
            'status_color' => $team->status_color,
            'vehicle_count' => $team->vehicle_count,
            'personnel_count' => $team->personnel_count,
            'phone' => $team->phone,
            'current_latitude' => $team->current_latitude,
            'current_longitude' => $team->current_longitude,
            'location' => $team->location,
            'district' => $team->district ? ['id' => $team->district->id, 'name' => $team->district->name] : null,
            'created_at' => $team->created_at?->toIso8601String(),
        ];

        if ($detailed) {
            $data['equipment'] = $team->equipment ?? [];
            $data['last_location_update'] = $team->last_location_update?->toIso8601String();
            $data['members'] = $team->members->map(fn ($m) => [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'name' => $m->user?->name,
                'role' => $m->role,
                'is_available' => $m->is_available,
            ]);
            $data['active_missions'] = $team->assignedRequests()
                ->whereIn('status', ['assigned', 'in_progress'])
                ->count();
        }

        return $data;
    }
}
