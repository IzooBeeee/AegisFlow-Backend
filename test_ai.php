<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$client = app(\App\Services\AI\AIServiceClient::class);

echo "Testing AI Service predictions...\n";
echo "====================================\n\n";

// 1. Test Flood Risk
echo "1. Flood Risk Prediction:\n";
$flood = $client->predictFloodRisk(
    waterLevel: 2.5,
    rainfall: 150.0,
    hours: 5,
    tide: 1.2
);
echo json_encode($flood, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// 2. Test Rescue Priority
echo "2. Rescue Priority Calculation:\n";
$priority = $client->calculateRescuePriority(
    urgency: 'high',
    vulnerableGroups: ['elderly', 'children'],
    peopleCount: 4,
    waterLevel: 1.5,
    createdAtIso: now()->subMinutes(45)->toIso8601String(),
    hasIncident: true
);
echo json_encode($priority, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// 3. Test Shelter Scoring
echo "3. Shelter Scoring:\n";
$shelter = $client->scoreShelter([
    'shelter_lat' => 16.0590,
    'shelter_lon' => 108.2060,
    'request_lat' => 16.0544,
    'request_lon' => 108.2022,
    'shelter_capacity' => 200,
    'shelter_occupancy' => 150,
    'people_count' => 4,
    'shelter_facilities' => ['first_aid', 'kitchen', 'clean_water'],
    'request_category' => 'medical'
]);
echo json_encode($shelter, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// 4. Test Route Optimization
echo "4. Route Optimization:\n";
$route = $client->optimizeRoute(
    startLat: 16.0544,
    startLon: 108.2022,
    endLat: 16.0590,
    endLon: 108.2060,
    floodedAreas: [
        ['lat' => 16.0570, 'lon' => 108.2040]
    ]
);
echo json_encode($route, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "Done.\n";
