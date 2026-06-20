<?php

namespace Database\Factories;

use App\Models\CustomerGroup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CustomerGroupFactory extends Factory
{
    protected $model = CustomerGroup::class;

    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'code' => strtoupper(Str::random(8)),
            'description' => fake()->sentence(),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
            'settings' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
