<?php

namespace Tests\Feature;

use App\Models\CustomerGroup;
use App\Models\InvitationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationCodeApiTest extends TestCase
{
    use RefreshDatabase;

    private CustomerGroup $customerGroup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customerGroup = CustomerGroup::create([
            'name' => 'Test Group',
            'code' => 'test-group',
            'description' => 'Test description',
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    public function test_can_list_invitation_codes(): void
    {
        InvitationCode::create([
            'customer_group_id' => $this->customerGroup->id,
            'code' => 'ABC12345',
        ]);

        $response = $this->getJson('/api/invitation-codes');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'customer_group_id', 'is_valid'],
                ],
                'pagination' => ['total', 'per_page', 'current_page', 'last_page'],
            ]);
    }

    public function test_can_create_invitation_code_with_auto_generated_code(): void
    {
        $response = $this->postJson('/api/invitation-codes', [
            'customer_group_id' => $this->customerGroup->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.customer_group_id', $this->customerGroup->id)
            ->assertJsonStructure(['data' => ['id', 'code', 'is_valid']]);

        $this->assertNotEmpty($response->json('data.code'));
    }

    public function test_can_create_invitation_code_with_custom_code(): void
    {
        $response = $this->postJson('/api/invitation-codes', [
            'customer_group_id' => $this->customerGroup->id,
            'code' => 'CUSTOM01',
            'max_uses' => 10,
            'description' => 'Test invitation code',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.code', 'CUSTOM01')
            ->assertJsonPath('data.max_uses', 10)
            ->assertJsonPath('data.description', 'Test invitation code');
    }

    public function test_can_show_invitation_code(): void
    {
        $code = InvitationCode::create([
            'customer_group_id' => $this->customerGroup->id,
            'code' => 'SHOWTEST',
        ]);

        $response = $this->getJson("/api/invitation-codes/{$code->id}");

        $response->assertOk()
            ->assertJsonPath('data.code', 'SHOWTEST');
    }

    public function test_can_update_invitation_code(): void
    {
        $code = InvitationCode::create([
            'customer_group_id' => $this->customerGroup->id,
            'code' => 'UPDATETEST',
        ]);

        $response = $this->putJson("/api/invitation-codes/{$code->id}", [
            'customer_group_id' => $this->customerGroup->id,
            'code' => 'UPDATED01',
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.code', 'UPDATED01')
            ->assertJsonPath('data.description', 'Updated description');
    }

    public function test_can_delete_invitation_code(): void
    {
        $code = InvitationCode::create([
            'customer_group_id' => $this->customerGroup->id,
            'code' => 'DELETEME',
        ]);

        $response = $this->deleteJson("/api/invitation-codes/{$code->id}");

        $response->assertOk();
        $this->assertSoftDeleted('invitation_codes', ['id' => $code->id]);
    }

    public function test_can_toggle_invitation_code_active_status(): void
    {
        $code = InvitationCode::create([
            'customer_group_id' => $this->customerGroup->id,
            'code' => 'TOGGLETEST',
            'is_active' => true,
        ]);

        $response = $this->patchJson("/api/invitation-codes/{$code->id}/toggle-active");

        $response->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_can_redeem_valid_invitation_code(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $code = InvitationCode::create([
            'customer_group_id' => $this->customerGroup->id,
            'code' => 'REDEEM01',
            'is_active' => true,
            'max_uses' => 10,
        ]);

        $response = $this->postJson('/api/invitation-codes/redeem', [
            'code' => 'REDEEM01',
            'user_id' => $user->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.customer_group.id', $this->customerGroup->id);

        $this->assertDatabaseHas('invitation_code_usages', [
            'invitation_code_id' => $code->id,
            'user_id' => $user->id,
        ]);

        $code->refresh();
        $this->assertEquals(1, $code->used_count);
    }

    public function test_cannot_redeem_expired_invitation_code(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'expired@example.com',
            'password' => bcrypt('password'),
        ]);

        InvitationCode::create([
            'customer_group_id' => $this->customerGroup->id,
            'code' => 'EXPIRED1',
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->postJson('/api/invitation-codes/redeem', [
            'code' => 'EXPIRED1',
            'user_id' => $user->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_redeem_used_up_invitation_code(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'usedup@example.com',
            'password' => bcrypt('password'),
        ]);

        InvitationCode::create([
            'customer_group_id' => $this->customerGroup->id,
            'code' => 'USEDUP01',
            'is_active' => true,
            'max_uses' => 1,
            'used_count' => 1,
        ]);

        $response = $this->postJson('/api/invitation-codes/redeem', [
            'code' => 'USEDUP01',
            'user_id' => $user->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_redeem_same_code_twice(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'twice@example.com',
            'password' => bcrypt('password'),
        ]);

        InvitationCode::create([
            'customer_group_id' => $this->customerGroup->id,
            'code' => 'TWICE001',
            'is_active' => true,
            'max_uses' => 10,
        ]);

        $this->postJson('/api/invitation-codes/redeem', [
            'code' => 'TWICE001',
            'user_id' => $user->id,
        ])->assertOk();

        $response = $this->postJson('/api/invitation-codes/redeem', [
            'code' => 'TWICE001',
            'user_id' => $user->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_can_batch_generate_invitation_codes(): void
    {
        $response = $this->postJson('/api/invitation-codes/batch-generate', [
            'customer_group_id' => $this->customerGroup->id,
            'count' => 5,
            'max_uses' => 1,
        ]);

        $response->assertCreated();
        $this->assertCount(5, $response->json('data'));
        $this->assertEquals(5, InvitationCode::count());
    }

    public function test_can_get_invitation_codes_by_customer_group(): void
    {
        InvitationCode::create([
            'customer_group_id' => $this->customerGroup->id,
            'code' => 'GROUP001',
        ]);

        InvitationCode::create([
            'customer_group_id' => $this->customerGroup->id,
            'code' => 'GROUP002',
        ]);

        $response = $this->getJson("/api/customer-groups/{$this->customerGroup->id}/invitation-codes");

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_can_filter_active_invitation_codes(): void
    {
        InvitationCode::create([
            'customer_group_id' => $this->customerGroup->id,
            'code' => 'ACTIVE01',
            'is_active' => true,
        ]);

        InvitationCode::create([
            'customer_group_id' => $this->customerGroup->id,
            'code' => 'INACTIV1',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/invitation-codes?active=true');

        $response->assertOk();
        $codes = collect($response->json('data'));
        $this->assertTrue($codes->every(fn ($c) => $c['is_valid'] === true));
    }

    public function test_can_search_invitation_codes(): void
    {
        InvitationCode::create([
            'customer_group_id' => $this->customerGroup->id,
            'code' => 'SEARCH01',
            'description' => 'Special code for searching',
        ]);

        $response = $this->getJson('/api/invitation-codes?search=SEARCH');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_invitation_code_validity_check(): void
    {
        $validCode = InvitationCode::create([
            'customer_group_id' => $this->customerGroup->id,
            'code' => 'VALID001',
            'is_active' => true,
            'max_uses' => 0,
        ]);

        $this->assertTrue($validCode->is_valid);
        $this->assertFalse($validCode->is_expired);
        $this->assertFalse($validCode->is_used_up);
        $this->assertNull($validCode->remaining_uses);
    }

    public function test_invitation_code_with_max_uses_remaining(): void
    {
        $code = InvitationCode::create([
            'customer_group_id' => $this->customerGroup->id,
            'code' => 'LIMIT001',
            'is_active' => true,
            'max_uses' => 5,
            'used_count' => 2,
        ]);

        $this->assertEquals(3, $code->remaining_uses);
        $this->assertEquals('2 / 5', $code->uses_display);
    }
}
