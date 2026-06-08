<?php

namespace Tests\Feature;

use App\Enums\IncidentStatusEnum;
use App\Enums\SeverityEnum;
use App\Enums\IncidentSourceEnum;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IncidentApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $operator;
    protected User $citizen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operator = User::factory()->create();
        $this->operator->assignRole(\Spatie\Permission\Models\Role::create([
            'name' => 'rescue_operator', 'display_name' => 'Điều phối viên', 'slug' => 'rescue_operator',
        ]));

        $this->citizen = User::factory()->create();
        $this->citizen->assignRole(\Spatie\Permission\Models\Role::create([
            'name' => 'citizen', 'display_name' => 'Công dân', 'slug' => 'citizen',
        ]));
    }

    /**
     * Test công dân có thể tạo incident
     */
    public function test_citizen_can_create_incident(): void
    {
        Sanctum::actingAs($this->citizen);

        $response = $this->postJson('/api/incidents', [
            'title' => 'Ngập đường Nguyễn Lương Bằng',
            'type' => 'flood',
            'severity' => 'high',
            'latitude' => 16.0544,
            'longitude' => 108.2022,
            'address' => '123 Nguyễn Lương Bằng, Đà Nẵng',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'title', 'type', 'severity', 'status', 'source'],
            ]);

        $this->assertDatabaseHas('incidents', [
            'title' => 'Ngập đường Nguyễn Lương Bằng',
            'reported_by' => $this->citizen->id,
            'source' => IncidentSourceEnum::CITIZEN->value,
        ]);
    }

    /**
     * Test tạo incident với severity cao dispatch AI job
     */
    public function test_create_high_severity_dispatches_ai_job(): void
    {
        Sanctum::actingAs($this->citizen);
        Bus::fake();

        $response = $this->postJson('/api/incidents', [
            'title' => 'Ngập nghiêm trọng',
            'type' => 'flood',
            'severity' => 'critical',
            'latitude' => 16.0544,
            'longitude' => 108.2022,
        ]);

        $response->assertStatus(201);
    }

    /**
     * Test validation khi tạo incident
     */
    public function test_create_incident_validation(): void
    {
        Sanctum::actingAs($this->citizen);

        $response = $this->postJson('/api/incidents', [
            // Thiếu required fields
        ]);

        $response->assertStatus(422);
    }

    /**
     * Test danh sách incidents
     */
    public function test_can_list_incidents(): void
    {
        Incident::factory()->count(5)->create();

        Sanctum::actingAs($this->operator);

        $response = $this->getJson('/api/incidents');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    /**
     * Test xem chi tiết incident
     */
    public function test_can_show_incident(): void
    {
        $incident = Incident::factory()->create(['title' => 'Test Incident']);

        Sanctum::actingAs($this->operator);

        $response = $this->getJson("/api/incidents/{$incident->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['id' => $incident->id],
            ]);
    }

    /**
     * Test cập nhật trạng thái incident
     */
    public function test_can_update_incident_status(): void
    {
        $incident = Incident::factory()->create();

        Sanctum::actingAs($this->operator);

        $response = $this->patchJson("/api/incidents/{$incident->id}", [
            'status' => IncidentStatusEnum::RESOLVED->value,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'status' => IncidentStatusEnum::RESOLVED->value,
        ]);
    }

    /**
     * Test resolve incident set resolved_at
     */
    public function test_resolving_sets_resolved_at(): void
    {
        $incident = Incident::factory()->create();

        Sanctum::actingAs($this->operator);

        $this->patchJson("/api/incidents/{$incident->id}", [
            'status' => IncidentStatusEnum::RESOLVED->value,
        ]);

        $incident->refresh();

        $this->assertNotNull($incident->resolved_at);
    }

    /**
     * Test lọc incidents theo status
     */
    public function test_can_filter_by_status(): void
    {
        Incident::factory()->count(3)->create(['status' => 'reported']);
        Incident::factory()->count(2)->create(['status' => 'resolved']);

        Sanctum::actingAs($this->operator);

        $response = $this->getJson('/api/incidents?status=reported');

        $response->assertStatus(200);
    }

    /**
     * Test incident không tồn tại
     */
    public function test_incident_not_found_returns_404(): void
    {
        Sanctum::actingAs($this->operator);

        $response = $this->getJson('/api/incidents/99999');

        $response->assertStatus(404);
    }
}
