<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\Role;
use App\Models\Room;
use App\Models\Task;
use App\Models\User;
use App\Services\Aios\PmDispatcher;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PmManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RoleSeeder::class);
        config(['aios.nine_router.mock' => true]);
    }

    private function teamRoom(): Room
    {
        $room = Room::factory()->create(['number' => '01']);

        foreach (['backend-dev', 'qa-test'] as $slug) {
            $role = Role::where('slug', $slug)->first();
            $room->agents()->create(['slug' => "{$slug}-1", 'name' => $slug, 'role_id' => $role->id]);
        }

        return $room;
    }

    public function test_mock_splits_and_assigns_by_keywords(): void
    {
        $room = $this->teamRoom();

        $result = app(PmDispatcher::class)->dispatch($room, 'perbaiki API login dan buatkan tesnya');

        $this->assertCount(2, $result['tasks']);
        $slugs = collect($result['tasks'])->map(fn (Task $t) => $t->agent->slug)->all();
        $this->assertContains('backend-dev-1', $slugs);
        $this->assertContains('qa-test-1', $slugs);

        $inbox = Project::where('slug', 'inbox-room-01')->firstOrFail();
        $this->assertSame('running', $inbox->status->value);
    }

    public function test_model_plan_assigns_per_json(): void
    {
        config(['aios.nine_router.mock' => false, 'aios.nine_router.api_key' => 'test-key']);
        Http::fake(['*' => Http::response([
            'model' => 'combo-test',
            'choices' => [['message' => ['content' => '[{"role": "qa-test", "title": "uji login"}]']]],
            'usage' => ['prompt_tokens' => 40, 'completion_tokens' => 15],
        ])]);
        $room = $this->teamRoom();

        $result = app(PmDispatcher::class)->dispatch($room, 'uji login');

        $this->assertCount(1, $result['tasks']);
        $this->assertSame('qa-test-1', $result['tasks'][0]->agent->slug);
        $this->assertSame('uji login', $result['tasks'][0]->title);
    }

    public function test_cli_pm_send(): void
    {
        $this->teamRoom();

        $this->artisan('aios', ['input' => ['pm', 'send', '--room', '01', "'perbaiki API dan buatkan tes'"]])
            ->assertExitCode(0);

        $this->assertSame(2, Task::count());
    }

    public function test_web_directive_box(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $room = $this->teamRoom();

        $this->actingAs($owner)
            ->post(route('rooms.directives.store', $room), ['instruction' => 'perbaiki API dan buatkan tes'])
            ->assertRedirect(route('rooms.show', $room))
            ->assertSessionHas('status');

        $this->assertSame(2, Task::count());
    }

    public function test_empty_instruction_rejected(): void
    {
        $room = $this->teamRoom();

        $this->artisan('aios', ['input' => ['pm', 'send', '--room', '01']])
            ->assertExitCode(1);
    }
}
