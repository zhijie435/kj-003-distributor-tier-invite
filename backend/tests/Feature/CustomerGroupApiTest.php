<?php

namespace Tests\Feature;

use App\Models\CustomerGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerGroupApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_all_customer_groups(): void
    {
        CustomerGroup::factory()->count(5)->create();

        $response = $this->getJson('/api/customer-groups');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'pagination' => ['total', 'per_page', 'current_page', 'last_page'],
            ]);
    }

    public function test_can_get_all_active_customer_groups_without_pagination(): void
    {
        CustomerGroup::factory()->count(3)->create(['is_active' => true]);
        CustomerGroup::factory()->count(2)->create(['is_active' => false]);

        $response = $this->getJson('/api/customer-groups/all');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_customer_group(): void
    {
        $data = [
            'name' => 'Test Group',
            'code' => 'test-group',
            'description' => 'Test Description',
            'is_active' => true,
            'sort_order' => 1,
        ];

        $response = $this->postJson('/api/customer-groups', $data);

        $response->assertStatus(201)
            ->assertJsonFragment($data);

        $this->assertDatabaseHas('customer_groups', $data);
    }

    public function test_can_show_customer_group(): void
    {
        $group = CustomerGroup::factory()->create();

        $response = $this->getJson("/api/customer-groups/{$group->id}");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $group->id,
                'name' => $group->name,
                'code' => $group->code,
            ]);
    }

    public function test_can_update_customer_group(): void
    {
        $group = CustomerGroup::factory()->create();

        $updateData = [
            'name' => 'Updated Group',
            'code' => $group->code,
            'description' => 'Updated Description',
            'is_active' => false,
            'sort_order' => 10,
        ];

        $response = $this->putJson("/api/customer-groups/{$group->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonFragment($updateData);

        $this->assertDatabaseHas('customer_groups', $updateData);
    }

    public function test_can_delete_customer_group(): void
    {
        $group = CustomerGroup::factory()->create();

        $response = $this->deleteJson("/api/customer-groups/{$group->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted($group);
    }

    public function test_can_toggle_customer_group_active_status(): void
    {
        $group = CustomerGroup::factory()->create(['is_active' => true]);

        $response = $this->patchJson("/api/customer-groups/{$group->id}/toggle-active");

        $response->assertStatus(200)
            ->assertJsonFragment(['is_active' => false]);

        $this->assertFalse($group->fresh()->is_active);
    }

    public function test_can_search_customer_groups(): void
    {
        CustomerGroup::factory()->create(['name' => 'Premium Group', 'code' => 'premium']);
        CustomerGroup::factory()->create(['name' => 'Standard Group', 'code' => 'standard']);

        $response = $this->getJson('/api/customer-groups?search=Premium');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Premium Group']);
    }

    public function test_can_filter_active_customer_groups(): void
    {
        CustomerGroup::factory()->create(['is_active' => true]);
        CustomerGroup::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/customer-groups?active=1');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_name_is_required_for_customer_group(): void
    {
        $response = $this->postJson('/api/customer-groups', [
            'code' => 'test-group',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_code_is_required_for_customer_group(): void
    {
        $response = $this->postJson('/api/customer-groups', [
            'name' => 'Test Group',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_code_must_be_unique_for_customer_group(): void
    {
        CustomerGroup::factory()->create(['code' => 'test-group']);

        $response = $this->postJson('/api/customer-groups', [
            'name' => 'Test Group 2',
            'code' => 'test-group',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }
}
