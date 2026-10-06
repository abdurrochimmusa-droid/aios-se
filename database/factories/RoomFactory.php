<?php

namespace Database\Factories;

use App\Enums\RoomStatus;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => str_pad((string) fake()->unique()->numberBetween(1, 89), 2, '0', STR_PAD_LEFT),
            'name' => fake()->words(2, true),
            'status' => RoomStatus::Active,
            'settings' => ['inter_room_enabled' => false, 'links' => []],
        ];
    }
}
