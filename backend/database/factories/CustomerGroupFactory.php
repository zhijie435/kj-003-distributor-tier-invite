<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerGroupFactory extends Factory
{
    protected $model = \App\Models\CustomerGroup::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'code' => $this->faker->unique()->slug(),
            'description' => $this->faker->sentence(),
            'is_active' => $this->faker->boolean(80),
            'sort_order' => $this->faker->numberBetween(0, 100),
            'settings' => $this->faker->boolean(50) ? [
                'key1' => $this->faker->word(),
                'key2' => $this->faker->randomNumber(),
            ] : null,
        ];
    }
}
