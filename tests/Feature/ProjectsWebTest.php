<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Approval;
use App\Models\Project;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectsWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => UserRole::Owner]);
    }

    public function test_index_and_show_render_for_viewer(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $room = Room::factory()->create();
        $project = Project::factory()->create(['room_id' => $room->id]);

        $this->actingAs($viewer)->get(route('projects.index'))->assertOk()->assertSee($project->name);
        $this->actingAs($viewer)->get(route('projects.show', $project))->assertOk()->assertSee('Linimasa tahap');
    }

    public function test_approver_can_approve_but_not_pause(): void
    {
        $owner = $this->owner();
        $approver = User::factory()->create(['role' => UserRole::Approver]);
        $project = Project::factory()->create(['room_id' => Room::factory()->create()->id, 'status' => ProjectStatus::Running, 'owner_id' => $owner->id]);
        $approval = Approval::create(['project_id' => $project->id, 'stage' => 'prd', 'requested_by' => $owner->id]);

        $this->actingAs($approver)
            ->post(route('projects.approvals.approve', [$project, $approval]))
            ->assertRedirect(route('projects.show', $project));

        $this->assertSame('approved', $approval->fresh()->status->value);
        $this->actingAs($approver)->post(route('projects.pause', $project))->assertForbidden();
    }

    public function test_viewer_cannot_approve(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $project = Project::factory()->create(['room_id' => Room::factory()->create()->id]);
        $approval = Approval::create(['project_id' => $project->id, 'stage' => 'prd']);

        $this->actingAs($viewer)
            ->post(route('projects.approvals.approve', [$project, $approval]))
            ->assertForbidden();
    }

    public function test_pause_resume_cancel_flow(): void
    {
        $owner = $this->owner();
        $project = Project::factory()->create(['room_id' => Room::factory()->create()->id, 'status' => ProjectStatus::Running]);

        $this->actingAs($owner)->post(route('projects.pause', $project))->assertRedirect();
        $this->assertSame('paused', $project->fresh()->status->value);

        $this->actingAs($owner)->post(route('projects.resume', $project))->assertRedirect();
        $this->assertSame('running', $project->fresh()->status->value);

        $this->actingAs($owner)->post(route('projects.cancel', $project))->assertRedirect();
        $this->assertSame('done', $project->fresh()->status->value);
    }
}
