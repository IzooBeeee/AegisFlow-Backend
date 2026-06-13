<?php

namespace App\Http\Controllers\Api;

use App\Enums\RescueRequestStatusEnum;
use App\Events\RescueRequestCreated;
use App\Events\RescueRequestUpdated;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\RescueRequest;
use App\Models\RescueTeam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RescueRequestController extends Controller
{
    /**
     * Danh sách yêu cầu cứu hộ
     * GET /api/rescue-requests
     */
    public function index(Request $request)
    {
        $query = RescueRequest::with(['district', 'assignedTeam', 'reporter'])
            ->orderBy('created_at', 'desc')
            ->orderBy('priority_score', 'desc');

        // Citizens chỉ thấy yêu cầu của mình
        $user = $request->user();
        if (! $user->hasRole(['city_admin', 'rescue_operator', 'rescue_team', 'traffic_operator'])) {
            $query->where('reported_by', $user->id);
        }

        if ($request->filled('status')) {
            $statuses = explode(',', $request->status);
            if (count($statuses) > 1) {
                $query->whereIn('status', $statuses);
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->filled('urgency')) {
            $query->where('urgency', $request->urgency);
        }

        if ($request->filled('district_id')) {
            $query->where('district_id', $request->district_id);
        }

        if ($request->filled('assigned_team_id')) {
            $query->where('assigned_team_id', $request->assigned_team_id);
        }

        $requests = $query->paginate($request->get('per_page', 20));

        $data = $requests->map(fn ($r) => $this->formatRequest($r));

        return ApiResponse::paginate($requests->setCollection($data));
    }

    /**
     * Danh sách yêu cầu đang chờ
     * GET /api/rescue-requests/pending
     */
    public function pending(Request $request)
    {
        $query = RescueRequest::with(['district', 'assignedTeam'])
            ->pending()
            ->orderBy('created_at', 'desc')
            ->orderBy('priority_score', 'desc');

        $requests = $query->paginate($request->get('per_page', 50));

        $data = $requests->map(fn ($r) => $this->formatRequest($r));

        return ApiResponse::paginate($requests->setCollection($data));
    }

    /**
     * Chi tiết yêu cầu
     * GET /api/rescue-requests/{id}
     */
    public function show(int $id, Request $request)
    {
        $req = RescueRequest::with([
            'district', 'ward', 'incident',
            'assignedTeam', 'reporter', 'events',
        ])->find($id);

        if (! $req) {
            return ApiResponse::notFound('Không tìm thấy yêu cầu');
        }

        // Citizens chỉ thấy yêu cầu của mình
        $user = $request->user();
        if (! $user->hasRole(['city_admin', 'rescue_operator', 'rescue_team', 'traffic_operator'])) {
            if ($req->reported_by !== $user->id) {
                return ApiResponse::forbidden();
            }
        }

        return ApiResponse::success($this->formatRequest($req, true));
    }

    /**
     * Tạo yêu cầu cứu hộ
     * POST /api/rescue-requests
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'caller_name' => 'required|string|max:255',
            'caller_phone' => 'nullable|string|max:20',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'required|string',
            'district_id' => 'nullable|exists:districts,id',
            'ward_id' => 'nullable|exists:wards,id',
            'urgency' => 'required|in:low,medium,high,critical',
            'category' => 'required|in:medical,food,water,rescue,evacuation,shelter,other',
            'people_count' => 'nullable|integer|min:1|max:1000',
            'vulnerable_groups' => 'nullable|array',
            'vulnerable_groups.*' => 'in:children,elderly,disabled,pregnant',
            'description' => 'nullable|string|max:2000',
            'photo_urls' => 'nullable|array',
            'photo_urls.*' => 'url',
            'water_level_m' => 'nullable|numeric|min:0',
            'accessibility_notes' => 'nullable|string|max:500',
        ]);

        $user = $request->user();

        $req = RescueRequest::create([
            'caller_name' => $data['caller_name'],
            'caller_phone' => $data['caller_phone'] ?? null,
            'address' => $data['address'],
            'district_id' => $data['district_id'] ?? null,
            'ward_id' => $data['ward_id'] ?? null,
            'urgency' => $data['urgency'],
            'category' => $data['category'],
            'people_count' => $data['people_count'] ?? 1,
            'vulnerable_groups' => $data['vulnerable_groups'] ?? [],
            'description' => $data['description'] ?? null,
            'photo_urls' => $data['photo_urls'] ?? [],
            'water_level_m' => $data['water_level_m'] ?? null,
            'accessibility_notes' => $data['accessibility_notes'] ?? null,
            'status' => RescueRequestStatusEnum::PENDING->value,
            'reported_by' => $user->id,
        ]);

        // Lưu PostGIS geometry
        if (DB::connection()->getDriverName() === 'pgsql') {
            try {
            DB::statement(
                            'UPDATE rescue_requests SET geometry = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                            [$data['longitude'], $data['latitude'], $req->id]
                        );
        } catch (\Exception $e) {}
        }

        // Tính priority score
        $req->calculatePriorityScore();

        $req->events()->create([
            'event_type' => 'created',
            'description' => 'Yêu cầu được tạo bởi '.$user->name,
            'actor_id' => $user->id,
        ]);

        // Broadcast event
        broadcast(new RescueRequestCreated($req->fresh()))->toOthers();

        return ApiResponse::created($this->formatRequest($req->fresh()), 'Yêu cầu cứu hộ đã được gửi');
    }

    /**
     * Cập nhật yêu cầu
     * PUT /api/rescue-requests/{id}
     */
    public function update(Request $request, int $id)
    {
        $req = RescueRequest::find($id);

        if (! $req) {
            return ApiResponse::notFound('Không tìm thấy yêu cầu');
        }

        $data = $request->validate([
            'caller_name' => 'sometimes|string|max:255',
            'caller_phone' => 'nullable|string|max:20',
            'urgency' => 'nullable|in:low,medium,high,critical',
            'description' => 'nullable|string|max:2000',
            'photo_urls' => 'nullable|array',
        ]);

        $req->update($data);

        return ApiResponse::success($this->formatRequest($req->fresh()), 'Cập nhật thành công');
    }

    /**
     * Phân công đội cứu hộ
     * PUT /api/rescue-requests/{id}/assign
     */
    public function assign(Request $request, int $id)
    {
        if (! $request->user()->hasRole(['city_admin', 'rescue_operator', 'rescue_team'])) {
            return ApiResponse::forbidden('Bạn không có quyền phân công yêu cầu cứu hộ');
        }

        $req = RescueRequest::find($id);

        if (! $req) {
            return ApiResponse::notFound('Không tìm thấy yêu cầu');
        }

        $data = $request->validate([
            'team_id' => 'required|exists:rescue_teams,id',
        ]);

        $team = RescueTeam::find($data['team_id']);

        if (! $team->isAvailable()) {
            return ApiResponse::error('Đội cứu hộ không sẵn sàng', 400);
        }

        $req->assignTeam($team);

        $req->events()->create([
            'event_type' => 'assigned',
            'description' => 'Được phân công cho đội: '.$team->name,
            'actor_id' => $request->user()->id,
        ]);

        // Broadcast event
        broadcast(new RescueRequestUpdated($req->fresh()))->toOthers();

        return ApiResponse::success($this->formatRequest($req->fresh()), 'Phân công thành công');
    }

    /**
     * Cập nhật trạng thái yêu cầu
     * PUT /api/rescue-requests/{id}/status
     */
    public function updateStatus(Request $request, int $id)
    {
        if (! $request->user()->hasRole(['city_admin', 'rescue_operator', 'rescue_team'])) {
            return ApiResponse::forbidden('Bạn không có quyền cập nhật trạng thái yêu cầu cứu hộ');
        }

        $req = RescueRequest::find($id);

        if (! $req) {
            return ApiResponse::notFound('Không tìm thấy yêu cầu');
        }

        $data = $request->validate([
            'status' => 'required|in:pending,assigned,in_progress,completed,cancelled',
            'notes' => 'nullable|string',
        ]);

        $oldStatus = $req->status;
        $user = $request->user();

        $req->status = $data['status'];
        $req->save();

        // Cập nhật trạng thái đặc biệt
        if ($data['status'] === 'completed') {
            $req->complete();
        }

        $eventTypes = [
            'assigned' => 'Đã phân công',
            'in_progress' => 'Bắt đầu xử lý',
            'completed' => 'Hoàn thành',
            'cancelled' => 'Đã hủy: '.($data['notes'] ?? ''),
        ];

        $req->events()->create([
            'event_type' => $data['status'],
            'description' => $eventTypes[$data['status']] ?? 'Cập nhật trạng thái',
            'actor_id' => $user->id,
            'metadata' => [
                'old_status' => $oldStatus,
                'new_status' => $data['status'],
                'notes' => $data['notes'] ?? null,
            ],
        ]);

        // Broadcast event
        broadcast(new RescueRequestUpdated($req->fresh()))->toOthers();

        return ApiResponse::success($this->formatRequest($req->fresh()), 'Cập nhật trạng thái thành công');
    }

    /**
     * Đánh giá yêu cầu
     * POST /api/rescue-requests/{id}/rate
     */
    public function rate(Request $request, int $id)
    {
        $req = RescueRequest::find($id);

        if (! $req) {
            return ApiResponse::notFound('Không tìm thấy yêu cầu');
        }

        $data = $request->validate([
            'rating' => 'required|integer|between:1,5',
            'feedback' => 'nullable|string|max:1000',
        ]);

        $req->update([
            'rating' => $data['rating'],
            'feedback' => $data['feedback'] ?? null,
        ]);

        return ApiResponse::success(null, 'Cảm ơn bạn đã đánh giá');
    }

    /**
     * Format request response
     */
    protected function formatRequest(RescueRequest $req, bool $detailed = false): array
    {
        $data = [
            'id' => $req->id,
            'request_number' => $req->request_number,
            'caller_name' => $req->caller_name,
            'caller_phone' => $req->caller_phone,
            'address' => $req->address,
            'urgency' => $req->urgency,
            'urgency_label' => $req->translated('urgency'),
            'category' => $req->category,
            'category_label' => $req->translated('category'),
            'people_count' => $req->people_count,
            'vulnerable_groups' => $req->vulnerable_groups ?? [],
            'description' => $req->description,
            'photo_urls' => $req->photo_urls ?? [],
            'status' => $req->status,
            'status_label' => $req->translated('status'),
            'priority_score' => $req->priority_score,
            'location' => $req->location,
            'water_level_m' => $req->water_level_m,
            'district' => $req->district ? ['id' => $req->district->id, 'name' => $req->district->name] : null,
            'assigned_team' => $req->assignedTeam ? [
                'id' => $req->assignedTeam->id,
                'name' => $req->assignedTeam->name,
                'phone' => $req->assignedTeam->phone,
                'status' => $req->assignedTeam->status,
            ] : null,
            'eta_minutes' => $req->eta_minutes,
            'rating' => $req->rating,
            'created_at' => $req->created_at?->toIso8601String(),
        ];

        if ($detailed) {
            $data['ward'] = $req->ward ? ['id' => $req->ward->id, 'name' => $req->ward->name] : null;
            $data['incident'] = $req->incident ? ['id' => $req->incident->id, 'title' => $req->incident->title] : null;
            $data['accessibility_notes'] = $req->accessibility_notes;
            $data['reporter'] = $req->reporter ? ['id' => $req->reporter->id, 'name' => $req->reporter->name] : null;
            $data['completed_at'] = $req->completed_at?->toIso8601String();
            $data['events'] = $req->events->map(fn ($e) => [
                'id' => $e->id,
                'event_type' => $e->event_type,
                'description' => $e->description,
                'created_at' => $e->created_at?->toIso8601String(),
            ]);
        }

        return $data;
    }
}
