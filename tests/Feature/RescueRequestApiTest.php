<?php

namespace Tests\Feature;

use App\Enums\FloodZoneStatusEnum;
use App\Enums\UrgencyEnum;
use App\Models\FloodZone;
use App\Models\RescueRequest;
use App\Models\RescueTeam;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RescueRequestApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $citizen;
    protected User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->citizen = User::factory()->create();
        $this->citizen->assignRole(\Spatie\Permission\Models\Role::create([
            'name' => 'citizen', 'display_name' => 'Công dân', 'slug' => 'citizen',
        ]));

        $this->operator = User::factory()->create();
        $this->operator->assignRole(\Spatie\Permission\Models\Role::create([
            'name' => 'rescue_operator', 'display_name' => 'Điều phối viên', 'slug' => 'rescue_operator',
        ]));
    }

    /**
     * Test citizen tạo yêu cầu cứu hộ
     */
    public function test_citizen_can_create_rescue_request(): void
    {
        Sanctum::actingAs($this->citizen);

        $response = $this->postJson('/api/rescue-requests', [
            'caller_name' => 'Nguyễn Văn A',
            'caller_phone' => '0901234567',
            'latitude' => 16.0544,
            'longitude' => 108.2022,
            'address' => '123 Đường Test, Đà Nẵng',
            'urgency' => 'high',
            'category' => 'rescue',
            'people_count' => 5,
            'vulnerable_groups' => ['children'],
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => ['id', 'request_number', 'urgency', 'category', 'status', 'priority_score'],
            ]);

        $this->assertDatabaseHas('rescue_requests', [
            'caller_name' => 'Nguyễn Văn A',
            'reported_by' => $this->citizen->id,
            'status' => 'pending',
        ]);
    }

    /**
     * Test citizen chỉ thấy yêu cầu của mình
     */
    public function test_citizen_only_sees_own_requests(): void
    {
        RescueRequest::factory()->count(3)->create(['reported_by' => $this->citizen->id]);
        RescueRequest::factory()->count(2)->create(); // của người khác

        Sanctum::actingAs($this->citizen);

        $response = $this->getJson('/api/rescue-requests');

        $response->assertStatus(200);
    }

    /**
     * Test operator thấy tất cả yêu cầu
     */
    public function test_operator_sees_all_requests(): void
    {
        RescueRequest::factory()->count(5)->create();

        Sanctum::actingAs($this->operator);

        $response = $this->getJson('/api/rescue-requests');

        $response->assertStatus(200);
    }

    /**
     * Test operator phân công đội cứu hộ
     */
    public function test_operator_can_assign_team(): void
    {
        $request = RescueRequest::factory()->create(['status' => 'pending']);
        $team = RescueTeam::factory()->create(['status' => 'available']);

        Sanctum::actingAs($this->operator);

        $response = $this->putJson("/api/rescue-requests/{$request->id}/assign", [
            'team_id' => $team->id,
        ]);

        $response->assertStatus(200);

        $request->refresh();
        $this->assertEquals('assigned', $request->status);
        $this->assertEquals($team->id, $request->assigned_team_id);
    }

    /**
     * Test lấy danh sách pending requests
     */
    public function test_can_get_pending_requests(): void
    {
        RescueRequest::factory()->count(3)->create(['status' => 'pending']);
        RescueRequest::factory()->count(2)->create(['status' => 'completed']);

        Sanctum::actingAs($this->operator);

        $response = $this->getJson('/api/rescue-requests/pending');

        $response->assertStatus(200);
    }

    /**
     * Test validation tạo request
     */
    public function test_create_rescue_request_validation(): void
    {
        Sanctum::actingAs($this->citizen);

        $response = $this->postJson('/api/rescue-requests', [
            // Thiếu required
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test đánh giá request
     */
    public function test_can_rate_completed_request(): void
    {
        $request = RescueRequest::factory()->create([
            'status' => 'completed',
            'reported_by' => $this->citizen->id,
        ]);

        Sanctum::actingAs($this->citizen);

        $response = $this->postJson("/api/rescue-requests/{$request->id}/rate", [
            'rating' => 5,
            'feedback' => 'Đội cứu hộ rất nhanh và nhiệt tình',
        ]);

        $response->assertStatus(200);

        $request->refresh();
        $this->assertEquals(5, $request->rating);
    }
}
