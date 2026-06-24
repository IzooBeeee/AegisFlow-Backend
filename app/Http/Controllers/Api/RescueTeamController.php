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
     * Cập nhật trạng thái
     * PUT /api/rescue-teams/{id}/status
     */
    public function updateStatus(Request $request, int $id)
    {
        $team = RescueTeam::find($id);

        if (! $team) {
            return ApiResponse::notFound('Không tìm thấy đội cứu hộ');
        }

        // Only allow members of the team or admins to change status
        $user = $request->user();
        if (!$user->hasRole(['city_admin', 'rescue_operator'])) {
            $member = $this->getOrCreateMember($user, $id);
            
            if (!$member) {
                return ApiResponse::forbidden('Bạn không có quyền cập nhật trạng thái của đội này');
            }
        }

        $data = $request->validate([
            'status' => 'required|string|in:available,offline',
        ]);

        // Prevent setting to available if they are dispatched or busy with an active incident
        if ($data['status'] === 'available' && in_array($team->status, ['dispatched', 'busy'])) {
            return ApiResponse::error('Đội đang làm nhiệm vụ, không thể chuyển sang trạng thái sẵn sàng lúc này.', 400);
        }

        $team->status = $data['status'];
        $team->save();

        return ApiResponse::success($this->formatTeam($team), 'Cập nhật trạng thái thành công');
    }

    /**
     * Đội của người dùng hiện tại
     * GET /api/rescue-teams/my
     */
    public function myTeam(Request $request)
    {
        $user = $request->user();
        $member = $this->getOrCreateMember($user);

        if (!$member || !$member->team) {
            return ApiResponse::error('Bạn chưa thuộc đội cứu hộ nào', 404);
        }

        return ApiResponse::success($this->formatTeam($member->team));
    }

    /**
     * Lấy hoặc tự động tạo liên kết đội cứu hộ cho user (nếu bị thiếu do seeder)
     */
    protected function getOrCreateMember($user, ?int $teamId = null)
    {
        $query = \App\Models\RescueMember::with('team.district')
            ->where('user_id', $user->id);
            
        if ($teamId) {
            $query->where('team_id', $teamId);
        }
        
        $member = $query->first();

        if (!$member) {
            $team = null;
            if ($teamId) {
                $team = RescueTeam::find($teamId);
            } else {
                // Thử tìm team trùng tên với user (ví dụ: 'Đội cứu hộ PCCC Cẩm Lệ (Tăng cường)')
                $team = RescueTeam::where('name', $user->name)->first();
                
                if (!$team) {
                    // Thử map theo email từ seeder
                    $emailToCode = [
                        'team_haichau@aegisflow.ai' => 'RESCUE-010',
                        'team_thanhkhe@aegisflow.ai' => 'RESCUE-011',
                        'team_qk5@aegisflow.ai' => 'RESCUE-012',
                        'team_lienchieu@aegisflow.ai' => 'RESCUE-013',
                        'team_nguhanhson@aegisflow.ai' => 'RESCUE-014',
                        'team_sontra@aegisflow.ai' => 'RESCUE-015',
                        'team_hoavang@aegisflow.ai' => 'RESCUE-016',
                        'team_camle@aegisflow.ai' => 'RESCUE-017',
                    ];
                    if (isset($emailToCode[$user->email])) {
                        $team = RescueTeam::where('code', $emailToCode[$user->email])->first();
                    }
                }
            }

            if ($team) {
                $member = \App\Models\RescueMember::create([
                    'user_id' => $user->id,
                    'team_id' => $team->id,
                    'role' => 'leader',
                    'status' => 'active',
                    'is_available' => true,
                ]);
                $member->load('team.district');
            }
        }

        return $member;
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
