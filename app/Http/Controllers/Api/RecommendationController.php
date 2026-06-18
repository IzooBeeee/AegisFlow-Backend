<?php

namespace App\Http\Controllers\Api;

use App\Enums\AlertStatusEnum;
use App\Enums\AlertTypeEnum;
use App\Events\AlertCreated;
use App\Events\NotificationSent;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Recommendation;
use App\Models\RescueRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class RecommendationController extends Controller
{
    /**
     * Danh sách đề xuất
     * GET /api/recommendations
     */
    public function index(Request $request)
    {
        $query = Recommendation::with(['prediction.floodZone.district', 'incident'])
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
        $item = Recommendation::with(['prediction.floodZone.district', 'incident', 'approver'])
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
        $item = Recommendation::with(['prediction.floodZone.district', 'incident'])->find($id);

        if (! $item) {
            return ApiResponse::notFound('Không tìm thấy đề xuất');
        }

        $item->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        // Thực thi hành động nghiệp vụ tương ứng với loại đề xuất
        $actionResult = $this->executeApprovalAction($item, $request->user());

        // Gửi thông báo realtime đến tất cả user
        $this->broadcastApprovalNotification($item, $actionResult);

        $item->update(['executed_at' => now()]);

        return ApiResponse::success(
            array_merge($this->formatRecommendation($item), ['action' => $actionResult]),
            'Đã phê duyệt và thực thi đề xuất'
        );
    }

    /**
     * Thực thi logic nghiệp vụ khi phê duyệt từng loại đề xuất.
     */
    protected function executeApprovalAction(Recommendation $item, $approver): array
    {
        $details = $item->details ?? [];
        $floodZone = $item->prediction?->floodZone;
        $district = $floodZone?->district;
        $districtIds = $district ? [(string) $district->id] : [];
        $floodZoneIds = $floodZone ? [(string) $floodZone->id] : [];

        return match ($item->type) {
            'alert' => $this->executeAlertAction($item, $approver, $details, $districtIds, $floodZoneIds),
            'evacuation' => $this->executeEvacuationAction($item, $approver, $details, $districtIds, $floodZoneIds),
            'rescue_dispatch' => $this->executeRescueDispatchAction($item, $approver, $details, $floodZone, $district),
            'reroute' => $this->executeRerouteAction($item, $approver, $details, $districtIds, $floodZoneIds),
            'priority_route' => $this->executePriorityRouteAction($item, $approver, $details, $districtIds, $floodZoneIds),
            'supply_dispatch' => $this->executeSupplyDispatchAction($item, $approver, $details, $districtIds, $floodZoneIds),
            'signal_control' => $this->executeSignalControlAction($item, $approver, $details, $districtIds),
            default => ['type' => 'notification_only', 'message' => 'Đã ghi nhận phê duyệt'],
        };
    }

    protected function executeAlertAction(Recommendation $item, $approver, array $details, array $districtIds, array $floodZoneIds): array
    {
        $alert = Alert::create([
            'title' => '[AI] '.($details['title'] ?? 'Cảnh báo ngập lụt'),
            'description' => $item->description,
            'alert_type' => AlertTypeEnum::FLOOD_WARNING->value,
            'severity' => $details['severity'] ?? 'high',
            'status' => AlertStatusEnum::ACTIVE->value,
            'affected_districts' => $districtIds,
            'affected_wards' => [],
            'affected_flood_zones' => $floodZoneIds,
            'source' => 'ai',
            'issued_by' => $approver->id,
            'related_incident_id' => $item->incident_id,
            'related_prediction_id' => $item->prediction_id,
        ]);
        event(new AlertCreated($alert));

        return ['type' => 'alert_created', 'alert_id' => $alert->id];
    }

    protected function executeEvacuationAction(Recommendation $item, $approver, array $details, array $districtIds, array $floodZoneIds): array
    {
        $alert = Alert::create([
            'title' => '[AI] Lệnh sơ tán: '.($details['target_zone'] ?? 'Khu vực nguy hiểm'),
            'description' => $item->description,
            'alert_type' => AlertTypeEnum::EVACUATION->value,
            'severity' => 'critical',
            'status' => AlertStatusEnum::ACTIVE->value,
            'affected_districts' => $districtIds,
            'affected_wards' => [],
            'affected_flood_zones' => $floodZoneIds,
            'source' => 'ai',
            'issued_by' => $approver->id,
            'related_incident_id' => $item->incident_id,
            'related_prediction_id' => $item->prediction_id,
        ]);
        event(new AlertCreated($alert));

        return ['type' => 'evacuation_alert_created', 'alert_id' => $alert->id];
    }

    protected function executeRescueDispatchAction(Recommendation $item, $approver, array $details, $floodZone, $district): array
    {
        $requestNumber = 'AI-'.strtoupper(Str::random(6));
        $urgency = $details['urgency'] ?? 'high';

        $rescueRequest = RescueRequest::create([
            'request_number' => $requestNumber,
            'caller_name' => 'Hệ thống AI AegisFlow',
            'caller_phone' => '0000000000',
            'address' => $details['target_zone'] ?? ($floodZone?->name ?? 'Khu vực AI xác định'),
            'district_id' => $district?->id,
            'incident_id' => $item->incident_id,
            'urgency' => $urgency,
            'category' => 'rescue',
            'people_count' => (int) ($details['people_count'] ?? 0),
            'vulnerable_groups' => [],
            'description' => $item->description,
            'water_level_m' => $details['current_level_m'] ?? $details['water_level_m'] ?? null,
            'status' => 'pending',
            'priority_score' => match ($urgency) {
                'critical' => 90,
                'high' => 70,
                'medium' => 50,
                default => 30,
            },
        ]);

        return ['type' => 'rescue_request_created', 'rescue_request_id' => $rescueRequest->id, 'request_number' => $requestNumber];
    }

    protected function executeRerouteAction(Recommendation $item, $approver, array $details, array $districtIds, array $floodZoneIds): array
    {
        $alert = Alert::create([
            'title' => '[AI] Cảnh báo đổi tuyến: '.($details['target_zone'] ?? 'Tuyến đường bị ảnh hưởng'),
            'description' => $item->description,
            'alert_type' => AlertTypeEnum::FLOOD_WARNING->value,
            'severity' => 'medium',
            'status' => AlertStatusEnum::ACTIVE->value,
            'affected_districts' => $districtIds,
            'affected_wards' => [],
            'affected_flood_zones' => $floodZoneIds,
            'source' => 'ai',
            'issued_by' => $approver->id,
            'related_incident_id' => $item->incident_id,
            'related_prediction_id' => $item->prediction_id,
        ]);
        event(new AlertCreated($alert));

        return ['type' => 'reroute_alert_created', 'alert_id' => $alert->id];
    }

    protected function executePriorityRouteAction(Recommendation $item, $approver, array $details, array $districtIds, array $floodZoneIds): array
    {
        $alert = Alert::create([
            'title' => '[AI] Kích hoạt tuyến ưu tiên: '.($details['target_zone'] ?? 'Vùng leo thang rủi ro'),
            'description' => $item->description,
            'alert_type' => AlertTypeEnum::EVACUATION->value,
            'severity' => 'high',
            'status' => AlertStatusEnum::ACTIVE->value,
            'affected_districts' => $districtIds,
            'affected_wards' => [],
            'affected_flood_zones' => $floodZoneIds,
            'source' => 'ai',
            'issued_by' => $approver->id,
            'related_incident_id' => $item->incident_id,
            'related_prediction_id' => $item->prediction_id,
        ]);
        event(new AlertCreated($alert));

        return ['type' => 'priority_route_alert_created', 'alert_id' => $alert->id];
    }

    protected function executeSupplyDispatchAction(Recommendation $item, $approver, array $details, array $districtIds, array $floodZoneIds): array
    {
        $alert = Alert::create([
            'title' => '[AI] Điều vật tư cứu trợ: '.($details['target_zone'] ?? 'Khu vực cần hỗ trợ'),
            'description' => $item->description,
            'alert_type' => AlertTypeEnum::FLOOD_WARNING->value,
            'severity' => 'medium',
            'status' => AlertStatusEnum::ACTIVE->value,
            'affected_districts' => $districtIds,
            'affected_wards' => [],
            'affected_flood_zones' => $floodZoneIds,
            'source' => 'ai',
            'issued_by' => $approver->id,
            'related_incident_id' => $item->incident_id,
            'related_prediction_id' => $item->prediction_id,
        ]);
        event(new AlertCreated($alert));

        return ['type' => 'supply_alert_created', 'alert_id' => $alert->id];
    }

    protected function executeSignalControlAction(Recommendation $item, $approver, array $details, array $districtIds): array
    {
        $alert = Alert::create([
            'title' => '[AI] Điều chỉnh tín hiệu giao thông: '.($details['target_zone'] ?? 'Khu vực ngập'),
            'description' => $item->description,
            'alert_type' => AlertTypeEnum::FLOOD_WARNING->value,
            'severity' => 'medium',
            'status' => AlertStatusEnum::ACTIVE->value,
            'affected_districts' => $districtIds,
            'affected_wards' => [],
            'affected_flood_zones' => [],
            'source' => 'ai',
            'issued_by' => $approver->id,
            'related_incident_id' => $item->incident_id,
            'related_prediction_id' => $item->prediction_id,
        ]);
        event(new AlertCreated($alert));

        return ['type' => 'signal_alert_created', 'alert_id' => $alert->id];
    }

    /**
     * Broadcast thông báo phê duyệt đến toàn bộ user.
     */
    protected function broadcastApprovalNotification(Recommendation $item, array $actionResult): void
    {
        $title = match ($item->type) {
            'rescue_dispatch' => '[AI] Đã điều cứu hộ khẩn cấp',
            'evacuation' => '[AI] Đã ban lệnh sơ tán',
            'alert' => '[AI] Đã phát cảnh báo mới',
            'reroute' => '[AI] Đã kích hoạt đổi tuyến',
            'priority_route' => '[AI] Đã kích hoạt tuyến ưu tiên',
            'supply_dispatch' => '[AI] Đã điều vật tư cứu trợ',
            'signal_control' => '[AI] Đã điều chỉnh tín hiệu',
            default => '[AI] Đề xuất đã được phê duyệt',
        };
        $body = $item->description;

        $users = User::all();
        foreach ($users as $u) {
            $notifId = DB::table('notifications')->insertGetId([
                'title' => $title,
                'body' => $body,
                'data' => json_encode(array_merge(
                    ['id' => $item->id, 'type' => $item->type, 'incident_id' => $item->incident_id],
                    $actionResult
                )),
                'notification_type' => 'RecommendationApproved',
                'target_type' => 'user',
                'target_id' => $u->id,
                'channel' => 'all',
                'status' => 'sent',
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            event(new NotificationSent($u->id, [
                'id' => $notifId,
                'type' => 'RecommendationApproved',
                'title' => $title,
                'message' => $body,
                'noi_dung' => $body,
                'tieu_de' => $title,
                'data' => array_merge(
                    ['id' => $item->id, 'type' => $item->type, 'incident_id' => $item->incident_id],
                    $actionResult
                ),
                'created_at' => now()->toIso8601String(),
            ]));
        }
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

    /**
     * Gọi AI service để phân tích chi tiết đề xuất
     * POST /api/recommendations/{id}/analyze
     */
    public function analyze(int $id)
    {
        $item = Recommendation::with(['prediction.floodZone.district', 'incident'])->find($id);

        if (! $item) {
            return ApiResponse::notFound('Không tìm thấy đề xuất');
        }

        $floodZone = $item->prediction?->floodZone;
        $district = $floodZone?->district;

        $payload = [
            'recommendation_type' => $item->type,
            'description' => $item->description,
            'details' => $item->details ?? [],
            'prediction' => $item->prediction ? [
                'risk_score' => $item->prediction->risk_score ?? ($item->prediction->probability * 100),
                'risk_level' => $item->prediction->severity,
                'probability' => $item->prediction->probability,
                'horizon_minutes' => $item->prediction->horizon_minutes,
            ] : [],
            'zone_name' => $floodZone?->name,
            'district_name' => $district?->name,
        ];

        try {
            $aiServiceUrl = config('services.ai.url', env('AI_SERVICE_URL', 'http://localhost:5005'));
            $response = Http::timeout(10)
                ->post("{$aiServiceUrl}/api/recommendations/analyze", $payload);

            if ($response->successful()) {
                return ApiResponse::success($response->json());
            }
        } catch (\Exception $e) {
            // Fall through to local fallback
        }

        // Fallback: phân tích cơ bản tại backend
        return ApiResponse::success([
            'recommendation_type' => $item->type,
            'urgency' => $item->details['urgency'] ?? 'medium',
            'urgency_score' => 50,
            'estimated_impact' => $item->description,
            'affected_population_estimate' => null,
            'time_sensitivity_minutes' => 60,
            'suggested_actions' => ['Xem xét và thực hiện theo đề xuất'],
            'risk_factors' => ['Dựa trên đánh giá AI'],
            'required_resources' => [],
            'confidence' => 0.6,
            'reasoning' => 'Phân tích từ backend (AI service không khả dụng)',
        ]);
    }

    protected function formatRecommendation($r, bool $detailed = false): array
    {
        $details = $r->details ?? [];

        // Confidence: details → prediction
        $confidence = $details['confidence']
            ?? ($details['confidence_score'] ?? null)
            ?? $r->prediction?->confidence;

        // Priority: details.urgency → type mapping
        $priority = $details['urgency'] ?? null;
        if (! $priority) {
            $priority = match ($r->type) {
                'evacuation', 'rescue_dispatch', 'emergency' => 'critical',
                'priority_route', 'alert' => 'high',
                'reroute', 'supply_dispatch' => 'medium',
                default => 'medium',
            };
        }

        // Khu vực: details.target_zone → floodZone.name → prediction.target_area → district.name
        $floodZone = $r->prediction?->floodZone;
        $district = $floodZone?->district;
        $targetArea = $details['target_zone']
            ?? $floodZone?->name
            ?? $r->prediction?->target_area
            ?? ($district ? $district->name.', Đà Nẵng' : null);

        $data = [
            'id' => $r->id,
            'type' => $r->type,
            'type_label' => $r->translated('type'),
            'description' => $r->description,
            'details' => $details,
            'confidence' => $confidence !== null ? (float) $confidence : null,
            'priority' => $priority,
            'target_area' => $targetArea,
            'flood_zone' => $floodZone ? [
                'id' => $floodZone->id,
                'name' => $floodZone->name,
            ] : null,
            'district' => $district ? [
                'id' => $district->id,
                'name' => $district->name,
            ] : null,
            'status' => $r->status,
            'status_label' => $r->translated('status'),
            'prediction' => $r->prediction ? [
                'id' => $r->prediction->id,
                'severity' => $r->prediction->severity,
                'probability' => $r->prediction->probability,
                'predicted_value' => $r->prediction->predicted_value,
                'horizon_minutes' => $r->prediction->horizon_minutes,
                'prediction_for' => $r->prediction->prediction_for?->toIso8601String(),
            ] : null,
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
