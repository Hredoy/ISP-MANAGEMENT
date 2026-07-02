<?php

namespace Database\Factories;

use App\Models\MockMikrotikUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MockMikrotikUser>
 */
class MockMikrotikUserFactory extends Factory
{
    protected $model = MockMikrotikUser::class;

    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'password' => fake()->password(8, 16),
            'profile' => 'default',
            'service' => 'pppoe',
            'disabled' => fake()->boolean(10),
            'comment' => fake()->name(),
            'local_address' => null,
            'remote_address' => 'client-pool',
            'ip_address' => fake()->unique()->localIpv4(),
            'mac_address' => fake()->macAddress(),
            'caller_id' => fake()->macAddress(),
            'bytes_in' => fake()->numberBetween(0, 50_000_000_000),
            'bytes_out' => fake()->numberBetween(0, 10_000_000_000),
        ];
    }
}
