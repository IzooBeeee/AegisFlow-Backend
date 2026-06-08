<?php

return [

    // ── Severity ──────────────────────────────────────────────
    'severity' => [
        'low' => 'Thấp',
        'medium' => 'Trung bình',
        'high' => 'Cao',
        'critical' => 'Nguy cấp',
    ],

    // ── Urgency ───────────────────────────────────────────────
    'urgency' => [
        'low' => 'Thấp',
        'medium' => 'Trung bình',
        'high' => 'Cao',
        'critical' => 'Khẩn cấp',
    ],

    // ── Incident ──────────────────────────────────────────────
    'incident_type' => [
        'flood' => 'Ngập lụt',
        'heavy_rain' => 'Mưa lớn',
        'landslide' => 'Sạt lở',
        'dam_failure' => 'Sự cố đập',
        'other' => 'Khác',
    ],
    'incident_status' => [
        'reported' => 'Mới báo cáo',
        'verified' => 'Đã xác minh',
        'investigating' => 'Đang kiểm tra',
        'confirmed' => 'Đã xác nhận',
        'responding' => 'Đang ứng phó',
        'resolved' => 'Đã giải quyết',
        'closed' => 'Đã đóng',
        'false_alarm' => 'Báo giả',
    ],
    'incident_source' => [
        'sensor' => 'Cảm biến',
        'citizen' => 'Người dân',
        'operator' => 'Điều phối viên',
        'ai' => 'Hệ thống AI',
    ],

    // ── Rescue Request ────────────────────────────────────────
    'rescue_request_status' => [
        'pending' => 'Đang chờ',
        'assigned' => 'Đã phân công',
        'in_progress' => 'Đang cứu hộ',
        'completed' => 'Hoàn thành',
        'cancelled' => 'Đã hủy',
    ],
    'rescue_category' => [
        'medical' => 'Y tế',
        'food' => 'Lương thực',
        'water' => 'Nước uống',
        'rescue' => 'Cứu hộ',
        'evacuation' => 'Sơ tán',
        'shelter' => 'Chỗ ở',
        'other' => 'Khác',
    ],

    // ── Rescue Team ───────────────────────────────────────────
    'rescue_team_status' => [
        'available' => 'Sẵn sàng',
        'on_mission' => 'Đang làm nhiệm vụ',
        'resting' => 'Đang nghỉ',
        'offline' => 'Ngoại tuyến',
    ],
    'rescue_team_type' => [
        'medical' => 'Y Tế',
        'fire' => 'PCCC',
        'military' => 'Quân Đội',
        'volunteer' => 'Tình Nguyện',
        'special' => 'Đặc Nhiệm',
    ],

    // ── Alert ─────────────────────────────────────────────────
    'alert_type' => [
        'flood' => 'Lũ lụt',
        'storm' => 'Bão',
        'landslide' => 'Sạt lở',
        'evacuation' => 'Sơ tán',
        'system' => 'Hệ thống',
        'weather' => 'Thời tiết',
        'flood_risk' => 'Nguy cơ ngập',
        'power_outage' => 'Mất điện',
        'traffic' => 'Giao thông',
    ],
    'alert_status' => [
        'draft' => 'Nháp',
        'active' => 'Đang hiệu lực',
        'updated' => 'Đã cập nhật',
        'resolved' => 'Đã giải quyết',
        'expired' => 'Hết hạn',
    ],

    // ── Flood Zone ────────────────────────────────────────────
    'flood_zone_risk' => [
        'low' => 'Thấp',
        'medium' => 'Trung bình',
        'high' => 'Cao',
        'critical' => 'Nguy cấp',
    ],
    'flood_zone_status' => [
        'monitoring' => 'Theo dõi',
        'alert' => 'Cảnh báo',
        'danger' => 'Nguy hiểm',
        'flooded' => 'Đang ngập',
        'recovering' => 'Phục hồi',
    ],

    // ── Sensor ────────────────────────────────────────────────
    'sensor_type' => [
        'water_level' => 'Mực nước',
        'rainfall' => 'Lượng mưa',
        'camera' => 'Camera AI',
        'wind' => 'Gió',
        'temperature' => 'Nhiệt độ',
        'humidity' => 'Độ ẩm',
        'combined' => 'Tổng hợp',
    ],
    'sensor_status' => [
        'online' => 'Trực tuyến',
        'offline' => 'Mất kết nối',
        'maintenance' => 'Bảo trì',
        'error' => 'Lỗi',
    ],

    // ── Shelter ───────────────────────────────────────────────
    'shelter_status' => [
        'open' => 'Mở cửa',
        'full' => 'Đã đầy',
        'maintenance' => 'Bảo trì',
        'closed' => 'Đóng cửa',
    ],

    // ── Prediction ────────────────────────────────────────────
    'prediction_status' => [
        'pending' => 'Chờ duyệt',
        'verified' => 'Đã xác thực',
        'alerted' => 'Đã cảnh báo',
        'expired' => 'Hết hạn',
        'superseded' => 'Đã thay thế',
    ],

    // ── Recommendation ────────────────────────────────────────
    'recommendation_type' => [
        'priority_route' => 'Tuyến ưu tiên',
        'alert' => 'Cảnh báo',
        'evacuation' => 'Sơ tán',
        'reroute' => 'Đổi tuyến',
        'resource' => 'Vật tư',
    ],
    'recommendation_status' => [
        'pending' => 'Chờ duyệt',
        'approved' => 'Đã duyệt',
        'rejected' => 'Từ chối',
        'executed' => 'Đã thực thi',
    ],

    // ── Map Node ──────────────────────────────────────────────
    'map_node_type' => [
        'shelter' => 'Điểm tị nạn',
        'hospital' => 'Bệnh viện',
        'school' => 'Trường học',
        'rescue_base' => 'Căn cứ cứu hộ',
        'intersection' => 'Ngã tư',
        'checkpoint' => 'Trạm kiểm soát',
    ],

    // ── Notification ──────────────────────────────────────────
    'notification_type' => [
        'alert' => 'Cảnh báo',
        'rescue_request' => 'Yêu cầu cứu hộ',
        'prediction' => 'Dự báo AI',
        'system' => 'Hệ thống',
    ],
];
