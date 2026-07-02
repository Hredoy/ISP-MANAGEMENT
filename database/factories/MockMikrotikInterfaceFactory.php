<?php

namespace Database\Factories;

use App\Models\MockMikrotikInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MockMikrotikInterface>
 */
class MockMikrotikInterfaceFactory extends Factory
{
    protected $model = MockMikrotikInterface::class;

    public function definition(): array
    {
        return [
            'name' => 'ether'.fake()->unique()->numberBetween(1, 24),
            'type' => 'ether',
            'running' => fake()->boolean(90),
            'disabled' => fake()->boolean(5),
            'mac_address' => fake()->macAddress(),
            'rx_bytes' => fake()->numberBetween(0, 500_000_000_000),
            'tx_bytes' => fake()->numberBetween(0, 200_000_000_000),
            'rx_bps' => fake()->numberBetween(0, 100_000_000),
            'tx_bps' => fake()->numberBetween(0, 50_000_000),
        ];
    }
}
