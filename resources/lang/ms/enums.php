<?php

return [

    'severity' => [
        'low' => 'Rendah',
        'medium' => 'Sederhana',
        'high' => 'Tinggi',
        'critical' => 'Kritikal',
    ],

    'urgency' => [
        'low' => 'Rendah',
        'medium' => 'Sederhana',
        'high' => 'Tinggi',
        'critical' => 'Kritikal',
    ],

    'incident_type' => [
        'flood' => 'Banjir',
        'heavy_rain' => 'Hujan Lebat',
        'landslide' => 'Tanah Runtuh',
        'dam_failure' => 'Kegagalan Empangan',
        'other' => 'Lain-lain',
    ],
    'incident_status' => [
        'reported' => 'Dilaporkan',
        'verified' => 'Disahkan',
        'investigating' => 'Menyiasat',
        'confirmed' => 'Dikonfirmasi',
        'responding' => 'Menghadapi',
        'resolved' => 'Selesai',
        'closed' => 'Ditutup',
        'false_alarm' => 'Penggera Palsu',
    ],
    'incident_source' => [
        'sensor' => 'Sensor',
        'citizen' => 'Rakyat',
        'operator' => 'Operator',
        'ai' => 'Sistem AI',
    ],

    'rescue_request_status' => [
        'pending' => 'Menunggu',
        'assigned' => 'Ditugaskan',
        'in_progress' => 'Sedang Berjalan',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
    ],
    'rescue_category' => [
        'medical' => 'Perubatan',
        'food' => 'Makanan',
        'water' => 'Air Bersih',
        'rescue' => 'Penyelamatan',
        'evacuation' => 'Evakuasi',
        'shelter' => 'Tempat Perlindungan',
        'other' => 'Lain-lain',
    ],

    'rescue_team_status' => [
        'available' => 'Tersedia',
        'on_mission' => 'Dalam Misi',
        'resting' => 'Berehat',
        'offline' => 'Luar Talian',
    ],
    'rescue_team_type' => [
        'medical' => 'Perubatan',
        'fire' => 'Bomba & Penyelamat',
        'military' => 'Tentera',
        'volunteer' => 'Sukarelawan',
        'special' => 'Pasukan Khas',
    ],

    'alert_type' => [
        'flood' => 'Banjir',
        'storm' => 'Ribut',
        'landslide' => 'Tanah Runtuh',
        'evacuation' => 'Evakuasi',
        'system' => 'Sistem',
        'weather' => 'Cuaca',
        'flood_risk' => 'Risiko Banjir',
        'power_outage' => 'Gangguan Bekalan Elektrik',
        'traffic' => 'Lalu Lintas',
    ],
    'alert_status' => [
        'draft' => 'Draf',
        'active' => 'Aktif',
        'updated' => 'Dikemas Kini',
        'resolved' => 'Selesai',
        'expired' => 'Tamat Tempoh',
    ],

    'flood_zone_risk' => [
        'low' => 'Rendah',
        'medium' => 'Sederhana',
        'high' => 'Tinggi',
        'critical' => 'Kritikal',
    ],
    'flood_zone_status' => [
        'monitoring' => 'Pemantauan',
        'alert' => 'Amaran',
        'danger' => 'Bahaya',
        'flooded' => 'Dilanda Banjir',
        'recovering' => 'Pemulihan',
    ],

    'sensor_type' => [
        'water_level' => 'Paras Air',
        'rainfall' => 'Jumlah Hujan',
        'camera' => 'Kamera AI',
        'wind' => 'Angin',
        'temperature' => 'Suhu',
        'humidity' => 'Kelembapan',
        'combined' => 'Gabungan',
    ],
    'sensor_status' => [
        'online' => 'Dalam Talian',
        'offline' => 'Luar Talian',
        'maintenance' => 'Penyelenggaraan',
        'error' => 'Ralat',
    ],

    'shelter_status' => [
        'open' => 'Buka',
        'full' => 'Penuh',
        'maintenance' => 'Penyelenggaraan',
        'closed' => 'Tutup',
    ],

    'prediction_status' => [
        'pending' => 'Menunggu',
        'verified' => 'Disahkan',
        'alerted' => 'Diberi Amaran',
        'expired' => 'Tamat Tempoh',
        'superseded' => 'Digantikan',
    ],

    'recommendation_type' => [
        'priority_route' => 'Laluan Keutamaan',
        'alert' => 'Amaran',
        'evacuation' => 'Evakuasi',
        'reroute' => 'Pengalihan Laluan',
        'resource' => 'Sumber Daya',
    ],
    'recommendation_status' => [
        'pending' => 'Menunggu',
        'approved' => 'Diluluskan',
        'rejected' => 'Ditolak',
        'executed' => 'Dijalankan',
    ],

    'map_node_type' => [
        'shelter' => 'Tempat Evakuasi',
        'hospital' => 'Hospital',
        'school' => 'Sekolah',
        'rescue_base' => 'Pangkalan Penyelamat',
        'intersection' => 'Persimpangan',
        'checkpoint' => 'Pusat Pemeriksaan',
    ],

    'notification_type' => [
        'alert' => 'Amaran',
        'rescue_request' => 'Permintaan Penyelamatan',
        'prediction' => 'Ramalan AI',
        'system' => 'Sistem',
    ],
];
