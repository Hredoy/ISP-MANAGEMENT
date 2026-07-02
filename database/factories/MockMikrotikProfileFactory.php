<?php

namespace Database\Factories;

use App\Models\MockMikrotikProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MockMikrotikProfile>
 */
class MockMikrotikProfileFactory extends Factory
{
    protected $model = MockMikrotikProfile::class;

    public function definition(): array
    {
        $rate = fake()->randomElement([5, 10, 15, 20, 25, 30, 50, 100]);

        return [
            'name' => fake()->unique()->randomElement(['Bronze', 'Silver', 'Gold', 'Platinum', 'Nano', 'Starter', 'Home', 'Business']).'_'.$rate.'M',
            'rate_limit' => "{$rate}M/{$rate}M",
            'local_address' => '10.10.0.1',
            'remote_address' => 'client-pool',
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['name' => 'default', 'rate_limit' => '5M/5M', 'is_default' => true]);
    }
}
