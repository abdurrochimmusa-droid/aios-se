<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Cost;
use App\Models\Project;
use App\Models\Role;
use App\Models\Room;
use App\Services\NineRouter\NineRouterClient;
use App\Services\NineRouter\NineRouterException;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NineRouterClientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'aios.nine_router.base_url' => 'https://nine.test/v1',
            'aios.nine_router.api_key' => 'test-key',
            'aios.nine_router.timeout' => 5,
        ]);
    }

    private function fakeSuccess(string $model = 'combo-hemat'): void
    {
        Http::fake([
            '*' => Http::response([
                'model' => $model,
                'choices' => [['message' => ['content' => 'ok']]],
                'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 3],
            ]),
        ]);
    }

    public function test_chat_sends_bearer_and_model_payload(): void
    {
        Http::preventStrayRequests();
        $this->fakeSuccess();

        $result = app(NineRouterClient::class)->chat(
            [['role' => 'user', 'content' => 'halo']],
            'combo-hemat'
        );

        $this->assertSame('ok', $result['content']);
        $this->assertSame(12, $result['tokens_in']);
        $this->assertSame(3, $result['tokens_out']);

        Http::assertSent(fn (Request $request) => $request->url() === config('aios.nine_router.base_url').'/chat/completions'
            && $request->header('Authorization') === ['Bearer test-key']
            && $request->data()['model'] === 'combo-hemat'
            && $request->data()['messages'][0]['content'] === 'halo');
    }

    public function test_chat_for_agent_resolves_combo_and_records_cost(): void
    {
        Http::preventStrayRequests();
        $this->fakeSuccess('combo-coding');
        $this->seed(RoleSeeder::class);

        $room = Room::factory()->create();
        $project = Project::factory()->create(['room_id' => $room->id]);
        $agent = Agent::factory()->create([
            'room_id' => $room->id,
            'role_id' => Role::where('slug', 'backend-dev')->first()->id,
            'combo_name' => 'combo-coding',
        ]);

        $result = app(NineRouterClient::class)->chatForAgent(
            $agent,
            [['role' => 'user', 'content' => 'tulis migrasi']],
            ['project_id' => $project->id, 'stage' => 'schema']
        );

        $this->assertSame('combo-coding', $result['model']);

        $cost = Cost::firstOrFail();
        $this->assertSame($project->id, $cost->project_id);
        $this->assertSame($agent->id, $cost->agent_id);
        $this->assertSame('schema', $cost->stage);
        $this->assertSame(12, (int) $cost->tokens_in);
    }

    public function test_server_error_becomes_exception_without_cost_row(): void
    {
        Http::fake(['*' => Http::response(['error' => 'overload'], 503)]);

        $this->expectException(NineRouterException::class);

        try {
            app(NineRouterClient::class)->chat([['role' => 'user', 'content' => 'halo']]);
        } finally {
            $this->assertSame(0, Cost::count());
        }
    }

    public function test_connection_failure_becomes_exception(): void
    {
        Http::fake(['*' => Http::failedConnection()]);

        $this->expectException(NineRouterException::class);
        app(NineRouterClient::class)->chat([['role' => 'user', 'content' => 'halo']]);
    }

    public function test_missing_api_key_fails_fast_without_request(): void
    {
        Http::preventStrayRequests();
        config(['aios.nine_router.api_key' => '']);

        $this->expectException(NineRouterException::class);
        app(NineRouterClient::class)->chat([['role' => 'user', 'content' => 'halo']]);
    }
}
