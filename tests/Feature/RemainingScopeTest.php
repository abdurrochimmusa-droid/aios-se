<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Jobs\RunAgentTask;
use App\Models\Cost;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Revision;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Services\Aios\GitService;
use App\Services\Aios\NaturalCommandTranslator;
use App\Services\Aios\Orchestrator;
use App\Services\Aios\PmDispatcher;
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

    public function test_revise_requeues_with_note_and_bumps_version(): void
    {
        config([
            'aios.nine_router.base_url' => 'https://nine.test/v1',
            'aios.nine_router.api_key' => 'test-key',
            'aios.projects_root' => sys_get_temp_dir().'/aios-revise-test',
        ]);
        Http::fake(['*' => Http::response([
            'model' => 'combo-test',
            'choices' => [['message' => ['content' => 'hasil revisi']]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
        ])]);
        $this->seed(RoleSeeder::class);

        $room = Room::factory()->create();
        $role = Role::where('slug', 'senior-data-analyst')->first();
        $agent = $room->agents()->create(['slug' => 'analyst-1', 'name' => 'A', 'role_id' => $role->id]);
        $project = Project::factory()->create(['room_id' => $room->id, 'status' => ProjectStatus::Running]);
        $task = $project->tasks()->create(['stage' => 'analysis-report', 'title' => 'A', 'step' => 1, 'agent_id' => $agent->id, 'status' => TaskStatus::Failed, 'error' => 'Salah.']);

        app(Orchestrator::class)->revise($task, 'Tambahkan aktor admin.');
        $this->assertSame(TaskStatus::Queued, $task->fresh()->status);
        $revision = Revision::where('task_id', $task->id)->firstOrFail();
        $this->assertSame('Tambahkan aktor admin.', $revision->note);
        $this->assertNull($revision->user_id);
        Http::assertSentCount(0);
        $job = new RunAgentTask($task->fresh());
        $job->handle(app(Orchestrator::class), app(NineRouterClient::class), app(SettingsService::class), app(GitService::class), app(SandboxService::class));

        // Prompt ke model memuat catatan revisi.
        Http::assertSent(fn ($request) => str_contains($request->data()['messages'][1]['content'], 'Tambahkan aktor admin'));

        $task->refresh();
        $this->assertSame(TaskStatus::Done, $task->status);
        $this->assertNull($task->revision_note);
        $this->assertSame(1, $project->artifacts()->where('type', 'analysis-report')->where('version', 1)->count());
    }

    public function test_web_revise_form(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $project = Project::factory()->create(['room_id' => Room::factory()->create()->id, 'status' => ProjectStatus::Running]);
        $task = $project->tasks()->create(['stage' => 'prd', 'title' => 'PRD', 'step' => 3, 'status' => TaskStatus::Failed, 'error' => 'Kurang.']);

        $this->actingAs($owner)
            ->post(route('projects.tasks.revise', [$project, $task]), ['note' => 'Tambahkan kriteria penerimaan.'])
            ->assertRedirect(route('projects.show', $project));

        $this->assertSame('queued', $task->fresh()->status->value);
        $revision = Revision::where('task_id', $task->id)->firstOrFail();
        $this->assertSame($owner->id, (int) $revision->user_id);

        // Halaman proyek menampilkan riwayat revisi.
        $this->actingAs($owner)->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Riwayat revisi (1)')
            ->assertSee('Tambahkan kriteria penerimaan.');
    }

    public function test_pm_uses_existing_project_instead_of_inbox(): void
    {
        config(['aios.nine_router.mock' => true]);
        $room = Room::factory()->create(['number' => '07']);
        $role = Role::where('slug', 'backend-dev')->first();
        $room->agents()->create(['slug' => 'be-1', 'name' => 'BE', 'role_id' => $role->id]);
        $project = Project::factory()->create(['room_id' => $room->id, 'status' => ProjectStatus::Done]);

        $countBefore = Project::where('room_id', $room->id)->count();
        app(PmDispatcher::class)->dispatch($room, 'perbaiki API');

        $this->assertSame($countBefore, Project::where('room_id', $room->id)->count());
        $this->assertSame('running', $project->fresh()->status->value);
        $this->assertSame(1, $project->tasks()->count());
    }
}
