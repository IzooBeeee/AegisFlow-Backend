<?php

return [

    'severity' => [
        'low' => 'Mababa',
        'medium' => 'Katamtaman',
        'high' => 'Mataas',
        'critical' => 'Kritikal',
    ],

    'urgency' => [
        'low' => 'Mababa',
        'medium' => 'Katamtaman',
        'high' => 'Mataas',
        'critical' => 'Kritikal',
    ],

    'incident_type' => [
        'flood' => 'Baha',
        'heavy_rain' => 'Malakas na Ulan',
        'landslide' => 'Landslide',
        'dam_failure' => 'Pagkasira ng Dam',
        'other' => 'Iba pa',
    ],
    'incident_status' => [
        'reported' => 'Naiulat',
        'verified' => 'Na-verify',
        'investigating' => 'Sinisiyasat',
        'confirmed' => 'Kumpirmado',
        'responding' => 'Tugon',
        'resolved' => 'Nalutas na',
        'closed' => 'Isinara na',
        'false_alarm' => 'Maling Alarma',
    ],
    'incident_source' => [
        'sensor' => 'Sensor',
        'citizen' => 'Mamamayan',
        'operator' => 'Operator',
        'ai' => 'Sistemang AI',
    ],

    'rescue_request_status' => [
        'pending' => 'Nakabinbin',
        'assigned' => 'Itinalaga',
        'in_progress' => 'Kasalukuyang Ginagawa',
        'completed' => 'Kumpleto na',
        'cancelled' => 'Kanselado',
    ],
    'rescue_category' => [
        'medical' => 'Medikal',
        'food' => 'Pagkain',
        'water' => 'Malinis na Tubig',
        'rescue' => 'Pagsagip',
        'evacuation' => 'Evacuation',
        'shelter' => 'Tuluyan',
        'other' => 'Iba pa',
    ],

    'rescue_team_status' => [
        'available' => 'Handang Tumugon',
        'on_mission' => 'Nasa Misyon',
        'resting' => 'Nagpapahinga',
        'offline' => 'Offline',
    ],
    'rescue_team_type' => [
        'medical' => 'Medikal',
        'fire' => 'Bumbero at Pagsagip',
        'military' => 'Militar',
        'volunteer' => 'Boluntaryo',
        'special' => 'Espesyal na Puwersa',
    ],

    'alert_type' => [
        'flood' => 'Baha',
        'storm' => 'Bagyo',
        'landslide' => 'Landslide',
        'evacuation' => 'Evacuation',
        'system' => 'Sistema',
        'weather' => 'Panahon',
        'flood_risk' => 'Panganib ng Baha',
        'power_outage' => 'Brownout',
        'traffic' => 'Trapiko',
    ],
    'alert_status' => [
        'draft' => 'Draft',
        'active' => 'Aktibo',
        'updated' => 'Nai-update',
        'resolved' => 'Nalutas na',
        'expired' => 'Nag-expire na',
    ],

    'flood_zone_risk' => [
        'low' => 'Mababa',
        'medium' => 'Katamtaman',
        'high' => 'Mataas',
        'critical' => 'Kritikal',
    ],
    'flood_zone_status' => [
        'monitoring' => 'Binabantayan',
        'alert' => 'Babala',
        'danger' => 'Panganib',
        'flooded' => 'Baha na',
        'recovering' => 'Bumababa na',
    ],

    'sensor_type' => [
        'water_level' => 'Taas ng Tubig',
        'rainfall' => 'Dami ng Ulan',
        'camera' => 'Kamera AI',
        'wind' => 'Hangin',
        'temperature' => 'Temperatura',
        'humidity' => 'Halumigmig',
        'combined' => 'Pinagsama',
    ],
    'sensor_status' => [
        'online' => 'Online',
        'offline' => 'Offline',
        'maintenance' => 'Kasalukuyang Kinukumpuni',
        'error' => 'Error',
    ],

    'shelter_status' => [
        'open' => 'Bukás',
        'full' => 'Punô',
        'maintenance' => 'Kasalukuyang Kinukumpuni',
        'closed' => 'Sarado',
    ],

    'prediction_status' => [
        'pending' => 'Nakabinbin',
        'verified' => 'Na-verify',
        'alerted' => 'Nabigyan ng Babala',
        'expired' => 'Nag-expire na',
        'superseded' => 'Napalitan na',
    ],

    'recommendation_type' => [
        'priority_route' => 'Pangunahing Ruta',
        'alert' => 'Babala',
        'evacuation' => 'Evacuation',
        'reroute' => 'Pagbabago ng Ruta',
        'resource' => 'Kagamitan',
    ],
    'recommendation_status' => [
        'pending' => 'Nakabinbin',
        'approved' => 'Inaprubahan',
        'rejected' => 'Tinanggihan',
        'executed' => 'Isinagawa na',
    ],

    'map_node_type' => [
        'shelter' => 'Tuluyan',
        'hospital' => 'Ospital',
        'school' => 'Paaralan',
        'rescue_base' => 'Himpilan ng Pagsagip',
        'intersection' => 'Krusan',
        'checkpoint' => 'Checkpoint',
    ],

    'notification_type' => [
        'alert' => 'Babala',
        'rescue_request' => 'Kahilingan sa Pagsagip',
        'prediction' => 'Hula ng AI',
        'system' => 'Sistema',
    ],
];
