<?php

return [

    'severity' => [
        'low' => 'ต่ำ',
        'medium' => 'ปานกลาง',
        'high' => 'สูง',
        'critical' => 'วิกฤต',
    ],

    'urgency' => [
        'low' => 'ต่ำ',
        'medium' => 'ปานกลาง',
        'high' => 'สูง',
        'critical' => 'วิกฤต',
    ],

    'incident_type' => [
        'flood' => 'น้ำท่วม',
        'heavy_rain' => 'ฝนตกหนัก',
        'landslide' => 'ดินถล่ม',
        'dam_failure' => 'เขื่อนชำรุด',
        'other' => 'อื่น ๆ',
    ],
    'incident_status' => [
        'reported' => 'ได้รับรายงาน',
        'verified' => 'ตรวจสอบแล้ว',
        'investigating' => 'กำลังตรวจสอบ',
        'confirmed' => 'ยืนยันแล้ว',
        'responding' => 'กำลังตอบสนอง',
        'resolved' => 'แก้ไขแล้ว',
        'closed' => 'ปิดตัวลง',
        'false_alarm' => 'สัญญาณหลอก',
    ],
    'incident_source' => [
        'sensor' => 'เซ็นเซอร์',
        'citizen' => 'ประชาชน',
        'operator' => 'เจ้าหน้าที่ควบคุม',
        'ai' => 'ระบบ AI',
    ],

    'rescue_request_status' => [
        'pending' => 'รอดำเนินการ',
        'assigned' => 'มอบหมายแล้ว',
        'in_progress' => 'กำลังดำเนินการ',
        'completed' => 'เสร็จสิ้น',
        'cancelled' => 'ยกเลิก',
    ],
    'rescue_category' => [
        'medical' => 'การแพทย์',
        'food' => 'อาหาร',
        'water' => 'น้ำดื่ม',
        'rescue' => 'กู้ภัย',
        'evacuation' => 'อพยพ',
        'shelter' => 'ที่พักพิง',
        'other' => 'อื่น ๆ',
    ],

    'rescue_team_status' => [
        'available' => 'พร้อมปฏิบัติงาน',
        'on_mission' => 'ปฏิบัติภารกิจ',
        'resting' => 'พักผ่อน',
        'offline' => 'ออฟไลน์',
    ],
    'rescue_team_type' => [
        'medical' => 'หน่วยแพทย์',
        'fire' => 'ดับเพลิงและกู้ภัย',
        'military' => 'ทหาร',
        'volunteer' => 'อาสาสมัคร',
        'special' => 'หน่วยรบพิเศษ',
    ],

    'alert_type' => [
        'flood' => 'น้ำท่วม',
        'storm' => 'พายุ',
        'landslide' => 'ดินถล่ม',
        'evacuation' => 'อพยพ',
        'system' => 'ระบบ',
        'weather' => 'สภาพอากาศ',
        'flood_risk' => 'ความเสี่ยงน้ำท่วม',
        'power_outage' => 'ไฟดับ',
        'traffic' => 'การจราจร',
    ],
    'alert_status' => [
        'draft' => 'ร่าง',
        'active' => 'ใช้งานอยู่',
        'updated' => 'อัปเดตแล้ว',
        'resolved' => 'แก้ไขแล้ว',
        'expired' => 'หมดอายุ',
    ],

    'flood_zone_risk' => [
        'low' => 'ต่ำ',
        'medium' => 'ปานกลาง',
        'high' => 'สูง',
        'critical' => 'วิกฤต',
    ],
    'flood_zone_status' => [
        'monitoring' => 'กำลังเฝ้าระวัง',
        'alert' => 'แจ้งเตือน',
        'danger' => 'อันตราย',
        'flooded' => 'น้ำท่วมขัง',
        'recovering' => 'กำลังฟื้นฟู',
    ],

    'sensor_type' => [
        'water_level' => 'ระดับน้ำ',
        'rainfall' => 'ปริมาณน้ำฝน',
        'camera' => 'กล้อง AI',
        'wind' => 'ความเร็วลม',
        'temperature' => 'อุณหภูมิ',
        'humidity' => 'ความชื้น',
        'combined' => 'แบบผสม',
    ],
    'sensor_status' => [
        'online' => 'ออนไลน์',
        'offline' => 'ออฟไลน์',
        'maintenance' => 'ซ่อมบำรุง',
        'error' => 'ข้อผิดพลาด',
    ],

    'shelter_status' => [
        'open' => 'เปิด',
        'full' => 'เต็ม',
        'maintenance' => 'ซ่อมบำรุง',
        'closed' => 'ปิด',
    ],

    'prediction_status' => [
        'pending' => 'รอดำเนินการ',
        'verified' => 'ยืนยันแล้ว',
        'alerted' => 'แจ้งเตือนแล้ว',
        'expired' => 'หมดอายุ',
        'superseded' => 'ถูกแทนที่',
    ],

    'recommendation_type' => [
        'priority_route' => 'เส้นทางหลัก',
        'alert' => 'แจ้งเตือน',
        'evacuation' => 'อพยพ',
        'reroute' => 'เปลี่ยนเส้นทาง',
        'resource' => 'ทรัพยากร',
    ],
    'recommendation_status' => [
        'pending' => 'รอดำเนินการ',
        'approved' => 'อนุมัติ',
        'rejected' => 'ปฏิเสธ',
        'executed' => 'ดำเนินการแล้ว',
    ],

    'map_node_type' => [
        'shelter' => 'ที่อพยพ',
        'hospital' => 'โรงพยาบาล',
        'school' => 'โรงเรียน',
        'rescue_base' => 'ฐานกู้ภัย',
        'intersection' => 'ทางแยก',
        'checkpoint' => 'จุดตรวจ',
    ],

    'notification_type' => [
        'alert' => 'แจ้งเตือน',
        'rescue_request' => 'คำร้องขอกู้ภัย',
        'prediction' => 'การทำนายโดย AI',
        'system' => 'ระบบ',
    ],
];
