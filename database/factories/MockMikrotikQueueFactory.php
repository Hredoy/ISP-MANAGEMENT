<?php

namespace Database\Factories;

use App\Models\MockMikrotikQueue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MockMikrotikQueue>
 */
class MockMikrotikQueueFactory extends Factory
{
    protected $model = MockMikrotikQueue::class;

    public function definition(): array
    {
        $rate = fake()->randomElement([5, 10, 15, 20, 25, 30, 50]);

        return [
            'name' => fake()->unique()->userName().'-queue',
            'target' => fake()->localIpv4(),
            'max_limit' => "{$rate}M/{$rate}M",
            'bytes_in' => fake()->numberBetween(0, 20_000_000_000),
            'bytes_out' => fake()->numberBetween(0, 5_000_000_000),
            'disabled' => fake()->boolean(5),
        ];
    }
}
