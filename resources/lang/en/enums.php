<?php

return [

    'severity' => [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'critical' => 'Critical',
    ],

    'urgency' => [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'critical' => 'Critical',
    ],

    'incident_type' => [
        'flood' => 'Flood',
        'heavy_rain' => 'Heavy Rain',
        'landslide' => 'Landslide',
        'dam_failure' => 'Dam Failure',
        'other' => 'Other',
    ],
    'incident_status' => [
        'reported' => 'Reported',
        'verified' => 'Verified',
        'investigating' => 'Investigating',
        'confirmed' => 'Confirmed',
        'responding' => 'Responding',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
        'false_alarm' => 'False Alarm',
    ],
    'incident_source' => [
        'sensor' => 'Sensor',
        'citizen' => 'Citizen',
        'operator' => 'Operator',
        'ai' => 'AI System',
    ],

    'rescue_request_status' => [
        'pending' => 'Pending',
        'assigned' => 'Assigned',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],
    'rescue_category' => [
        'medical' => 'Medical',
        'food' => 'Food',
        'water' => 'Water',
        'rescue' => 'Rescue',
        'evacuation' => 'Evacuation',
        'shelter' => 'Shelter',
        'other' => 'Other',
    ],

    'rescue_team_status' => [
        'available' => 'Available',
        'on_mission' => 'On Mission',
        'resting' => 'Resting',
        'offline' => 'Offline',
    ],
    'rescue_team_type' => [
        'medical' => 'Medical',
        'fire' => 'Fire & Rescue',
        'military' => 'Military',
        'volunteer' => 'Volunteer',
        'special' => 'Special Forces',
    ],

    'alert_type' => [
        'flood' => 'Flood',
        'storm' => 'Storm',
        'landslide' => 'Landslide',
        'evacuation' => 'Evacuation',
        'system' => 'System',
        'weather' => 'Weather',
        'flood_risk' => 'Flood Risk',
        'power_outage' => 'Power Outage',
        'traffic' => 'Traffic',
    ],
    'alert_status' => [
        'draft' => 'Draft',
        'active' => 'Active',
        'updated' => 'Updated',
        'resolved' => 'Resolved',
        'expired' => 'Expired',
    ],

    'flood_zone_risk' => [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'critical' => 'Critical',
    ],
    'flood_zone_status' => [
        'monitoring' => 'Monitoring',
        'alert' => 'Alert',
        'danger' => 'Danger',
        'flooded' => 'Flooded',
        'recovering' => 'Recovering',
    ],

    'sensor_type' => [
        'water_level' => 'Water Level',
        'rainfall' => 'Rainfall',
        'camera' => 'AI Camera',
        'wind' => 'Wind',
        'temperature' => 'Temperature',
        'humidity' => 'Humidity',
        'combined' => 'Combined',
    ],
    'sensor_status' => [
        'online' => 'Online',
        'offline' => 'Offline',
        'maintenance' => 'Maintenance',
        'error' => 'Error',
    ],

    'shelter_status' => [
        'open' => 'Open',
        'full' => 'Full',
        'maintenance' => 'Maintenance',
        'closed' => 'Closed',
    ],

    'prediction_status' => [
        'pending' => 'Pending',
        'verified' => 'Verified',
        'alerted' => 'Alerted',
        'expired' => 'Expired',
        'superseded' => 'Superseded',
    ],

    'recommendation_type' => [
        'priority_route' => 'Priority Route',
        'alert' => 'Alert',
        'evacuation' => 'Evacuation',
        'reroute' => 'Reroute',
        'resource' => 'Resource',
    ],
    'recommendation_status' => [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'executed' => 'Executed',
    ],

    'map_node_type' => [
        'shelter' => 'Shelter',
        'hospital' => 'Hospital',
        'school' => 'School',
        'rescue_base' => 'Rescue Base',
        'intersection' => 'Intersection',
        'checkpoint' => 'Checkpoint',
    ],

    'notification_type' => [
        'alert' => 'Alert',
        'rescue_request' => 'Rescue Request',
        'prediction' => 'AI Prediction',
        'system' => 'System',
    ],
];
