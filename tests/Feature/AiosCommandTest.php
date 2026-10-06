<?php

namespace Tests\Feature;

use App\Enums\RoomStatus;
use App\Enums\TaskStatus;
use App\Models\Agent;
use App\Models\CommandHistory;
use App\Models\Project;
use App\Models\Role;
use App\Models\Room;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiosCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_room_add_autonumbers_and_records_history(): void
    {
        $this->artisan('aios', ['input' => ['room', 'add', "'IT Team'"]])
            ->assertExitCode(0);

        $room = Room::where('number', '01')->firstOrFail();
        $this->assertSame('IT Team', $room->name);
        $this->assertSame(['inter_room_enabled' => false, 'links' => []], $room->settings);

        $this->artisan('aios', ['input' => ['room', 'add', "'Riset'"]])
            ->assertExitCode(0);
        $this->assertNotNull(Room::where('number', '02')->first());

        $this->assertSame(2, CommandHistory::where('status', 'executed')->count());
    }

    public function test_agent_add_set_and_remove(): void
    {
        $this->artisan('aios', ['input' => ['room', 'add', "'IT Team'"]])->assertExitCode(0);

        $this->artisan('aios', ['input' => ['agent', 'add', '--room', '01', '--role', "'Backend Dev'", '--model', 'combo-coding']])
            ->assertExitCode(0);

        $agent = Agent::where('slug', 'backend-dev-1')->firstOrFail();
        $this->assertSame('combo-coding', $agent->combo_name);

        $this->artisan('aios', ['input' => ['agent', 'set', 'backend-dev-1', '--model', 'combo-hemat']])
            ->assertExitCode(0);
        $this->assertSame('combo-hemat', $agent->fresh()->combo_name);

        $this->artisan('aios', ['input' => ['agent', 'remove', 'backend-dev-1'], '--force' => true])
            ->assertExitCode(0);
        $this->assertTrue($agent->fresh()->trashed());
        $this->assertSame(0, Agent::count());
    }

    public function test_role_add_with_tools(): void
    {
        $this->artisan('aios', ['input' => ['role', 'add', "'Security Specialist'", '--desc', "'Audit keamanan kode'", '--tools', 'git,sandbox']])
            ->assertExitCode(0);

        $role = Role::where('slug', 'security-specialist')->firstOrFail();
        $this->assertSame(['git', 'sandbox'], $role->allowed_tools);
        $this->assertFalse($role->is_builtin);
    }

    public function test_room_link_and_unlink_are_symmetric(): void
    {
        $this->artisan('aios', ['input' => ['room', 'add', "'A'"]])->assertExitCode(0);
        $this->artisan('aios', ['input' => ['room', 'add', "'B'"]])->assertExitCode(0);

        $this->artisan('aios', ['input' => ['room', 'link', '01', '02'], '--force' => true])->assertExitCode(0);
        $this->assertSame(['02'], Room::where('number', '01')->first()->settings['links']);
        $this->assertSame(['01'], Room::where('number', '02')->first()->settings['links']);

        $this->artisan('aios', ['input' => ['room', 'unlink', '01', '02'], '--force' => true])->assertExitCode(0);
        $this->assertSame([], Room::where('number', '01')->first()->settings['links']);
    }

    public function test_destructive_command_needs_confirmation(): void
    {
        $this->artisan('aios', ['input' => ['room', 'add', "'A'"]])->assertExitCode(0);

        $this->artisan('aios', ['input' => ['room', 'archive', '01']])
            ->expectsConfirmation('Perintah ini mengubah arsip/hubungan/data. Jalankan?', 'no')
            ->assertExitCode(1);
        $this->assertTrue(Room::where('number', '01')->first()->status === RoomStatus::Active);
        $this->assertSame(1, CommandHistory::where('status', 'cancelled')->count());

        $this->artisan('aios', ['input' => ['room', 'archive', '01']])
            ->expectsConfirmation('Perintah ini mengubah arsip/hubungan/data. Jalankan?', 'yes')
            ->assertExitCode(0);
        $this->assertTrue(Room::where('number', '01')->first()->status === RoomStatus::Archived);

        $this->artisan('aios', ['input' => ['room', 'add', "'C'"]])->assertExitCode(0);
        $this->artisan('aios', ['input' => ['room', 'archive', '02'], '--force' => true])
            ->assertExitCode(0);
    }

    public function test_preview_does_not_mutate(): void
    {
        $this->artisan('aios', ['input' => ['room', 'add', "'IT Team'"], '--preview' => true])
            ->assertExitCode(0);

        $this->assertSame(0, Room::count());
        $this->assertSame(1, CommandHistory::where('status', 'preview')->count());
    }

    public function test_project_run_creates_draft(): void
    {
        $this->artisan('aios', ['input' => ['room', 'add', "'IT Team'"]])->assertExitCode(0);

        $this->artisan('aios', ['input' => ['project', 'run', '--room', '01', "'Aplikasi inventaris gudang'"]])
            ->assertExitCode(0);

        $project = Project::where('slug', 'aplikasi-inventaris-gudang')->firstOrFail();
        $this->assertSame('running', $project->status->value);
        $this->assertSame(9, $project->tasks()->count());
    }

    public function test_invalid_and_natural_inputs(): void
    {
        $this->artisan('aios', ['input' => ['room', 'explode', '01']])
            ->assertExitCode(1);

        $this->artisan('aios', ['input' => ['Tambahkan', 'Security']])
            ->assertExitCode(2);
    }

    public function test_room_show_outputs_agents(): void
    {
        $this->artisan('aios', ['input' => ['room', 'add', "'IT Team'"]])->assertExitCode(0);
        $this->artisan('aios', ['input' => ['agent', 'add', '--room', '01', '--role', "'Backend Dev'"]])->assertExitCode(0);

        $this->artisan('aios', ['input' => ['room', 'show', '01']])
            ->expectsOutputToContain('backend-dev-1')
            ->assertExitCode(0);
    }

    public function test_agent_show_reports_progress(): void
    {
        $this->artisan('aios', ['input' => ['room', 'add', "'IT Team'"]])->assertExitCode(0);
        $this->artisan('aios', ['input' => ['agent', 'add', '--room', '01', '--role', "'Backend Dev'"]])->assertExitCode(0);

        $this->artisan('aios', ['input' => ['agent', 'show', 'backend-dev-1']])
            ->expectsOutputToContain('Progres: 0/0 tahap (0%)')
            ->assertExitCode(0);
    }

    public function test_project_progress_and_task_revise(): void
    {
        $this->artisan('aios', ['input' => ['room', 'add', "'IT Team'"]])->assertExitCode(0);
        $this->artisan('aios', ['input' => ['project', 'run', '--room', '01', "'Demo'"]])->assertExitCode(0);

        $project = Project::where('slug', 'demo')->firstOrFail();

        $this->artisan('aios', ['input' => ['project', 'progress', 'demo']])
            ->expectsOutputToContain('0/9 tahap (0%)')
            ->assertExitCode(0);

        $task = $project->tasks()->where('stage', 'analysis-report')->firstOrFail();
        $task->update(['status' => TaskStatus::Failed, 'error' => 'Salah.']);

        $this->artisan('aios', ['input' => ['task', 'revise', (string) $task->id, "'Tambahkan aktor admin'"]])
            ->assertExitCode(0);

        $task->refresh();
        $this->assertSame('queued', $task->status->value);
        $this->assertSame('Tambahkan aktor admin', $task->revision_note);
    }
}
