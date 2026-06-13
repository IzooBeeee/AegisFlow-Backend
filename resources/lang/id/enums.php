<?php

return [

    'severity' => [
        'low' => 'Rendah',
        'medium' => 'Sedang',
        'high' => 'Tinggi',
        'critical' => 'Kritis',
    ],

    'urgency' => [
        'low' => 'Rendah',
        'medium' => 'Sedang',
        'high' => 'Tinggi',
        'critical' => 'Kritis',
    ],

    'incident_type' => [
        'flood' => 'Banjir',
        'heavy_rain' => 'Hujan Lebat',
        'landslide' => 'Tanah Longsor',
        'dam_failure' => 'Kegagalan Bendungan',
        'other' => 'Lainnya',
    ],
    'incident_status' => [
        'reported' => 'Dilaporkan',
        'verified' => 'Diverifikasi',
        'investigating' => 'Menyelidiki',
        'confirmed' => 'Dikonfirmasi',
        'responding' => 'Menanggapi',
        'resolved' => 'Selesai',
        'closed' => 'Ditutup',
        'false_alarm' => 'Alarm Palsu',
    ],
    'incident_source' => [
        'sensor' => 'Sensor',
        'citizen' => 'Warga',
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
        'medical' => 'Medis',
        'food' => 'Makanan',
        'water' => 'Air Bersih',
        'rescue' => 'Penyelamatan',
        'evacuation' => 'Evakuasi',
        'shelter' => 'Tempat Berlindung',
        'other' => 'Lainnya',
    ],

    'rescue_team_status' => [
        'available' => 'Tersedia',
        'on_mission' => 'Dalam Misi',
        'resting' => 'Istirahat',
        'offline' => 'Luring',
    ],
    'rescue_team_type' => [
        'medical' => 'Medis',
        'fire' => 'Pemadam Kebakaran & Penyelamatan',
        'military' => 'Militer',
        'volunteer' => 'Relawan',
        'special' => 'Pasukan Khusus',
    ],

    'alert_type' => [
        'flood' => 'Banjir',
        'storm' => 'Badai',
        'landslide' => 'Tanah Longsor',
        'evacuation' => 'Evakuasi',
        'system' => 'Sistem',
        'weather' => 'Cuaca',
        'flood_risk' => 'Risiko Banjir',
        'power_outage' => 'Mati Listrik',
        'traffic' => 'Lalu Lintas',
    ],
    'alert_status' => [
        'draft' => 'Draf',
        'active' => 'Aktif',
        'updated' => 'Diperbarui',
        'resolved' => 'Selesai',
        'expired' => 'Kedaluwarsa',
    ],

    'flood_zone_risk' => [
        'low' => 'Rendah',
        'medium' => 'Sedang',
        'high' => 'Tinggi',
        'critical' => 'Kritis',
    ],
    'flood_zone_status' => [
        'monitoring' => 'Pemantauan',
        'alert' => 'Peringatan',
        'danger' => 'Bahaya',
        'flooded' => 'Kebanjiran',
        'recovering' => 'Pemulihan',
    ],

    'sensor_type' => [
        'water_level' => 'Tinggi Air',
        'rainfall' => 'Curah Hujan',
        'camera' => 'Kamera AI',
        'wind' => 'Angin',
        'temperature' => 'Suhu',
        'humidity' => 'Kelembaban',
        'combined' => 'Kombinasi',
    ],
    'sensor_status' => [
        'online' => 'Daring',
        'offline' => 'Luring',
        'maintenance' => 'Pemeliharaan',
        'error' => 'Error',
    ],

    'shelter_status' => [
        'open' => 'Buka',
        'full' => 'Penuh',
        'maintenance' => 'Pemeliharaan',
        'closed' => 'Tutup',
    ],

    'prediction_status' => [
        'pending' => 'Menunggu',
        'verified' => 'Diverifikasi',
        'alerted' => 'Diberi Peringatan',
        'expired' => 'Kedaluwarsa',
        'superseded' => 'Digantikan',
    ],

    'recommendation_type' => [
        'priority_route' => 'Rute Prioritas',
        'alert' => 'Peringatan',
        'evacuation' => 'Evakuasi',
        'reroute' => 'Pengalihan Rute',
        'resource' => 'Sumber Daya',
    ],
    'recommendation_status' => [
        'pending' => 'Menunggu',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'executed' => 'Dijalankan',
    ],

    'map_node_type' => [
        'shelter' => 'Tempat Evakuasi',
        'hospital' => 'Rumah Sakit',
        'school' => 'Sekolah',
        'rescue_base' => 'Pos Penyelamatan',
        'intersection' => 'Persimpangan',
        'checkpoint' => 'Poin Pemeriksaan',
    ],

    'notification_type' => [
        'alert' => 'Peringatan',
        'rescue_request' => 'Permintaan Cenyelamatan',
        'prediction' => 'Prediksi AI',
        'system' => 'Sistem',
    ],
];
