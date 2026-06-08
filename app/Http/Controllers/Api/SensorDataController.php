<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Helpers\ApiResponse;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Services\FloodAutoDetector;
use Illuminate\Http\Request;

class SensorDataController extends Controller
{
    public function __construct(
        protected FloodAutoDetector $detector
    ) {}

    /**
     * Nhận dữ liệu từ 1 cảm biến
     * POST /api/sensor-data
     */
    public function ingest(Request $request)
    {
        $data = $request->validate([
            'sensor_id' => 'required|exists:sensors,id',
            'value' => 'required|numeric',
            'recorded_at' => 'nullable|date',
            'raw_data' => 'nullable|array',
            'quality_score' => 'nullable|numeric|between:0,1',
            'source' => 'nullable|string',
        ]);

        $sensor = Sensor::find($data['sensor_id']);

        $reading = SensorReading::create([
            'sensor_id' => $data['sensor_id'],
            'recorded_at' => $data['recorded_at'] ?? now(),
            'value' => $data['value'],
            'raw_data' => $data['raw_data'] ?? null,
            'quality_score' => $data['quality_score'] ?? null,
            'source' => $data['source'] ?? 'sensor',
            'is_anomaly' => false,
        ]);

        // Cập nhật last reading của sensor
        $sensor->updateLastReading($data['value'], $reading->recorded_at);

        // Kiểm tra ngưỡng và tạo incident tự động
        $this->detector->processSensorReading($reading);

        return ApiResponse::success([
            'id' => $reading->id,
            'value' => $reading->value,
            'recorded_at' => $reading->recorded_at->toIso8601String(),
        ], 'Dữ liệu đã được lưu');
    }

    /**
     * Nhận batch dữ liệu từ nhiều cảm biến
     * POST /api/sensor-data/batch
     */
    public function batchIngest(Request $request)
    {
        $data = $request->validate([
            'readings' => 'required|array|min:1|max:1000',
            'readings.*.sensor_id' => 'required|exists:sensors,id',
            'readings.*.value' => 'required|numeric',
            'readings.*.recorded_at' => 'nullable|date',
            'readings.*.raw_data' => 'nullable|array',
            'readings.*.quality_score' => 'nullable|numeric|between:0,1',
            'readings.*.source' => 'nullable|string',
        ]);

        $results = [];
        $anomalies = 0;

        foreach ($data['readings'] as $item) {
            $reading = SensorReading::create([
                'sensor_id' => $item['sensor_id'],
                'recorded_at' => $item['recorded_at'] ?? now(),
                'value' => $item['value'],
                'raw_data' => $item['raw_data'] ?? null,
                'quality_score' => $item['quality_score'] ?? null,
                'source' => $item['source'] ?? 'sensor',
                'is_anomaly' => false,
            ]);

            $sensor = Sensor::find($item['sensor_id']);
            $sensor->updateLastReading($item['value'], $reading->recorded_at);

            $results[] = ['id' => $reading->id, 'sensor_id' => $item['sensor_id']];

            if ($this->detector->processSensorReading($reading)) {
                $anomalies++;
            }
        }

        return ApiResponse::success([
            'processed' => count($results),
            'anomalies_detected' => $anomalies,
            'results' => $results,
        ]);
    }
}
