<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\WeatherData;
use Illuminate\Http\Request;

class WeatherDataController extends Controller
{
    /**
     * Dữ liệu thời tiết hiện tại theo quận/huyện
     * GET /api/weather/current
     */
    public function current(Request $request)
    {
        $request->validate([
            'district_id' => 'nullable|exists:districts,id',
        ]);

        $query = WeatherData::with('district')
            ->orderByDesc('recorded_at');

        if ($request->filled('district_id')) {
            $query->where('district_id', $request->district_id);
        }

        // Lấy bản ghi mới nhất cho mỗi quận
        $latest = $query->get()
            ->groupBy('district_id')
            ->map(fn ($group) => $group->first());

        return ApiResponse::success($latest->values());
    }

    /**
     * Lịch sử thời tiết theo quận
     * GET /api/weather/history
     */
    public function history(Request $request)
    {
        $request->validate([
            'district_id' => 'required|exists:districts,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'hours' => 'nullable|integer|min:1|max:168', // tối đa 7 ngày
        ]);

        $query = WeatherData::where('district_id', $request->district_id)
            ->orderByDesc('recorded_at');

        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('recorded_at', [$request->from, $request->to]);
        } elseif ($request->filled('hours')) {
            $query->where('recorded_at', '>=', now()->subHours($request->integer('hours')));
        } else {
            // Mặc định: 24 giờ gần nhất
            $query->where('recorded_at', '>=', now()->subHours(24));
        }

        $data = $query->paginate($request->integer('per_page', 50));

        return ApiResponse::success($data);
    }

    /**
     * Nhập dữ liệu thời tiết (từ API bên ngoài hoặc thủ công)
     * POST /api/weather
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'district_id' => 'required|exists:districts,id',
            'recorded_at' => 'required|date',
            'temperature_c' => 'nullable|numeric|between:-10,50',
            'humidity_pct' => 'nullable|numeric|between:0,100',
            'wind_speed_kmh' => 'nullable|numeric|min:0',
            'wind_direction' => 'nullable|string|max:10',
            'rainfall_mm' => 'nullable|numeric|min:0',
            'pressure_hpa' => 'nullable|numeric',
            'visibility_km' => 'nullable|numeric|min:0',
            'cloud_cover_pct' => 'nullable|numeric|between:0,100',
            'uv_index' => 'nullable|numeric|min:0',
            'source' => 'nullable|string|max:50',
            'forecast_date' => 'nullable|date',
        ]);

        $weather = WeatherData::create($data);

        return ApiResponse::created($weather, 'Dữ liệu thời tiết đã được lưu');
    }

    /**
     * Nhập dữ liệu thời tiết hàng loạt
     * POST /api/weather/batch
     */
    public function batchStore(Request $request)
    {
        $request->validate([
            'data' => 'required|array|min:1|max:100',
            'data.*.district_id' => 'required|exists:districts,id',
            'data.*.recorded_at' => 'required|date',
            'data.*.temperature_c' => 'nullable|numeric',
            'data.*.humidity_pct' => 'nullable|numeric',
            'data.*.rainfall_mm' => 'nullable|numeric|min:0',
            'data.*.wind_speed_kmh' => 'nullable|numeric|min:0',
            'data.*.source' => 'nullable|string|max:50',
        ]);

        $records = collect($request->data)->map(function ($item) {
            return WeatherData::create($item);
        });

        return ApiResponse::created([
            'count' => $records->count(),
        ], "Đã lưu {$records->count()} bản ghi thời tiết");
    }

    /**
     * Thống kê thời tiết theo quận (tóm tắt)
     * GET /api/weather/summary
     */
    public function summary(Request $request)
    {
        $request->validate([
            'district_id' => 'nullable|exists:districts,id',
            'hours' => 'nullable|integer|min:1|max:168',
        ]);

        $hours = $request->integer('hours', 24);
        $query = WeatherData::where('recorded_at', '>=', now()->subHours($hours));

        if ($request->filled('district_id')) {
            $query->where('district_id', $request->district_id);
        }

        $summary = $query->selectRaw("
            district_id,
            COUNT(*) as reading_count,
            ROUND(AVG(temperature_c)::numeric, 1) as avg_temperature_c,
            ROUND(AVG(humidity_pct)::numeric, 1) as avg_humidity_pct,
            ROUND(SUM(rainfall_mm)::numeric, 1) as total_rainfall_mm,
            ROUND(MAX(rainfall_mm)::numeric, 1) as max_rainfall_mm,
            ROUND(AVG(wind_speed_kmh)::numeric, 1) as avg_wind_speed_kmh,
            ROUND(MAX(wind_speed_kmh)::numeric, 1) as max_wind_speed_kmh,
            MIN(recorded_at) as from_time,
            MAX(recorded_at) as to_time
        ")
            ->groupBy('district_id')
            ->with('district:id,name')
            ->get();

        return ApiResponse::success([
            'period_hours' => $hours,
            'districts' => $summary,
        ]);
    }
}
