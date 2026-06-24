<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\RescueMember;
use App\Models\RescueTeam;
use Illuminate\Http\Request;

class RescueTeamController extends Controller
{
    /**
     * Mapping email tài khoản cứu hộ → mã đội (RESCUE-XXX)
     * Mỗi tài khoản rescue_team đại diện cho 1 đội, không cần thành viên phức tạp
     */
    private const EMAIL_TO_TEAM_CODE = [
        'rescue@aegisflow.ai'              => 'RESCUE-001', // Đội PCCC Liên Chiểu
        'pccc.camle@aegisflow.ai'          => 'RESCUE-002', // Đội PCCC Cẩm Lệ
        'yte.danang@aegisflow.ai'          => 'RESCUE-003', // Đội Y tế Đà Nẵng
        'quandoi.hoavang@aegisflow.ai'     => 'RESCUE-004', // Đội Quân đội Hòa Vang
        'tinhnguyen.thankkhe@aegisflow.ai' => 'RESCUE-005', // Đội Tình nguyện Thanh Khê
    ];

    /**
     * Lấy đội cứu hộ của user đang đăng nhập
     * Logic đơn giản: email → team code → team
     */
    protected function getMyTeam($user): ?RescueTeam
    {
        // Bước 1: Thử rescue_members trước (nếu có)
        $member = RescueMember::where('user_id', $user->id)->with('team.district')->first();
        if ($member?->team) {
            return $member->team;
        }

        // Bước 2: Map theo email
        $code = self::EMAIL_TO_TEAM_CODE[$user->email] ?? null;
        if ($code) {
            $team = RescueTeam::with('district')->where('code', $code)->first();
            if ($team) {
                // Tự động tạo rescue_member để lần sau không cần map nữa
                RescueMember::firstOrCreate(
                    ['user_id' => $user->id, 'team_id' => $team->id],
                    ['role' => 'leader', 'status' => 'active', 'is_available' => true]
                );
                return $team;
            }
        }

        return null;
    }

    // ============================================================
    // API Endpoints
    // ============================================================

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
        $team = RescueTeam::with(['district', 'assignedRequests'])
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
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $team->updateLocation($data['latitude'], $data['longitude']);

        return ApiResponse::success([
            'latitude'   => $team->current_latitude,
            'longitude'  => $team->current_longitude,
            'updated_at' => $team->last_location_update?->toIso8601String(),
        ], 'Cập nhật vị trí thành công');
    }

    /**
     * Cập nhật trạng thái đội
     * PUT /api/rescue-teams/{id}/status
     * - Admin/operator: cập nhật bất kỳ đội nào
     * - rescue_team: chỉ cập nhật đội của mình
     */
    public function updateStatus(Request $request, int $id)
    {
        $team = RescueTeam::find($id);

        if (! $team) {
            return ApiResponse::notFound('Không tìm thấy đội cứu hộ');
        }

        $user = $request->user();

        // Kiểm tra quyền: rescue_team chỉ được cập nhật đội của mình
        if (! $user->hasRole(['city_admin', 'rescue_operator'])) {
            $myTeam = $this->getMyTeam($user);
            if (! $myTeam || $myTeam->id !== $id) {
                return ApiResponse::forbidden('Bạn chỉ có thể cập nhật trạng thái đội của mình');
            }
        }

        $data = $request->validate([
            'status' => 'required|string|in:available,offline',
        ]);

        $team->status = $data['status'];
        $team->save();

        return ApiResponse::success($this->formatTeam($team), 'Cập nhật trạng thái thành công');
    }

    /**
     * Thông tin đội của người dùng đang đăng nhập
     * GET /api/rescue-teams/my
     */
    public function myTeam(Request $request)
    {
        $team = $this->getMyTeam($request->user());

        if (! $team) {
            return ApiResponse::error('Tài khoản của bạn chưa được liên kết với đội cứu hộ nào', 404);
        }

        return ApiResponse::success($this->formatTeam($team));
    }

    // ============================================================
    // Format helper
    // ============================================================

    protected function formatTeam(RescueTeam $team, bool $detailed = false): array
    {
        $data = [
            'id'               => $team->id,
            'name'             => $team->name,
            'code'             => $team->code,
            'team_type'        => $team->team_type,
            'team_type_label'  => $team->translated('team_type'),
            'specializations'  => $team->specializations ?? [],
            'status'           => $team->status,
            'status_label'     => $team->translated('status'),
            'status_color'     => $team->status_color,
            'vehicle_count'    => $team->vehicle_count,
            'personnel_count'  => $team->personnel_count,
            'phone'            => $team->phone,
            'current_latitude' => $team->current_latitude,
            'current_longitude'=> $team->current_longitude,
            'location'         => $team->location,
            'district'         => $team->district
                ? ['id' => $team->district->id, 'name' => $team->district->name]
                : null,
            'created_at'       => $team->created_at?->toIso8601String(),
        ];

        if ($detailed) {
            $data['equipment']           = $team->equipment ?? [];
            $data['last_location_update'] = $team->last_location_update?->toIso8601String();
            $data['active_missions']     = $team->assignedRequests()
                ->whereIn('status', ['assigned', 'in_progress'])
                ->count();
        }

        return $data;
    }
}
