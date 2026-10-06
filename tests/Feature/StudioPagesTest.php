<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Approval;
use App\Models\Cost;
use App\Models\Project;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RoleSeeder::class);
        $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));
    }

    public function test_approvals_queue_and_decide(): void
    {
        $project = Project::factory()->create(['room_id' => Room::factory()->create()->id]);
        $approval = Approval::create(['project_id' => $project->id, 'stage' => 'prd']);

        $this->get(route('approvals.index'))->assertOk()->assertSee('Menunggu (1)');

        $this->post(route('approvals.approve', $approval))->assertRedirect(route('approvals.index'));
        $this->assertSame('approved', $approval->fresh()->status->value);

        // Keputusan ganda ditolak.
        $this->post(route('approvals.reject', $approval))->assertSessionHasErrors('approval');
    }

    public function test_roles_index_and_store(): void
    {
        $this->get(route('roles.index'))->assertOk()->assertSee('Backend Dev');

        $this->post(route('roles.store'), ['name' => 'Security Specialist', 'tools' => 'git,sandbox'])
            ->assertRedirect(route('roles.index'));

        $role = Role::where('slug', 'security-specialist')->firstOrFail();
        $this->assertSame(['git', 'sandbox'], $role->allowed_tools);
    }

    public function test_costs_index_shows_usage(): void
    {
        $project = Project::factory()->create(['room_id' => Room::factory()->create()->id]);
        Cost::create(['project_id' => $project->id, 'room_id' => $project->room_id, 'model' => 'combo-test', 'tokens_in' => 100, 'tokens_out' => 50]);

        $this->get(route('costs.index'))->assertOk()->assertSee('150')->assertSee('combo-test');
    }

    public function test_viewer_reads_but_cannot_mutate(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $approval = Approval::create(['project_id' => Project::factory()->create(['room_id' => Room::factory()->create()->id])->id, 'stage' => 'prd']);

        $this->actingAs($viewer)->get(route('approvals.index'))->assertOk();
        $this->actingAs($viewer)->get(route('roles.index'))->assertOk();
        $this->actingAs($viewer)->get(route('costs.index'))->assertOk();
        $this->actingAs($viewer)->post(route('approvals.approve', $approval))->assertForbidden();
        $this->actingAs($viewer)->post(route('roles.store'), ['name' => 'X'])->assertForbidden();
    }
}
