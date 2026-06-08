<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Helpers\ApiResponse;
use App\Http\Resources\SensorResource;
use App\Models\Sensor;
use App\Models\SensorReading;
use Illuminate\Http\Request;

class SensorController extends Controller
{
    /**
     * Danh sách cảm biến
     * GET /api/sensors
     */
    public function index(Request $request)
    {
        $query = Sensor::with(['floodZone', 'district'])
            ->active()
            ->orderBy('name');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('flood_zone_id')) {
            $query->where('flood_zone_id', $request->flood_zone_id);
        }

        if ($request->filled('district_id')) {
            $query->where('district_id', $request->district_id);
        }

        $sensors = $query->paginate($request->get('per_page', 50));

        return ApiResponse::paginate($sensors->setCollection(
            collect(SensorResource::collection($sensors->getCollection())->resolve())
        ));
    }

    /**
     * Chi tiết cảm biến
     * GET /api/sensors/{id}
     */
    public function show(int $id)
    {
        $sensor = Sensor::with(['floodZone', 'district', 'latestReadings'])
            ->find($id);

        if (! $sensor) {
            return ApiResponse::notFound('Không tìm thấy cảm biến');
        }

        return ApiResponse::success(new SensorResource($sensor));
    }

    /**
     * Readings của cảm biến
     * GET /api/sensors/{id}/readings
     */
    public function readings(Request $request, int $id)
    {
        $sensor = Sensor::find($id);

        if (! $sensor) {
            return ApiResponse::notFound('Không tìm thấy cảm biến');
        }

        $query = $sensor->readings()
            ->orderBy('recorded_at', 'desc');

        if ($request->filled('from')) {
            $query->where('recorded_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->where('recorded_at', '<=', $request->to);
        }

        if ($request->boolean('anomalies_only')) {
            $query->where('is_anomaly', true);
        }

        $readings = $query->paginate($request->get('per_page', 100));

        return ApiResponse::paginate($readings);
    }

    /**
     * Gửi dữ liệu cảm biến
     * POST /api/sensor-data
     */
    public function ingest(Request $request)
    {
        $data = $request->validate([
            'sensor_id' => 'required|exists:sensors,id',
            'value' => 'required|numeric',
            'recorded_at' => 'nullable|date',
            'source' => 'nullable|string',
            'raw_data' => 'nullable|array',
        ]);

        $sensor = Sensor::find($data['sensor_id']);

        $reading = SensorReading::create([
            'sensor_id' => $data['sensor_id'],
            'recorded_at' => $data['recorded_at'] ?? now(),
            'value' => $data['value'],
            'source' => $data['source'] ?? 'sensor',
            'raw_data' => $data['raw_data'] ?? null,
            'quality_score' => 1.0,
            'is_anomaly' => $sensor->isAnomaly($data['value']),
        ]);

        // Cập nhật last_value của sensor
        $sensor->updateLastReading($data['value'], $reading->recorded_at);

        // Kiểm tra ngưỡng → cập nhật flood zone
        if ($sensor->floodZone && $sensor->type === 'water_level') {
            if ($data['value'] > $sensor->floodZone->current_water_level_m) {
                $sensor->floodZone->updateWaterLevel($data['value']);
            }
        }

        return ApiResponse::success([
            'reading_id' => $reading->id,
            'is_anomaly' => $reading->is_anomaly,
        ], 'Dữ liệu đã được ghi nhận');
    }

    /**
     * Batch ingest
     * POST /api/sensor-data/batch
     */
    public function batchIngest(Request $request)
    {
        $data = $request->validate([
            'readings' => 'required|array|min:1|max:100',
            'readings.*.sensor_id' => 'required|exists:sensors,id',
            'readings.*.value' => 'required|numeric',
            'readings.*.recorded_at' => 'nullable|date',
            'readings.*.source' => 'nullable|string',
        ]);

        $results = [];

        foreach ($data['readings'] as $readingData) {
            $sensor = Sensor::find($readingData['sensor_id']);

            $reading = SensorReading::create([
                'sensor_id' => $readingData['sensor_id'],
                'recorded_at' => $readingData['recorded_at'] ?? now(),
                'value' => $readingData['value'],
                'source' => $readingData['source'] ?? 'sensor',
                'is_anomaly' => $sensor->isAnomaly($readingData['value']),
            ]);

            $sensor->updateLastReading($readingData['value'], $reading->recorded_at);

            $results[] = [
                'reading_id' => $reading->id,
                'sensor_id' => $readingData['sensor_id'],
                'is_anomaly' => $reading->is_anomaly,
            ];
        }

        return ApiResponse::success(['processed' => count($results), 'readings' => $results], 'Đã xử lý '.count($results).' readings');
    }
}
