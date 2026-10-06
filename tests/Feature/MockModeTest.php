<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agent;
use App\Models\Cost;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Services\NineRouter\NineRouterClient;
use App\Services\SettingsService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MockModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_mock_answers_without_api_key_and_records_cost(): void
    {
        Http::preventStrayRequests();
        config(['aios.nine_router.api_key' => '']);
        config(['aios.nine_router.mock' => true]);
        $this->seed(RoleSeeder::class);

        $room = Room::factory()->create();
        $agent = Agent::factory()->create([
            'room_id' => $room->id,
            'role_id' => Role::where('slug', 'prd-specialist')->first()->id,
        ]);

        $result = app(NineRouterClient::class)->chatForAgent(
            $agent,
            [['role' => 'user', 'content' => 'susun PRD']],
            ['stage' => 'prd']
        );

        $this->assertStringContainsString('# PRD', $result['content']);
        $this->assertGreaterThan(0, $result['tokens_out']);
        $this->assertSame(1, Cost::count());
    }

    public function test_mock_toggle_persisted_via_settings(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);

        $this->actingAs($owner)->put(route('settings.update'), [
            'nine_router_base_url' => 'https://nine.example/v1',
            'nine_router_default_combo' => 'combo-hemat',
            'nine_router_timeout' => 120,
            'nine_router_mock' => '1',
            'task_token_budget' => 200000,
            'delegation_max_depth' => 4,
            'delegation_max_rounds' => 5,
            'projects_root' => '',
        ])->assertRedirect(route('settings.edit'));

        $this->assertTrue(app(SettingsService::class)->getBool('nine_router.mock'));
    }
}
