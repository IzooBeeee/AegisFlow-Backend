<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\FloodZone;
use App\Models\Incident;
use App\Models\RescueTeam;
use App\Models\Sensor;
use App\Models\Shelter;
use App\Support\DaNangLandMask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class MapController extends Controller
{
    private function openMapApiKey(): string
    {
        return config('services.openmap.key')
            ?: env('OPENMAP_API_KEY', '6TTIZbUWJmRMSpiYzQ0YY8z5v8wv43w0');
    }

    /**
     * Forward geocoding qua OpenMapVN
     * GET /api/map/geocode/forward?text=...
     */
    public function geocodeForward(Request $request)
    {
        $text = trim((string) $request->query('text', ''));

        if ($text === '') {
            return response()->json([
                'success' => false,
                'message' => 'Missing text query.',
            ], 422);
        }

        $response = Http::timeout(8)->get('https://mapapis.openmap.vn/v1/geocode', [
            'text' => $text,
            'apikey' => $this->openMapApiKey(),
        ]);

        return response()->json($response->json(), $response->status());
    }

    /**
     * Reverse geocoding qua OpenMapVN
     * GET /api/map/geocode/reverse?lat=...&lng=...
     */
    public function geocodeReverse(Request $request)
    {
        $lat = $request->query('lat');
        $lng = $request->query('lng');

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return response()->json([
                'success' => false,
                'message' => 'Latitude and longitude must be numbers.',
            ], 422);
        }

        $response = Http::timeout(8)->get('https://mapapis.openmap.vn/v1/geocode/reverse', [
            'latlng' => sprintf('%s,%s', $lat, $lng),
            'apikey' => $this->openMapApiKey(),
        ]);

        return response()->json($response->json(), $response->status());
    }

    /**
     * Incidents dạng GeoJSON cho map
     * GET /api/map/incidents
     */
    public function incidents(Request $request)
    {
        $query = Incident::active()
            ->orderBy('severity', 'desc');

        if ($request->filled('district_id')) {
            $query->where('district_id', $request->district_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('danh_muc')) {
            $danhMucVal = $request->danh_muc;
            if (is_numeric($danhMucVal)) {
                $danhMuc = (int) $danhMucVal;
                $types = match ($danhMuc) {
                    1 => ['traffic', 'congestion'],
                    2 => ['environment'],
                    3 => ['fire'],
                    4 => ['trash', 'garbage'],
                    5 => ['flood', 'heavy_rain', 'landslide', 'dam_failure'],
                    default => ['other'],
                };
                $query->whereIn('type', $types);
            } else {
                $query->where('type', $danhMucVal);
            }
        }

        // Bounds filter for map viewport
        if ($request->filled(['min_lon', 'min_lat', 'max_lon', 'max_lat'])) {
            if (DB::connection()->getDriverName() === 'pgsql') {
                $query->whereRaw('geometry && ST_MakeEnvelope(?, ?, ?, ?, 4326)', [
                    (float) $request->min_lon,
                    (float) $request->min_lat,
                    (float) $request->max_lon,
                    (float) $request->max_lat,
                ]);
            }
        }

        $incidents = $query->with(['ward', 'district'])->limit(200)->get();

        $features = $incidents
            ->map(fn ($i) => $i->toGeoJson())
            ->filter(fn (array $feature) => DaNangLandMask::featureIsLikelyLand($feature))
            ->values();

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    /**
     * Vùng ngập dạng GeoJSON
     * GET /api/map/flood-zones
     */
    public function floodZones(Request $request)
    {
        $query = FloodZone::active();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $zones = $query->get();

        $features = $zones->map(fn ($z) => $z->toGeoJson());

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    /**
     * Đội cứu hộ dạng GeoJSON
     * GET /api/map/rescue-teams
     */
    public function rescueTeams()
    {
        $teams = RescueTeam::whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->get();

        $features = $teams->map(function ($team) {
            return [
                'type' => 'Feature',
                'id' => $team->id,
                'properties' => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'status' => $team->status,
                    'team_type' => $team->team_type,
                ],
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$team->current_longitude, $team->current_latitude],
                ],
            ];
        });

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    /**
     * Điểm trú ẩn dạng GeoJSON
     * GET /api/map/shelters
     */
    public function shelters()
    {
        $shelters = Shelter::open()->get();

        $features = [];
        foreach ($shelters as $shelter) {
            $location = $shelter->location;
            if ($location) {
                $features[] = [
                    'type' => 'Feature',
                    'id' => $shelter->id,
                    'properties' => [
                        'id' => $shelter->id,
                        'name' => $shelter->name,
                        'capacity' => $shelter->capacity,
                        'available_beds' => $shelter->available_beds,
                        'facilities' => $shelter->facilities ?? [],
                        'status' => $shelter->status,
                    ],
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [$location['lng'], $location['lat']],
                    ],
                ];
            }
        }

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    /**
     * Báo cáo ngập thực tế (từ flood_reports) dạng GeoJSON
     * GET /api/map/flood-reports?event_id=1
     */
    public function floodReports(Request $request)
    {
        $query = DB::table('flood_reports')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('latitude', '!=', 0)
            ->where('longitude', '!=', 0);

        if ($request->filled('event_id')) {
            $query->where('flood_event_id', $request->event_id);
        }

        if ($request->filled(['min_lon', 'min_lat', 'max_lon', 'max_lat'])) {
            $query
                ->whereBetween('longitude', [(float) $request->min_lon, (float) $request->max_lon])
                ->whereBetween('latitude', [(float) $request->min_lat, (float) $request->max_lat]);
        }

        $reports = $query->select([
            'id', 'external_id', 'address', 'street_name', 'ward_name', 'district_name',
            'latitude', 'longitude', 'water_level_cm', 'flood_type',
            'flood_location_type', 'is_frequent', 'description',
            'image_urls', 'polyline', 'flood_started_at', 'flood_ended_at', 'reported_at',
        ])->limit(500)->get();

        $features = $reports->map(function ($r) {
            $wl = (float) ($r->water_level_cm ?? 0);
            $color = match (true) {
                $wl >= 75 => '#EF4444',
                $wl >= 50 => '#F97316',
                $wl >= 25 => '#3B82F6',
                default => '#22C55E',
            };

            $images = [];
            if ($r->image_urls) {
                $decoded = json_decode($r->image_urls, true);
                $images = is_array($decoded) ? $decoded : [];
            }

            // Build geometry: LineString for street floods, Point for others
            $isStreet = $r->flood_type === 'street' && ! empty($r->polyline);
            if ($isStreet) {
                $coords = self::decodePolyline($r->polyline);
                $geometry = count($coords) >= 2
                    ? ['type' => 'LineString', 'coordinates' => $coords]
                    : ['type' => 'Point', 'coordinates' => [(float) $r->longitude, (float) $r->latitude]];
            } else {
                $geometry = ['type' => 'Point', 'coordinates' => [(float) $r->longitude, (float) $r->latitude]];
            }

            return [
                'type' => 'Feature',
                'id' => $r->id,
                'properties' => [
                    'id' => $r->id,
                    'address' => $r->address,
                    'street_name' => $r->street_name,
                    'ward_name' => $r->ward_name,
                    'district_name' => $r->district_name,
                    'water_level_cm' => $r->water_level_cm,
                    'flood_type' => $r->flood_type,
                    'flood_location_type' => $r->flood_location_type,
                    'is_frequent' => (bool) $r->is_frequent,
                    'description' => $r->description,
                    'image_urls' => $images,
                    'polyline' => $r->polyline,
                    'flood_started_at' => $r->flood_started_at,
                    'reported_at' => $r->reported_at,
                    'color' => $color,
                ],
                'geometry' => $geometry,
            ];
        })->filter(fn (array $feature) => DaNangLandMask::featureIsLikelyLand($feature))->values();

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    /**
     * Decode Google encoded polyline → [[lng, lat], ...]
     */
    private static function decodePolyline(string $encoded): array
    {
        $coords = [];
        $index = 0;
        $len = strlen($encoded);
        $lat = 0;
        $lng = 0;

        while ($index < $len) {
            $shift = 0;
            $result = 0;
            do {
                $b = ord($encoded[$index++]) - 63;
                $result |= ($b & 0x1F) << $shift;
                $shift += 5;
            } while ($b >= 0x20);
            $dlat = ($result & 1) ? ~($result >> 1) : ($result >> 1);
            $lat += $dlat;

            $shift = 0;
            $result = 0;
            do {
                $b = ord($encoded[$index++]) - 63;
                $result |= ($b & 0x1F) << $shift;
                $shift += 5;
            } while ($b >= 0x20);
            $dlng = ($result & 1) ? ~($result >> 1) : ($result >> 1);
            $lng += $dlng;

            $coords[] = [round($lng / 1e5, 7), round($lat / 1e5, 7)]; // [lng, lat] for GeoJSON
        }

        return $coords;
    }

    /**
     * GET /api/map/sensor-stations?type=flood_1m5
     */
    public function sensorStations(Request $request)
    {
        $query = DB::table('sensor_stations')
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($request->filled('type')) {
            $query->where('station_type', $request->type);
        }

        $stations = $query->select([
            'id', 'external_id', 'name', 'station_type', 'station_type_label',
            'area', 'address', 'ward_name', 'district_name',
            'latitude', 'longitude', 'current_depth_m', 'phone',
        ])->get();

        $features = $stations->map(function ($s) {
            return [
                'type' => 'Feature',
                'id' => $s->id,
                'properties' => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'station_type' => $s->station_type,
                    'station_type_label' => $s->station_type_label,
                    'area' => $s->area,
                    'address' => $s->address,
                    'ward_name' => $s->ward_name,
                    'district_name' => $s->district_name,
                    'current_depth_m' => $s->current_depth_m,
                    'phone' => $s->phone,
                ],
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float) $s->longitude, (float) $s->latitude],
                ],
            ];
        })->filter(fn (array $feature) => DaNangLandMask::featureIsLikelyLand($feature))->values();

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    /**
     * Danh sách đợt lũ lịch sử
     * GET /api/map/flood-events
     */
    public function floodEvents()
    {
        $events = DB::table('flood_events')
            ->orderByDesc('started_at')
            ->select(['id', 'external_id', 'name', 'started_at', 'ended_at',
                'severity', 'status', 'total_reports', 'affected_districts',
                'max_water_level_cm', 'avg_water_level_cm'])
            ->get();

        return ApiResponse::success($events);
    }

    /**
     * Tất cả dữ liệu map cùng lúc
     * GET /api/map/all
     */
    public function all(Request $request)
    {
        return ApiResponse::success([
            'incidents' => $this->incidents($request)->getData(),
            'flood_zones' => $this->floodZones($request)->getData(),
            'rescue_teams' => $this->rescueTeams()->getData(),
            'shelters' => $this->shelters()->getData(),
            'sensors' => $this->sensors()->getData(),
            'flood_reports' => $this->floodReports($request)->getData(),
            'sensor_stations' => $this->sensorStations($request)->getData(),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Cảm biến dạng GeoJSON
     * GET /api/map/sensors
     */
    public function sensors()
    {
        $sensors = Sensor::active()->get();

        $features = $sensors->map(function ($s) {
            return [
                'type' => 'Feature',
                'id' => $s->id,
                'properties' => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'type' => $s->type,
                    'status' => $s->status,
                    'last_value' => $s->last_value,
                ],
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$s->longitude, $s->latitude],
                ],
            ];
        })->filter(fn (array $feature) => DaNangLandMask::featureIsLikelyLand($feature))->values();

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }
}
