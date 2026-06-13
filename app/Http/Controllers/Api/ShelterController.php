<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Shelter;
use Illuminate\Http\Request;

class ShelterController extends Controller
{
    /**
     * Danh sách điểm trú ẩn
     * GET /api/shelters
     */
    public function index(Request $request)
    {
        $query = Shelter::with('district')
            ->orderBy('status')
            ->orderBy('name');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('district_id')) {
            $query->where('district_id', $request->district_id);
        }

        if ($request->boolean('available_only')) {
            $query->open()->hasCapacity();
        }

        $shelters = $query->paginate($request->get('per_page', 20));

        $data = $shelters->map(fn ($s) => $this->formatShelter($s));

        return ApiResponse::paginate($shelters->setCollection($data));
    }

    /**
     * Chi tiết điểm trú ẩn
     * GET /api/shelters/{id}
     */
    public function show(int $id)
    {
        $shelter = Shelter::with(['district', 'district', 'supplyStocks.supply'])
            ->find($id);

        if (! $shelter) {
            return ApiResponse::notFound('Không tìm thấy điểm trú ẩn');
        }

        return ApiResponse::success($this->formatShelter($shelter, true));
    }

    /**
     * Tạo điểm trú ẩn
     * POST /api/shelters
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'capacity' => 'required|integer|min:1|max:100000',
            'current_occupancy' => 'nullable|integer|min:0',
            'status' => 'nullable|in:open,full,closed,preparing',
            'shelter_type' => 'nullable|string|max:50',
            'district_id' => 'nullable|exists:districts,id',
            'ward_id' => 'nullable|exists:wards,id',
            'facilities' => 'nullable|array',
            'facilities.*' => 'string|max:80',
            'contact_phone' => 'nullable|string|max:30',
            'contact_name' => 'nullable|string|max:255',
            'opening_hours' => 'nullable|string|max:255',
            'is_flood_safe' => 'nullable|boolean',
            'flood_depth_tolerance_m' => 'nullable|numeric|min:0|max:20',
        ]);

        $occupancy = min((int) ($data['current_occupancy'] ?? 0), (int) $data['capacity']);

        $shelter = Shelter::create([
            'name' => $data['name'],
            'code' => $this->nextShelterCode(),
            'address' => $data['address'],
            'district_id' => $data['district_id'] ?? null,
            'ward_id' => $data['ward_id'] ?? null,
            'shelter_type' => $data['shelter_type'] ?? 'community_center',
            'capacity' => $data['capacity'],
            'current_occupancy' => $occupancy,
            'facilities' => $data['facilities'] ?? [],
            'status' => $data['status'] ?? 'open',
            'is_flood_safe' => $data['is_flood_safe'] ?? true,
            'flood_depth_tolerance_m' => $data['flood_depth_tolerance_m'] ?? 2.5,
            'contact_phone' => $data['contact_phone'] ?? null,
            'contact_name' => $data['contact_name'] ?? null,
            'opening_hours' => $data['opening_hours'] ?? '24/7',
        ]);

        try {
            \DB::statement(
                        'UPDATE shelters SET geometry = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?',
                        [(float) $data['longitude'], (float) $data['latitude'], $shelter->id]
                    );
        } catch (\Exception $e) {}

        return ApiResponse::created($this->formatShelter($shelter->fresh()), 'Đã tạo điểm sơ tán');
    }

    /**
     * Cập nhật số người tại điểm trú ẩn
     * PUT /api/shelters/{id}/occupancy
     */
    public function updateOccupancy(Request $request, int $id)
    {
        $shelter = Shelter::find($id);

        if (! $shelter) {
            return ApiResponse::notFound('Không tìm thấy điểm trú ẩn');
        }

        $data = $request->validate([
            'action' => 'required|in:add,remove,set',
            'count' => 'required_if:action,add,remove|integer|min:1',
            'occupancy' => 'required_if:action,set|integer|min:0',
        ]);

        if ($data['action'] === 'add') {
            $shelter->addOccupants($data['count']);
        } elseif ($data['action'] === 'remove') {
            $shelter->removeOccupants($data['count']);
        } else {
            $shelter->current_occupancy = $data['occupancy'];
            if ($shelter->capacity > 0) {
                if ($shelter->current_occupancy >= $shelter->capacity * 0.9) {
                    $shelter->status = 'full';
                } elseif ($shelter->current_occupancy < $shelter->capacity * 0.9) {
                    $shelter->status = 'open';
                }
            }
            $shelter->save();
        }

        return ApiResponse::success($this->formatShelter($shelter->fresh(), true), 'Đã cập nhật số người');
    }

    protected function nextShelterCode(): string
    {
        $nextId = (int) Shelter::max('id') + 1;

        do {
            $code = 'SHELTER-'.str_pad((string) $nextId, 3, '0', STR_PAD_LEFT);
            $nextId++;
        } while (Shelter::where('code', $code)->exists());

        return $code;
    }

    /**
     * Format shelter response
     */
    protected function formatShelter(Shelter $shelter, bool $detailed = false): array
    {
        $data = [
            'id' => $shelter->id,
            'name' => $shelter->name,
            'code' => $shelter->code,
            'address' => $shelter->address,
            'location' => $shelter->location,
            'shelter_type' => $shelter->shelter_type,
            'capacity' => $shelter->capacity,
            'current_occupancy' => $shelter->current_occupancy,
            'available_beds' => $shelter->available_beds,
            'occupancy_percent' => $shelter->occupancy_percent,
            'status' => $shelter->status,
            'status_label' => $shelter->translated('status'),
            'facilities' => $shelter->facilities ?? [],
            'is_flood_safe' => $shelter->is_flood_safe,
            'district' => $shelter->district ? ['id' => $shelter->district->id, 'name' => $shelter->district->name] : null,
        ];

        if ($detailed) {
            $data['contact_phone'] = $shelter->contact_phone;
            $data['contact_name'] = $shelter->contact_name;
            $data['flood_depth_tolerance_m'] = $shelter->flood_depth_tolerance_m;
            $data['opening_hours'] = $shelter->opening_hours;
            $data['supply_stocks'] = $shelter->supplyStocks->map(fn ($stock) => [
                'supply' => [
                    'id' => $stock->supply->id,
                    'name' => $stock->supply->name,
                    'category' => $stock->supply->category,
                    'unit' => $stock->supply->unit,
                ],
                'quantity' => $stock->quantity,
                'available_quantity' => $stock->available_quantity,
            ]);
        }

        return $data;
    }
}
