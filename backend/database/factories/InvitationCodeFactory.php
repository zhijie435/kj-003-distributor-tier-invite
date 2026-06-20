<?php

namespace Database\Factories;

use App\Models\CustomerGroup;
use App\Models\InvitationCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class InvitationCodeFactory extends Factory
{
    protected $model = InvitationCode::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(Str::random(8)),
            'customer_group_id' => CustomerGroup::factory(),
            'description' => fake()->sentence(),
            'max_uses' => 0,
            'used_count' => 0,
            'expires_at' => null,
            'is_active' => true,
        ];
    }

    public function forGroup(CustomerGroup $group): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_group_id' => $group->id,
        ]);
    }

    public function limitedUses(int $maxUses = 10): static
    {
        return $this->state(fn (array $attributes) => [
            'max_uses' => $maxUses,
        ]);
    }

    public function usedUp(int $maxUses = 10): static
    {
        return $this->state(fn (array $attributes) => [
            'max_uses' => $maxUses,
            'used_count' => $maxUses,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDays(1),
        ]);
    }

    public function notExpired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->addDays(30),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withCustomCode(string $code): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => strtoupper($code),
        ]);
    }
}
