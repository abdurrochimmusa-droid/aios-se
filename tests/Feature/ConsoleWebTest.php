<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CommandHistory;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsoleWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));
    }

    public function test_preview_shows_plan_without_mutating(): void
    {
        $response = $this->post(route('console.preview'), ['input' => "room add 'IT Team'"]);

        $response->assertOk()->assertSee('Akan membuat Room-01');
        $this->assertSame(0, Room::count());
    }

    public function test_run_executes_after_preview(): void
    {
        $this->post(route('console.preview'), ['input' => "room add 'IT Team'"])->assertOk();

        $this->post(route('console.run'), ['input' => "room add 'IT Team'"])
            ->assertRedirect(route('console.index'))
            ->assertSessionHas('status');

        $this->assertNotNull(Room::where('number', '01')->first());
        $this->assertSame(1, CommandHistory::where('status', 'executed')->count());
    }

    public function test_natural_language_gets_guidance(): void
    {
        $this->post(route('console.preview'), ['input' => 'Tambahkan Security'])
            ->assertRedirect(route('console.index'))
            ->assertSessionHasErrors('input');
    }
}
