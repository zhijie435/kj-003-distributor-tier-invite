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

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'sanctum');
    }

    // ==================== 列表接口测试 ====================

    public function test_can_get_invitation_codes_index(): void
    {
        $group = CustomerGroup::factory()->create();
        InvitationCode::factory()->forGroup($group)->count(5)->create();

        $response = $this->getJson('/api/invitation-codes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'pagination' => ['total', 'per_page', 'current_page', 'last_page'],
            ])
            ->assertJsonCount(5, 'data');
    }

    public function test_index_with_customer_group_filter(): void
    {
        $group1 = CustomerGroup::factory()->create();
        $group2 = CustomerGroup::factory()->create();
        InvitationCode::factory()->forGroup($group1)->count(3)->create();
        InvitationCode::factory()->forGroup($group2)->count(2)->create();

        $response = $this->getJson('/api/invitation-codes?customer_group_id=' . $group1->id);

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_index_with_search_filter(): void
    {
        InvitationCode::factory()->withCustomCode('FINDME12')->create();
        InvitationCode::factory()->count(3)->create();

        $response = $this->getJson('/api/invitation-codes?search=FINDME');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_index_with_active_filter(): void
    {
        InvitationCode::factory()->count(2)->create();
        InvitationCode::factory()->inactive()->count(3)->create();

        $response = $this->getJson('/api/invitation-codes?active=1');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_index_with_status_filter_active(): void
    {
        InvitationCode::factory()->count(2)->create();
        InvitationCode::factory()->expired()->count(1)->create();

        $response = $this->getJson('/api/invitation-codes?status=active');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_index_with_status_filter_expired(): void
    {
        InvitationCode::factory()->count(2)->create();
        InvitationCode::factory()->expired()->count(3)->create();

        $response = $this->getJson('/api/invitation-codes?status=expired');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_index_with_pagination(): void
    {
        $group = CustomerGroup::factory()->create();
        InvitationCode::factory()->forGroup($group)->count(25)->create();

        $response = $this->getJson('/api/invitation-codes?per_page=10&page=2');

        $response->assertStatus(200)
            ->assertJsonPath('pagination.total', 25)
            ->assertJsonPath('pagination.per_page', 10)
            ->assertJsonPath('pagination.current_page', 2)
            ->assertJsonCount(10, 'data');
    }

    public function test_index_with_include_trashed(): void
    {
        $code = InvitationCode::factory()->create();
        $code->delete();
        InvitationCode::factory()->count(2)->create();

        $response = $this->getJson('/api/invitation-codes?include_trashed=1');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    // ==================== 创建接口测试 ====================

    public function test_can_create_invitation_code(): void
    {
        $group = CustomerGroup::factory()->create();

        $response = $this->postJson('/api/invitation-codes', [
            'customer_group_id' => $group->id,
            'description' => 'Test API code',
            'max_uses' => 10,
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', '邀请码创建成功')
            ->assertJsonStructure(['data' => ['id', 'code', 'customer_group_id']]);

        $this->assertDatabaseHas('invitation_codes', [
            'customer_group_id' => $group->id,
            'description' => 'Test API code',
            'max_uses' => 10,
            'is_active' => true,
        ]);
    }

    public function test_create_with_custom_code(): void
    {
        $group = CustomerGroup::factory()->create();

        $response = $this->postJson('/api/invitation-codes', [
            'customer_group_id' => $group->id,
            'code' => 'API12345',
            'max_uses' => 0,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'API12345');
    }

    public function test_create_fails_without_customer_group(): void
    {
        $response = $this->postJson('/api/invitation-codes', [
            'max_uses' => 0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_group_id']);
    }

    public function test_create_fails_with_invalid_customer_group(): void
    {
        $response = $this->postJson('/api/invitation-codes', [
            'customer_group_id' => 999999,
            'max_uses' => 0,
        ]);

        $response->assertStatus(422);
    }

    public function test_create_fails_with_invalid_code(): void
    {
        $group = CustomerGroup::factory()->create();

        $response = $this->postJson('/api/invitation-codes', [
            'customer_group_id' => $group->id,
            'code' => 'lowercase!',
            'max_uses' => 0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_create_fails_with_duplicate_code(): void
    {
        $group = CustomerGroup::factory()->create();
        InvitationCode::factory()->forGroup($group)->withCustomCode('DUPLICATE')->create();

        $response = $this->postJson('/api/invitation-codes', [
            'customer_group_id' => $group->id,
            'code' => 'DUPLICATE',
            'max_uses' => 0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_create_fails_with_past_expiration(): void
    {
        $group = CustomerGroup::factory()->create();

        $response = $this->postJson('/api/invitation-codes', [
            'customer_group_id' => $group->id,
            'max_uses' => 0,
            'expires_at' => now()->subDay()->format('Y-m-d H:i:s'),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['expires_at']);
    }

    public function test_create_fails_with_negative_max_uses(): void
    {
        $group = CustomerGroup::factory()->create();

        $response = $this->postJson('/api/invitation-codes', [
            'customer_group_id' => $group->id,
            'max_uses' => -1,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['max_uses']);
    }

    // ==================== 详情接口测试 ====================

    public function test_can_show_invitation_code(): void
    {
        $code = InvitationCode::factory()->create();

        $response = $this->getJson('/api/invitation-codes/' . $code->id);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'code', 'usages']])
            ->assertJsonPath('data.id', $code->id);
    }

    public function test_show_with_usages(): void
    {
        $code = InvitationCode::factory()->create();
        $user = User::factory()->create();
        $code->usages()->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/invitation-codes/' . $code->id);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.usages');
    }

    public function test_show_not_found(): void
    {
        $response = $this->getJson('/api/invitation-codes/999999');

        $response->assertStatus(404);
    }

    // ==================== 更新接口测试 ====================

    public function test_can_update_invitation_code(): void
    {
        $code = InvitationCode::factory()->create();
        $newGroup = CustomerGroup::factory()->create();

        $response = $this->putJson('/api/invitation-codes/' . $code->id, [
            'customer_group_id' => $newGroup->id,
            'description' => 'Updated via API',
            'max_uses' => 50,
            'is_active' => false,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', '邀请码更新成功')
            ->assertJsonPath('data.description', 'Updated via API')
            ->assertJsonPath('data.max_uses', 50)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('invitation_codes', [
            'id' => $code->id,
            'customer_group_id' => $newGroup->id,
            'description' => 'Updated via API',
            'max_uses' => 50,
            'is_active' => false,
        ]);
    }

    public function test_update_fails_validation(): void
    {
        $code = InvitationCode::factory()->create();

        $response = $this->putJson('/api/invitation-codes/' . $code->id, [
            'customer_group_id' => 999999,
            'max_uses' => -5,
        ]);

        $response->assertStatus(422);
    }

    // ==================== 删除接口测试 ====================

    public function test_can_delete_invitation_code(): void
    {
        $code = InvitationCode::factory()->create();

        $response = $this->deleteJson('/api/invitation-codes/' . $code->id);

        $response->assertStatus(200)
            ->assertJsonPath('message', '邀请码删除成功');

        $this->assertSoftDeleted($code);
    }

    public function test_delete_not_found(): void
    {
        $response = $this->deleteJson('/api/invitation-codes/999999');

        $response->assertStatus(404);
    }

    // ==================== 切换状态接口测试 ====================

    public function test_can_toggle_active(): void
    {
        $code = InvitationCode::factory()->create(['is_active' => true]);

        $response = $this->patchJson('/api/invitation-codes/' . $code->id . '/toggle-active');

        $response->assertStatus(200)
            ->assertJsonPath('message', '邀请码状态更新成功')
            ->assertJsonPath('data.is_active', false);
    }

    // ==================== 恢复接口测试 ====================

    public function test_can_restore_deleted_code(): void
    {
        $code = InvitationCode::factory()->create();
        $code->delete();
        $this->assertSoftDeleted($code);

        $response = $this->postJson('/api/invitation-codes/' . $code->id . '/restore');

        $response->assertStatus(200)
            ->assertJsonPath('message', '邀请码恢复成功');

        $this->assertFalse($code->fresh()->trashed());
    }

    // ==================== 批量生成接口测试 ====================

    public function test_can_batch_generate_codes(): void
    {
        $group = CustomerGroup::factory()->create();

        $response = $this->postJson('/api/invitation-codes/batch-generate', [
            'customer_group_id' => $group->id,
            'count' => 5,
            'code_length' => 10,
            'max_uses' => 10,
            'description' => 'Batch test',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', '成功生成 5 个邀请码')
            ->assertJsonCount(5, 'data');
    }

    public function test_batch_generate_default_count(): void
    {
        $group = CustomerGroup::factory()->create();

        $response = $this->postJson('/api/invitation-codes/batch-generate', [
            'customer_group_id' => $group->id,
            'count' => 3,
        ]);

        $response->assertStatus(201)
            ->assertJsonCount(3, 'data');
    }

    public function test_batch_generate_fails_without_customer_group(): void
    {
        $response = $this->postJson('/api/invitation-codes/batch-generate', [
            'count' => 5,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_group_id']);
    }

    public function test_batch_generate_fails_with_invalid_count(): void
    {
        $group = CustomerGroup::factory()->create();

        $response = $this->postJson('/api/invitation-codes/batch-generate', [
            'customer_group_id' => $group->id,
            'count' => 0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['count']);
    }

    public function test_batch_generate_fails_with_count_over_100(): void
    {
        $group = CustomerGroup::factory()->create();

        $response = $this->postJson('/api/invitation-codes/batch-generate', [
            'customer_group_id' => $group->id,
            'count' => 101,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['count']);
    }

    public function test_batch_generate_fails_with_invalid_code_length(): void
    {
        $group = CustomerGroup::factory()->create();

        $response = $this->postJson('/api/invitation-codes/batch-generate', [
            'customer_group_id' => $group->id,
            'count' => 5,
            'code_length' => 3,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code_length']);
    }

    // ==================== 兑换邀请码接口测试 ====================

    public function test_can_redeem_invitation_code(): void
    {
        $code = InvitationCode::factory()->create();

        $response = $this->postJson('/api/invitation-codes/redeem', [
            'code' => $code->code,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', '邀请码使用成功')
            ->assertJsonStructure(['data' => ['invitation_code', 'customer_group']]);

        $this->assertEquals(1, $code->fresh()->used_count);
        $this->assertTrue($this->user->customerGroups->contains($code->customer_group_id));
    }

    public function test_redeem_applies_to_authenticated_user_ignoring_user_id(): void
    {
        $code = InvitationCode::factory()->create();
        $otherUser = User::factory()->create();

        $response = $this->postJson('/api/invitation-codes/redeem', [
            'code' => $code->code,
            'user_id' => $otherUser->id,
        ]);

        $response->assertStatus(200);

        $this->assertTrue($this->user->fresh()->customerGroups->contains($code->customer_group_id));
        $this->assertFalse($otherUser->fresh()->customerGroups->contains($code->customer_group_id));
    }

    public function test_redeem_fails_without_code(): void
    {
        $response = $this->postJson('/api/invitation-codes/redeem', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_redeem_nonexistent_code(): void
    {
        $response = $this->postJson('/api/invitation-codes/redeem', [
            'code' => 'NONEXISTENT',
        ]);

        $response->assertStatus(404);
    }

    public function test_redeem_expired_code(): void
    {
        $code = InvitationCode::factory()->expired()->create();

        $response = $this->postJson('/api/invitation-codes/redeem', [
            'code' => $code->code,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'CODE_EXPIRED');
    }

    public function test_redeem_already_used_code(): void
    {
        $code = InvitationCode::factory()->create();
        $code->usages()->create(['user_id' => $this->user->id]);

        $response = $this->postJson('/api/invitation-codes/redeem', [
            'code' => $code->code,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'CODE_ALREADY_USED');
    }

    // ==================== 验证邀请码接口测试 ====================

    public function test_can_validate_valid_code(): void
    {
        $code = InvitationCode::factory()->create();

        $response = $this->postJson('/api/invitation-codes/validate', [
            'code' => $code->code,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.error_code', null);
    }

    public function test_validate_expired_code(): void
    {
        $code = InvitationCode::factory()->expired()->create();

        $response = $this->postJson('/api/invitation-codes/validate', [
            'code' => $code->code,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.error_code', 'CODE_EXPIRED');
    }

    public function test_validate_used_up_code(): void
    {
        $code = InvitationCode::factory()->usedUp(5)->create();

        $response = $this->postJson('/api/invitation-codes/validate', [
            'code' => $code->code,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.error_code', 'CODE_USED_UP');
    }

    public function test_validate_inactive_code(): void
    {
        $code = InvitationCode::factory()->inactive()->create();

        $response = $this->postJson('/api/invitation-codes/validate', [
            'code' => $code->code,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.error_code', 'CODE_INACTIVE');
    }

    public function test_validate_nonexistent_code(): void
    {
        $response = $this->postJson('/api/invitation-codes/validate', [
            'code' => 'NONEXISTENT',
        ]);

        $response->assertStatus(404);
    }

    public function test_validate_fails_without_code(): void
    {
        $response = $this->postJson('/api/invitation-codes/validate', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    // ==================== 客户分组关联邀请码列表测试 ====================

    public function test_can_get_codes_by_customer_group(): void
    {
        $group = CustomerGroup::factory()->create();
        InvitationCode::factory()->forGroup($group)->count(5)->create();
        InvitationCode::factory()->count(3)->create();

        $response = $this->getJson('/api/customer-groups/' . $group->id . '/invitation-codes');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');
    }
}
