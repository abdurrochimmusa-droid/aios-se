<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Jobs\RunAgentTask;
use App\Models\Cost;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Services\Aios\GitService;
use App\Services\Aios\NaturalCommandTranslator;
use App\Services\Aios\Orchestrator;
use App\Services\Aios\SandboxService;
use App\Services\NineRouter\NineRouterClient;
use App\Services\SettingsService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RemainingScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    public function test_nonmember_viewer_cannot_open_project(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $project = Project::factory()->create(['room_id' => Room::factory()->create()->id]);

        $this->actingAs($viewer)->get(route('projects.show', $project))->assertForbidden();
        $this->actingAs($viewer)->get(route('projects.index'))->assertOk()->assertDontSee($project->name);
    }

    public function test_member_viewer_can_open_and_approve_but_not_manage(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);
        $project = Project::factory()->create(['room_id' => Room::factory()->create()->id, 'owner_id' => $owner->id]);
        ProjectMember::create(['project_id' => $project->id, 'user_id' => $viewer->id, 'role' => UserRole::Viewer]);

        $this->actingAs($viewer)->get(route('projects.show', $project))->assertOk();
        $this->actingAs($viewer)->post(route('projects.pause', $project))->assertForbidden();

        $this->actingAs($owner)
            ->post(route('projects.members.store', $project), ['email' => $viewer->email, 'role' => 'approver'])
            ->assertRedirect(route('projects.show', $project));
        $this->assertSame('approver', $viewer->projectMemberships()->first()->role->value);
    }

    public function test_natural_language_translates_then_confirms(): void
    {
        Http::fake(['*' => Http::response([
            'model' => 'combo-test',
            'choices' => [['message' => ['content' => '{"command": "room.add", "positional": ["Tim Riset"], "options": {}}']]],
            'usage' => ['prompt_tokens' => 50, 'completion_tokens' => 20],
        ])]);
        config(['aios.nine_router.api_key' => 'test-key']);
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        $translation = app(NaturalCommandTranslator::class)->translate('Buatkan room Tim Riset');
        $this->assertTrue($translation['ok']);
        $this->assertSame('room.add', $translation['parsed']['command']);

        // Pratinjau menampilkan hasil terjemahan, eksekusi membuat room.
        $this->actingAs($owner)
            ->post(route('console.preview'), ['input' => 'Buatkan room Tim Riset'])
            ->assertOk()->assertSee('Diterjemahkan dari');

        $this->actingAs($owner)
            ->post(route('console.run'), ['input' => "room add 'Tim Riset'"])
            ->assertRedirect(route('console.index'));
        $this->assertNotNull(Room::where('name', 'Tim Riset')->first());
    }

    public function test_translation_failure_returns_guidance(): void
    {
        Http::fake(['*' => Http::response([
            'model' => 'combo-test',
            'choices' => [['message' => ['content' => '{"error": "tidak mengerti"}']]],
            'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
        ])]);
        config(['aios.nine_router.api_key' => 'test-key']);
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($owner)
            ->post(route('console.preview'), ['input' => 'asdf tidak jelas'])
            ->assertRedirect(route('console.index'))
            ->assertSessionHasErrors('input');
    }

    public function test_project_stages_can_be_reordered_and_removed(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $project = Project::factory()->create(['room_id' => Room::factory()->create()->id]);

        $this->actingAs($owner)
            ->post(route('projects.stages.move', [$project, 'research-summary', 'up']))
            ->assertRedirect(route('projects.show', $project));

        $stages = $project->fresh()->stages;
        $this->assertSame('research-summary', $stages[0]['stage']);
        $this->assertSame(1, $stages[0]['step']);

        $this->actingAs($owner)
            ->delete(route('projects.stages.remove', [$project, 'research-summary']))
            ->assertRedirect(route('projects.show', $project));

        $this->assertFalse(collect($project->fresh()->stages)->contains(fn ($e) => $e['stage'] === 'research-summary'));

        // Plan memakai alur kustom: tanpa riset = 8 tahap.
        $this->assertSame(8, app(Orchestrator::class)->plan($project)['planned']);
    }

    public function test_project_token_budget_pauses_project(): void
    {
        config([
            'aios.nine_router.base_url' => 'https://nine.test/v1',
            'aios.nine_router.api_key' => 'test-key',
            'aios.projects_root' => sys_get_temp_dir().'/aios-budget-test',
        ]);
        Http::fake(['*' => Http::response([
            'model' => 'combo-test',
            'choices' => [['message' => ['content' => 'ok']]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
        ])]);
        $this->seed(RoleSeeder::class);

        $room = Room::factory()->create();
        $role = Role::where('slug', 'senior-data-analyst')->first();
        $agent = $room->agents()->create(['slug' => 'analyst-1', 'name' => 'A', 'role_id' => $role->id]);
        $project = Project::factory()->create(['room_id' => $room->id, 'status' => ProjectStatus::Running, 'token_budget' => 5]);
        Cost::create(['project_id' => $project->id, 'room_id' => $room->id, 'agent_id' => $agent->id, 'tokens_in' => 3, 'tokens_out' => 2]);

        $task = $project->tasks()->create(['stage' => 'analysis-report', 'title' => 'A', 'step' => 1, 'agent_id' => $agent->id]);
        $job = new RunAgentTask($task);
        $job->handle(app(Orchestrator::class), app(NineRouterClient::class), app(SettingsService::class), app(GitService::class), app(SandboxService::class));

        $this->assertSame(ProjectStatus::Paused, $project->fresh()->status);
    }
}
