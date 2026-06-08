<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Jobs\CallAIPrediction;
use App\Models\Prediction;
use Illuminate\Http\Request;

class PredictionController extends Controller
{
    /**
     * Danh sách dự đoán
     * GET /api/predictions
     */
    public function index(Request $request)
    {
        $query = Prediction::with(['model', 'floodZone', 'district'])
            ->orderBy('issued_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('prediction_type')) {
            $query->where('prediction_type', $request->prediction_type);
        }

        if ($request->filled('flood_zone_id')) {
            $query->where('flood_zone_id', $request->flood_zone_id);
        }

        if ($request->filled('horizon_minutes')) {
            $query->where('horizon_minutes', (int) $request->horizon_minutes);
        }

        if ($request->filled('period')) {
            $minutes = match ($request->period) {
                '30m' => 30,
                '1h' => 60,
                '3h' => 180,
                '6h' => 360,
                '12h' => 720,
                '24h' => 1440,
                '48h' => 2880,
                default => null,
            };

            if ($minutes) {
                $query->where('horizon_minutes', '<=', $minutes);
            }
        }

        if ($request->boolean('recent_only')) {
            $query->recent(24);
        }

        $predictions = $query->paginate($request->get('per_page', 20));

        $data = $predictions->map(fn ($p) => $this->formatPrediction($p));

        return ApiResponse::paginate($predictions->setCollection($data));
    }

    /**
     * Chi tiết dự đoán
     * GET /api/predictions/{id}
     */
    public function show(int $id)
    {
        $prediction = Prediction::with([
            'model', 'floodZone', 'district', 'verifier',
            'predictionDetails',
        ])->find($id);

        if (! $prediction) {
            return ApiResponse::notFound('Không tìm thấy dự đoán');
        }

        return ApiResponse::success($this->formatPrediction($prediction, true));
    }

    /**
     * Kích hoạt dự đoán AI
     * POST /api/predictions/trigger
     */
    public function trigger(Request $request)
    {
        $data = $request->validate([
            'incident_id' => 'nullable|exists:incidents,id',
            'flood_zone_id' => 'nullable|exists:flood_zones,id',
            'horizon_minutes' => 'nullable|integer|in:15,30,60,120,240,1440',
            'sync' => 'nullable|boolean',
        ]);

        $horizon = $data['horizon_minutes'] ?? 60;

        if ($request->boolean('sync')) {
            CallAIPrediction::dispatchSync(
                $data['incident_id'] ?? null,
                $data['flood_zone_id'] ?? null,
                $horizon
            );
        } else {
            CallAIPrediction::dispatch(
                $data['incident_id'] ?? null,
                $data['flood_zone_id'] ?? null,
                $horizon
            );
        }

        return ApiResponse::success([
            'message' => 'Yêu cầu dự đoán đã được gửi',
            'horizon_minutes' => $horizon,
        ], 'Dự đoán AI đang được xử lý');
    }

    /**
     * Xác nhận dự đoán
     * PUT /api/predictions/{id}/verify
     */
    public function verify(Request $request, int $id)
    {
        $prediction = Prediction::find($id);

        if (! $prediction) {
            return ApiResponse::notFound('Không tìm thấy dự đoán');
        }

        $prediction->verify($request->user()->id);

        return ApiResponse::success($this->formatPrediction($prediction), 'Dự đoán đã được xác nhận');
    }

    /**
     * Format prediction response
     */
    protected function formatPrediction(Prediction $p, bool $detailed = false): array
    {
        $data = [
            'id' => $p->id,
            'prediction_type' => $p->prediction_type,
            'model' => $p->model ? [
                'id' => $p->model->id,
                'name' => $p->model->name,
                'version' => $p->model->version,
            ] : null,
            'model_version' => $p->model_version,
            'prediction_for' => $p->prediction_for?->toIso8601String(),
            'horizon_minutes' => $p->horizon_minutes,
            'issued_at' => $p->issued_at?->toIso8601String(),
            'predicted_value' => $p->predicted_value,
            'confidence' => $p->confidence,
            'probability' => $p->probability,
            'severity' => $p->severity,
            'severity_label' => $p->translated('severity'),
            'status' => $p->status,
            'status_label' => $p->translated('status'),
            'flood_zone' => $p->floodZone ? [
                'id' => $p->floodZone->id,
                'name' => $p->floodZone->name,
            ] : null,
            'district' => $p->district ? [
                'id' => $p->district->id,
                'name' => $p->district->name,
            ] : null,
            'input_data' => $p->input_data,
            'processing_time_ms' => $p->processing_time_ms,
            'created_at' => $p->created_at?->toIso8601String(),
            'updated_at' => $p->updated_at?->toIso8601String(),
        ];

        if ($detailed) {
            $data['verified_by'] = $p->verifier ? [
                'id' => $p->verifier->id,
                'name' => $p->verifier->name,
            ] : null;
            $data['verified_at'] = $p->verified_at?->toIso8601String();
            $data['prediction_details'] = $p->predictionDetails->map(fn ($d) => [
                'entity_type' => $d->entity_type,
                'entity_id' => $d->entity_id,
                'predicted_value' => $d->predicted_value,
                'probability' => $d->probability,
                'severity' => $d->severity,
                'risk_factors' => $d->risk_factors,
            ]);
        }

        return $data;
    }
}
