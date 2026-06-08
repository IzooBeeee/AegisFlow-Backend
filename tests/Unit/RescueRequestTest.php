<?php

namespace Tests\Unit;

use App\Models\FloodZone;
use App\Models\RescueRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RescueRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test tính priority score
     */
    public function test_calculate_priority_score_critical(): void
    {
        $request = RescueRequest::factory()->create([
            'urgency' => 'critical',
            'people_count' => 10,
            'vulnerable_groups' => ['children', 'elderly'],
            'water_level_m' => 3.5,
        ]);

        $score = $request->calculatePriorityScore();

        $this->assertGreaterThanOrEqual(70, $score);
    }

    /**
     * Test priority score thấp cho low urgency
     */
    public function test_calculate_priority_score_low(): void
    {
        $request = RescueRequest::factory()->create([
            'urgency' => 'low',
            'people_count' => 1,
            'vulnerable_groups' => [],
        ]);

        $score = $request->calculatePriorityScore();

        $this->assertLessThan(50, $score);
    }

    /**
     * Test hoàn thành request
     */
    public function test_complete_request(): void
    {
        $request = RescueRequest::factory()->create([
            'status' => 'in_progress',
        ]);

        $request->complete();

        $this->assertEquals('completed', $request->status);
        $this->assertNotNull($request->completed_at);
    }

    /**
     * Test hủy request
     */
    public function test_cancel_request(): void
    {
        $request = RescueRequest::factory()->create();

        $request->cancel('Người yêu cầu hủy');

        $this->assertEquals('cancelled', $request->status);
        $this->assertEquals('Người yêu cầu hủy', $request->cancellation_reason);
    }
}
