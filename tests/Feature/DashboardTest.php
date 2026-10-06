<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\Project;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create());
    }

    public function test_dashboard_renders_summary(): void
    {
        $room = Room::factory()->create(['number' => '01', 'name' => 'IT Team']);
        $project = Project::factory()->create(['room_id' => $room->id]);
        Approval::create([
            'project_id' => $project->id,
            'stage' => 'prd',
            'requested_by' => null,
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Room-01 IT Team');
        $response->assertSee('Antrean persetujuan');
        $response->assertSee('Biaya hari ini');
    }

    public function test_dashboard_empty_state(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Belum ada room');
    }
}
