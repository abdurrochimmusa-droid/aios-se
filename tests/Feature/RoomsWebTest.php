<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Agent;
use App\Models\CommandHistory;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomsWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RoleSeeder::class);
        $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));
    }

    public function test_index_lists_rooms(): void
    {
        Room::factory()->create(['number' => '01', 'name' => 'IT Team']);

        $this->get(route('rooms.index'))
            ->assertOk()
            ->assertSee('Room-01 IT Team')
            ->assertSee('+ Add Room');
    }

    public function test_store_creates_room_and_redirects_to_detail(): void
    {
        $response = $this->post(route('rooms.store'), ['name' => 'IT Team']);

        $room = Room::where('number', '01')->firstOrFail();
        $response->assertRedirect(route('rooms.show', $room));
        $this->assertSame(1, CommandHistory::where('status', 'executed')->count());
    }

    public function test_store_validates_name(): void
    {
        $this->post(route('rooms.store'), ['name' => ''])
            ->assertSessionHasErrors('name');
        $this->assertSame(0, Room::count());
    }

    public function test_show_displays_agents_and_add_form(): void
    {
        $room = Room::factory()->create(['number' => '01']);

        $this->get(route('rooms.show', $room))
            ->assertOk()
            ->assertSee('Tambah agen');
    }

    public function test_store_agent_and_remove(): void
    {
        $room = Room::factory()->create(['number' => '01']);

        $this->post(route('rooms.agents.store', $room), ['role' => 'Backend Dev'])
            ->assertRedirect(route('rooms.show', $room));

        $agent = Agent::where('slug', 'backend-dev-1')->firstOrFail();

        $this->delete(route('rooms.agents.destroy', [$room, $agent]))
            ->assertRedirect(route('rooms.show', $room));
        $this->assertTrue($agent->fresh()->trashed());
    }

    public function test_archive_room(): void
    {
        $room = Room::factory()->create(['number' => '01']);

        $this->post(route('rooms.archive', $room))
            ->assertRedirect(route('rooms.index'));
        $this->assertSame('archived', $room->fresh()->status->value);
    }
}
