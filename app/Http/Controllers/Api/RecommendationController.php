<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Helpers\ApiResponse;
use App\Models\Recommendation;
use App\Models\Alert;
use App\Events\NotificationSent;
use App\Events\AlertCreated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecommendationController extends Controller
{
    /**
     * Danh sách đề xuất
     * GET /api/recommendations
     */
    public function index(Request $request)
    {
        $query = Recommendation::with(['prediction', 'incident'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $items = $query->paginate($request->get('per_page', 20));

        $data = $items->map(fn ($r) => $this->formatRecommendation($r));

        return ApiResponse::paginate($items->setCollection($data));
    }

    /**
     * Chi tiết đề xuất
     * GET /api/recommendations/{id}
     */
    public function show(int $id)
    {
        $item = Recommendation::with(['prediction', 'incident', 'approver'])
            ->find($id);

        if (! $item) {
            return ApiResponse::notFound('Không tìm thấy đề xuất');
        }

        return ApiResponse::success($this->formatRecommendation($item, true));
    }

    /**
     * Phê duyệt đề xuất
     * PUT /api/recommendations/{id}/approve
     */
    public function approve(Request $request, int $id)
    {
        $item = Recommendation::find($id);

        if (! $item) {
            return ApiResponse::notFound('Không tìm thấy đề xuất');
        }

        $item->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        // Xác định tiêu đề dựa trên loại đề xuất
        $title = match ($item->type) {
            'rescue_dispatch' => '[AI Đề Xuất] Điều phối cứu hộ khẩn cấp',
            'evacuation' => '[AI Đề Xuất] Yêu cầu sơ tán dân cư',
            'alert' => '[AI Đề Xuất] Cảnh báo thiên tai mới',
            default => '[AI Đề Xuất] Đề xuất hành động mới',
        };

        $body = $item->description;

        // Lưu thông báo vào CSDL và gửi WebSocket Realtime cho tất cả các user trong hệ thống
        $users = \App\Models\User::all();
        foreach ($users as $u) {
            $notifId = DB::table('notifications')->insertGetId([
                'title' => $title,
                'body' => $body,
                'data' => json_encode([
                    'id' => $item->id,
                    'type' => $item->type,
                    'incident_id' => $item->incident_id,
                ]),
                'notification_type' => 'RecommendationApproved',
                'target_type' => 'user',
                'target_id' => $u->id,
                'channel' => 'all',
                'status' => 'sent',
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $wsData = [
                'id' => $notifId,
                'type' => 'report_status',
                'title' => $title,
                'message' => $body,
                'noi_dung' => $body,
                'tieu_de' => $title,
                'data' => [
                    'id' => $item->id,
                    'type' => $item->type,
                    'incident_id' => $item->incident_id,
                ],
                'created_at' => now()->toIso8601String(),
            ];

            // Phát sự kiện real-time qua WebSockets (Reverb)
            event(new NotificationSent($u->id, $wsData));
        }

        // Nếu là đề xuất loại alert, tạo cảnh báo thực tế và phát AlertCreated
        if ($item->type === 'alert') {
            $alert = Alert::create([
                'title' => 'Cảnh báo từ AI: ' . $title,
                'description' => $body,
                'alert_type' => \App\Enums\AlertTypeEnum::FLOOD_WARNING->value ?? 'flood_warning',
                'severity' => 'high',
                'status' => \App\Enums\AlertStatusEnum::ACTIVE->value ?? 'active',
                'affected_districts' => $item->incident?->district_id ? [(string)$item->incident->district_id] : [],
                'affected_wards' => [],
                'affected_flood_zones' => [],
                'source' => 'ai',
                'issued_by' => $request->user()->id,
                'related_incident_id' => $item->incident_id,
                'related_prediction_id' => $item->prediction_id,
            ]);

            event(new AlertCreated($alert));
        }

        return ApiResponse::success($this->formatRecommendation($item), 'Đã phê duyệt đề xuất');
    }

    /**
     * Từ chối đề xuất
     * PUT /api/recommendations/{id}/reject
     */
    public function reject(Request $request, int $id)
    {
        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $item = Recommendation::find($id);

        if (! $item) {
            return ApiResponse::notFound('Không tìm thấy đề xuất');
        }

        $item->update([
            'status' => 'rejected',
            'rejected_reason' => $data['reason'],
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return ApiResponse::success($this->formatRecommendation($item), 'Đã từ chối đề xuất');
    }

    protected function formatRecommendation($r, bool $detailed = false): array
    {
        $data = [
            'id' => $r->id,
            'type' => $r->type,
            'type_label' => $r->translated('type'),
            'description' => $r->description,
            'details' => $r->details,
            'status' => $r->status,
            'status_label' => $r->translated('status'),
            'prediction' => $r->prediction ? ['id' => $r->prediction->id] : null,
            'incident' => $r->incident ? ['id' => $r->incident->id, 'title' => $r->incident->title] : null,
            'created_at' => $r->created_at?->toIso8601String(),
        ];

        if ($detailed) {
            $data['approver'] = $r->approver ? ['id' => $r->approver->id, 'name' => $r->approver->name] : null;
            $data['approved_at'] = $r->approved_at?->toIso8601String();
            $data['rejected_reason'] = $r->rejected_reason;
            $data['executed_at'] = $r->executed_at?->toIso8601String();
        }

        return $data;
    }
}
