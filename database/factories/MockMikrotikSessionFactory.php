<?php

namespace Database\Factories;

use App\Models\MockMikrotikSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MockMikrotikSession>
 */
class MockMikrotikSessionFactory extends Factory
{
    protected $model = MockMikrotikSession::class;

    public function definition(): array
    {
        return [
            'address' => fake()->localIpv4(),
            'caller_id' => fake()->macAddress(),
            'uptime_seconds' => fake()->numberBetween(60, 86400 * 5),
        ];
    }
}
