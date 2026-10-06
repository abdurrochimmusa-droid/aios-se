<?php

namespace Database\Factories;

use App\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\Role;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'room_id' => Room::factory(),
            'role_id' => Role::factory(),
            'status' => AgentStatus::Active,
        ];
    }
}
