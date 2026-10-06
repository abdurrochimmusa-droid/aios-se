<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Jobs\RunAgentTask;
use App\Models\Approval;
use App\Models\Artifact;
use App\Models\Project;
use App\Models\Role;
use App\Models\Room;
use App\Models\Task;
use App\Models\User;
use App\Services\Aios\CommandException;
use App\Services\Aios\GitService;
use App\Services\Aios\Orchestrator;
use App\Services\Aios\SandboxService;
use App\Services\NineRouter\NineRouterClient;
use App\Services\SettingsService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\ManagesTestRepos;
use Tests\TestCase;

class OrchestratorTest extends TestCase
{
    use ManagesTestRepos;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->clearRepoRoot(sys_get_temp_dir().'/aios-orch-test');
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'aios.nine_router.base_url' => 'https://nine.test/v1',
            'aios.nine_router.api_key' => 'test-key',
            'aios.projects_root' => sys_get_temp_dir().'/aios-orch-test',
        ]);
        Http::fake(['*' => Http::response([
            'model' => 'combo-test',
            'choices' => [['message' => ['content' => '# Hasil tahap']]],
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
        ])]);
        $this->seed(RoleSeeder::class);
    }

    private function roomWithFullTeam(): Room
    {
        $room = Room::factory()->create(['number' => '01']);

        foreach (Role::all() as $i => $role) {
            $room->agents()->create([
                'slug' => "{$role->slug}-1",
                'name' => "{$role->name} 1",
                'role_id' => $role->id,
                'combo_name' => 'combo-test',
            ]);
        }

        return $room;
    }

    private function runJob(Task $task): void
    {
        $job = new RunAgentTask($task);
        $job->handle(
            app(Orchestrator::class),
            app(NineRouterClient::class),
            app(SettingsService::class),
            app(GitService::class),
            app(SandboxService::class)
        );
    }

    public function test_plan_assigns_all_stages_to_matching_agents(): void
    {
        $room = $this->roomWithFullTeam();
        $project = Project::factory()->create(['room_id' => $room->id]);

        $result = app(Orchestrator::class)->plan($project);

        $this->assertSame(9, $result['planned']);
        $this->assertSame([], $result['unassigned']);
        $this->assertSame(9, $project->tasks()->whereNotNull('agent_id')->count());
        $this->assertSame(2, $project->tasks()->where('step', 7)->count());
    }

    public function test_plan_reports_stages_without_agent(): void
    {
        $room = Room::factory()->create();
        $project = Project::factory()->create(['room_id' => $room->id]);

        $result = app(Orchestrator::class)->plan($project);

        $this->assertSame(9, $result['planned']);
        $this->assertNotEmpty($result['unassigned']);
    }

    public function test_task_run_creates_versioned_artifact_and_advances(): void
    {
        Queue::fake();
        $room = $this->roomWithFullTeam();
        $project = Project::factory()->create(['room_id' => $room->id]);

        app(Orchestrator::class)->plan($project);
        app(Orchestrator::class)->start($project);

        Queue::assertPushed(RunAgentTask::class, 1);

        $task = $project->tasks()->where('stage', 'analysis-report')->firstOrFail();
        $this->runJob($task);

        $artifact = Artifact::where('type', 'analysis-report')->firstOrFail();
        $this->assertSame('# Hasil tahap', $artifact->body);
        $this->assertSame(1, $artifact->version);
        $this->assertSame(TaskStatus::Done, $task->fresh()->status);
        Queue::assertPushed(RunAgentTask::class, 2);
    }

    public function test_prd_gate_holds_pipeline_until_approved(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $room = $this->roomWithFullTeam();
        $project = Project::factory()->create(['room_id' => $room->id, 'status' => ProjectStatus::Running, 'owner_id' => $owner->id]);

        app(Orchestrator::class)->plan($project);
        $task = $project->tasks()->where('stage', 'prd')->firstOrFail();
        $this->runJob($task);

        $this->assertSame(TaskStatus::WaitingApproval, $task->fresh()->status);
        $approval = Approval::where('stage', 'prd')->firstOrFail();
        $this->assertSame($owner->id, (int) $approval->requested_by);

        app(Orchestrator::class)->approve($approval, $owner->id);

        $this->assertSame(TaskStatus::Done, $task->fresh()->status);
        Queue::assertPushed(RunAgentTask::class, 1);
    }

    public function test_reject_pauses_project_and_fails_task(): void
    {
        $owner = User::factory()->create();
        $room = $this->roomWithFullTeam();
        $project = Project::factory()->create(['room_id' => $room->id, 'status' => ProjectStatus::Running, 'owner_id' => $owner->id]);

        app(Orchestrator::class)->plan($project);
        $task = $project->tasks()->where('stage', 'prd')->firstOrFail();
        $this->runJob($task);

        $approval = Approval::where('stage', 'prd')->firstOrFail();
        app(Orchestrator::class)->reject($approval, $owner->id, 'Kurang detail.');

        $this->assertSame(ProjectStatus::Paused, $project->fresh()->status);
        $this->assertSame(TaskStatus::Failed, $task->fresh()->status);
    }

    public function test_release_gate_completes_project(): void
    {
        $owner = User::factory()->create();
        $room = $this->roomWithFullTeam();
        $project = Project::factory()->create(['room_id' => $room->id, 'status' => ProjectStatus::Running, 'owner_id' => $owner->id]);

        app(Orchestrator::class)->plan($project);
        $task = $project->tasks()->where('stage', 'test-report')->firstOrFail();
        $this->runJob($task);

        $approval = Approval::where('stage', 'release')->firstOrFail();
        app(Orchestrator::class)->approve($approval, $owner->id);

        $this->assertSame(ProjectStatus::Done, $project->fresh()->status);
    }

    public function test_paused_project_job_exits_without_effect(): void
    {
        $room = $this->roomWithFullTeam();
        $project = Project::factory()->create(['room_id' => $room->id, 'status' => ProjectStatus::Paused]);

        app(Orchestrator::class)->plan($project);
        $task = $project->tasks()->where('stage', 'analysis-report')->firstOrFail();
        $this->runJob($task);

        $this->assertSame(TaskStatus::Queued, $task->fresh()->status);
        $this->assertSame(0, Artifact::count());
    }

    public function test_token_budget_pauses_task(): void
    {
        config(['aios.task_token_budget' => 5]);
        $room = $this->roomWithFullTeam();
        $project = Project::factory()->create(['room_id' => $room->id, 'status' => ProjectStatus::Running]);

        app(Orchestrator::class)->plan($project);
        $task = $project->tasks()->where('stage', 'analysis-report')->firstOrFail();
        $task->update(['tokens_in' => 3, 'tokens_out' => 2]);
        $this->runJob($task);

        $this->assertSame(TaskStatus::Paused, $task->fresh()->status);
        $this->assertSame(0, Artifact::count());
    }

    public function test_delegation_limits_are_enforced(): void
    {
        $room = $this->roomWithFullTeam();
        $agents = $room->agents;
        $project = Project::factory()->create(['room_id' => $room->id]);

        app(Orchestrator::class)->plan($project);
        $root = $project->tasks()->where('stage', 'prd')->firstOrFail();

        $child = app(Orchestrator::class)->delegate($root, $agents[1], 'Bantu riset');
        $this->assertSame(1, $child->depth);

        $deep = $child;
        for ($i = 2; $i <= 4; $i++) {
            $deep = app(Orchestrator::class)->delegate($deep, $agents[0], "Lanjut {$i}");
        }

        $this->expectException(CommandException::class);
        app(Orchestrator::class)->delegate($deep, $agents[1], 'Terlalu dalam');
    }
}
