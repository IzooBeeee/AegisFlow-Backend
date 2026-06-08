<?php

namespace App\Services;

use App\Enums\IncidentStatusEnum;
use App\Enums\IncidentTypeEnum;
use App\Enums\SeverityEnum;
use App\Models\FloodZone;
use App\Models\Incident;
use App\Models\Sensor;
use App\Models\SensorReading;

/**
 * FloodAutoDetector — Tự động phát hiện ngập từ dữ liệu cảm biến
 */
class FloodAutoDetector
{
    /**
     * Xử lý một sensor reading — kiểm tra ngưỡng, cập nhật vùng ngập
     *
     * @return bool True nếu phát hiện bất thường
     */
    public function processSensorReading(SensorReading $reading): bool
    {
        $sensor = $reading->sensor;

        if (! $sensor) {
            return false;
        }

        // Chỉ xử lý cảm biến mực nước
        if ($sensor->type !== 'water_level') {
            return false;
        }

        $isAnomaly = $this->checkAnomaly($reading, $sensor);

        if ($isAnomaly) {
            $reading->is_anomaly = true;
            $reading->saveQuietly();

            $this->updateFloodZone($sensor, $reading->value);

            $this->checkAutoIncident($sensor, $reading);
        }

        return $isAnomaly;
    }

    /**
     * Kiểm tra giá trị có bất thường không
     */
    protected function checkAnomaly(SensorReading $reading, Sensor $sensor): bool
    {
        // Kiểm tra danger threshold
        if ($sensor->danger_threshold !== null && $reading->value >= $sensor->danger_threshold) {
            return true;
        }

        // Kiểm tra ngưỡng vùng ngập
        if ($sensor->flood_zone_id) {
            $zone = $sensor->floodZone;
            if ($zone && $reading->value >= $zone->danger_threshold_m) {
                return true;
            }
        }

        // Kiểm tra chất lượng dữ liệu
        if ($reading->quality_score !== null && $reading->quality_score < 0.5) {
            return true;
        }

        return false;
    }

    /**
     * Cập nhật trạng thái vùng ngập
     */
    protected function updateFloodZone(Sensor $sensor, float $value): void
    {
        if (! $sensor->flood_zone_id) {
            return;
        }

        $zone = $sensor->floodZone;

        if (! $zone) {
            return;
        }

        // Cập nhật mực nước hiện tại
        $zone->updateWaterLevel($value);
    }

    /**
     * Tự động tạo incident nếu ngưỡng vượt mức nghiêm trọng
     */
    protected function checkAutoIncident(Sensor $sensor, SensorReading $reading): ?Incident
    {
        if ($sensor->flood_zone_id) {
            $zone = FloodZone::find($sensor->flood_zone_id);

            if ($zone && $zone->risk_level === 'critical' && $reading->value >= $zone->danger_threshold_m) {
                // Kiểm tra đã có incident đang active cho vùng này chưa
                $existing = Incident::where('flood_zone_id', $sensor->flood_zone_id)
                    ->active()
                    ->where('type', IncidentTypeEnum::FLOOD->value)
                    ->first();

                if ($existing) {
                    return null;
                }

                return $this->createAutoIncident($sensor, $reading, $zone);
            }
        }

        return null;
    }

    /**
     * Tạo incident tự động
     */
    protected function createAutoIncident(Sensor $sensor, SensorReading $reading, FloodZone $zone): Incident
    {
        $severity = $reading->value >= $zone->danger_threshold_m ? SeverityEnum::CRITICAL->value : SeverityEnum::HIGH->value;

        $incident = Incident::create([
            'title' => "Ngập tự động phát hiện: {$zone->name}",
            'description' => "Cảm biến {$sensor->name} ghi nhận mực nước {$reading->value}m vượt ngưỡng nguy hiểm.",
            'type' => IncidentTypeEnum::FLOOD->value,
            'severity' => $severity,
            'status' => IncidentStatusEnum::REPORTED->value,
            'source' => 'sensor',
            'district_id' => $sensor->district_id,
            'flood_zone_id' => $sensor->flood_zone_id,
            'water_level_m' => $reading->value,
        ]);

        return $incident;
    }
}
