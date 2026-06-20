<?php

namespace Tests\Unit;

use App\Exceptions\InvitationCodeException;
use App\Models\CustomerGroup;
use App\Models\InvitationCode;
use App\Models\User;
use App\Services\InvitationCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InvitationCodeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InvitationCodeService::class);
    }

    // ==================== 查询方法测试 ====================

    public function test_get_query_returns_builder(): void
    {
        $query = $this->service->getQuery();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, $query);
    }

    public function test_get_query_with_customer_group_filter(): void
    {
        $group1 = CustomerGroup::factory()->create();
        $group2 = CustomerGroup::factory()->create();
        InvitationCode::factory()->forGroup($group1)->count(3)->create();
        InvitationCode::factory()->forGroup($group2)->count(2)->create();

        $query = $this->service->getQuery(['customer_group_id' => $group1->id]);
        $this->assertEquals(3, $query->count());
    }

    public function test_get_query_with_active_filter(): void
    {
        InvitationCode::factory()->count(2)->create();
        InvitationCode::factory()->inactive()->count(3)->create();

        $query = $this->service->getQuery(['active' => true]);
        $this->assertEquals(2, $query->count());
    }

    public function test_get_query_with_valid_filter(): void
    {
        InvitationCode::factory()->count(2)->create();
        InvitationCode::factory()->expired()->count(1)->create();
        InvitationCode::factory()->usedUp(5)->count(1)->create();

        $query = $this->service->getQuery(['is_valid' => true]);
        $this->assertEquals(2, $query->count());
    }

    public function test_get_query_with_search_filter(): void
    {
        InvitationCode::factory()->withCustomCode('ABC12345')->create();
        InvitationCode::factory()->count(3)->create();

        $query = $this->service->getQuery(['search' => 'ABC123']);
        $this->assertEquals(1, $query->count());
    }

    public function test_get_query_with_include_trashed_filter(): void
    {
        $code = InvitationCode::factory()->create();
        $code->delete();
        InvitationCode::factory()->count(2)->create();

        $query = $this->service->getQuery(['include_trashed' => true]);
        $this->assertEquals(3, $query->count());
    }

    public function test_get_query_with_only_trashed_filter(): void
    {
        $code = InvitationCode::factory()->create();
        $code->delete();
        InvitationCode::factory()->count(2)->create();

        $query = $this->service->getQuery(['only_trashed' => true]);
        $this->assertEquals(1, $query->count());
    }

    public function test_paginate_returns_paginator(): void
    {
        $group = CustomerGroup::factory()->create();
        InvitationCode::factory()->forGroup($group)->count(25)->create();

        $paginator = $this->service->paginate([], 10, 1);
        $this->assertEquals(25, $paginator->total());
        $this->assertEquals(10, $paginator->perPage());
        $this->assertEquals(1, $paginator->currentPage());
        $this->assertCount(10, $paginator->items());
    }

    public function test_get_by_customer_group(): void
    {
        $group = CustomerGroup::factory()->create();
        InvitationCode::factory()->forGroup($group)->count(5)->create();
        InvitationCode::factory()->count(3)->create();

        $codes = $this->service->getByCustomerGroup($group);
        $this->assertCount(5, $codes);
        $codes->each(fn ($code) => $this->assertEquals($group->id, $code->customer_group_id));
    }

    public function test_find_by_id_returns_model_or_null(): void
    {
        $code = InvitationCode::factory()->create();
        $found = $this->service->findById($code->id);
        $this->assertInstanceOf(InvitationCode::class, $found);
        $this->assertEquals($code->id, $found->id);

        $this->assertNull($this->service->findById(999999));
    }

    public function test_find_by_code_handles_case_and_whitespace(): void
    {
        InvitationCode::factory()->withCustomCode('TESTCODE')->create();

        $found = $this->service->findByCode('testcode');
        $this->assertNotNull($found);

        $found = $this->service->findByCode('  testcode  ');
        $this->assertNotNull($found);

        $this->assertNull($this->service->findByCode('NONEXISTENT'));
    }

    public function test_get_by_code_or_fail_throws_on_not_found(): void
    {
        $this->expectException(InvitationCodeException::class);
        $this->expectExceptionCode(0);

        $this->service->getByCodeOrFail('NONEXISTENT');
    }

    // ==================== 创建方法测试 ====================

    public function test_create_invitation_code(): void
    {
        $group = CustomerGroup::factory()->create();

        $code = $this->service->create([
            'customer_group_id' => $group->id,
            'description' => 'Test code',
            'max_uses' => 100,
            'is_active' => true,
        ]);

        $this->assertInstanceOf(InvitationCode::class, $code);
        $this->assertEquals($group->id, $code->customer_group_id);
        $this->assertEquals(100, $code->max_uses);
        $this->assertTrue($code->is_active);
        $this->assertNotNull($code->code);
        $this->assertEquals(8, strlen($code->code));
    }

    public function test_create_with_custom_code(): void
    {
        $group = CustomerGroup::factory()->create();

        $code = $this->service->create([
            'customer_group_id' => $group->id,
            'code' => 'MYCODE123',
            'max_uses' => 0,
        ]);

        $this->assertEquals('MYCODE123', $code->code);
    }

    public function test_create_with_code_length(): void
    {
        $group = CustomerGroup::factory()->create();

        $code = $this->service->create([
            'customer_group_id' => $group->id,
            'code_length' => 12,
            'max_uses' => 0,
        ]);

        $this->assertEquals(12, strlen($code->code));
    }

    public function test_create_default_values(): void
    {
        $group = CustomerGroup::factory()->create();

        $code = $this->service->create([
            'customer_group_id' => $group->id,
        ]);

        $this->assertEquals(InvitationCode::UNLIMITED_USES, $code->max_uses);
        $this->assertTrue($code->is_active);
    }

    public function test_create_throws_exception_for_nonexistent_customer_group(): void
    {
        $this->expectException(InvitationCodeException::class);

        $this->service->create([
            'customer_group_id' => 999999,
        ]);
    }

    // ==================== 更新方法测试 ====================

    public function test_update_invitation_code(): void
    {
        $code = InvitationCode::factory()->create();
        $newGroup = CustomerGroup::factory()->create();

        $updated = $this->service->update($code, [
            'customer_group_id' => $newGroup->id,
            'description' => 'Updated description',
            'max_uses' => 50,
            'is_active' => false,
        ]);

        $this->assertEquals($newGroup->id, $updated->customer_group_id);
        $this->assertEquals('Updated description', $updated->description);
        $this->assertEquals(50, $updated->max_uses);
        $this->assertFalse($updated->is_active);
    }

    public function test_update_with_nonexistent_customer_group(): void
    {
        $code = InvitationCode::factory()->create();

        $this->expectException(InvitationCodeException::class);

        $this->service->update($code, [
            'customer_group_id' => 999999,
        ]);
    }

    // ==================== 删除和恢复测试 ====================

    public function test_delete_invitation_code(): void
    {
        $code = InvitationCode::factory()->create();

        $this->service->delete($code);

        $this->assertSoftDeleted($code);
    }

    public function test_restore_deleted_invitation_code(): void
    {
        $code = InvitationCode::factory()->create();
        $code->delete();
        $this->assertSoftDeleted($code);

        $restored = $this->service->restore($code->id);
        $this->assertFalse($restored->trashed());
    }

    public function test_restore_nonexistent_code_throws_exception(): void
    {
        $this->expectException(InvitationCodeException::class);

        $this->service->restore(999999);
    }

    // ==================== 切换状态测试 ====================

    public function test_toggle_active(): void
    {
        $code = InvitationCode::factory()->create(['is_active' => true]);

        $toggled = $this->service->toggleActive($code);
        $this->assertFalse($toggled->is_active);

        $toggled = $this->service->toggleActive($toggled);
        $this->assertTrue($toggled->is_active);
    }

    // ==================== 批量生成测试 ====================

    public function test_batch_generate_codes(): void
    {
        $group = CustomerGroup::factory()->create();

        $codes = $this->service->batchGenerate([
            'customer_group_id' => $group->id,
            'count' => 5,
            'code_length' => 10,
            'max_uses' => 10,
            'description' => 'Batch test',
        ]);

        $this->assertCount(5, $codes);
        $codes->each(function ($code) use ($group) {
            $this->assertEquals($group->id, $code->customer_group_id);
            $this->assertEquals(10, strlen($code->code));
            $this->assertEquals(10, $code->max_uses);
            $this->assertTrue($code->is_active);
        });
    }

    public function test_batch_generate_defaults(): void
    {
        $group = CustomerGroup::factory()->create();

        $codes = $this->service->batchGenerate([
            'customer_group_id' => $group->id,
            'count' => 3,
        ]);

        $this->assertCount(3, $codes);
        $codes->each(function ($code) {
            $this->assertEquals(InvitationCode::DEFAULT_CODE_LENGTH, strlen($code->code));
            $this->assertEquals(InvitationCode::UNLIMITED_USES, $code->max_uses);
        });
    }

    public function test_batch_generate_throws_for_nonexistent_group(): void
    {
        $this->expectException(InvitationCodeException::class);

        $this->service->batchGenerate([
            'customer_group_id' => 999999,
            'count' => 3,
        ]);
    }

    // ==================== 兑换邀请码测试 ====================

    public function test_redeem_invitation_code(): void
    {
        $code = InvitationCode::factory()->create();
        $user = User::factory()->create();

        $result = $this->service->redeem($code->code, $user);

        $this->assertArrayHasKey('invitation_code', $result);
        $this->assertArrayHasKey('customer_group', $result);
        $this->assertEquals(1, $code->fresh()->used_count);
        $this->assertTrue($user->customerGroups->contains($code->customer_group_id));
    }

    public function test_redeem_case_insensitive(): void
    {
        $code = InvitationCode::factory()->withCustomCode('LOWERCASE')->create();
        $user = User::factory()->create();

        $result = $this->service->redeem('lowercase', $user);
        $this->assertNotNull($result);
    }

    public function test_redeem_already_used_by_user(): void
    {
        $code = InvitationCode::factory()->create();
        $user = User::factory()->create();

        $this->service->redeem($code->code, $user);

        $this->expectException(InvitationCodeException::class);
        $this->expectExceptionMessage('您已使用过该邀请码');

        $this->service->redeem($code->code, $user);
    }

    public function test_redeem_expired_code(): void
    {
        $code = InvitationCode::factory()->expired()->create();
        $user = User::factory()->create();

        $this->expectException(InvitationCodeException::class);
        $this->expectExceptionMessage('邀请码已过期');

        $this->service->redeem($code->code, $user);
    }

    public function test_redeem_used_up_code(): void
    {
        $code = InvitationCode::factory()->usedUp(1)->create();
        $user = User::factory()->create();

        $this->expectException(InvitationCodeException::class);
        $this->expectExceptionMessage('邀请码已用完');

        $this->service->redeem($code->code, $user);
    }

    public function test_redeem_inactive_code(): void
    {
        $code = InvitationCode::factory()->inactive()->create();
        $user = User::factory()->create();

        $this->expectException(InvitationCodeException::class);
        $this->expectExceptionMessage('邀请码已禁用');

        $this->service->redeem($code->code, $user);
    }

    public function test_redeem_deleted_code(): void
    {
        $code = InvitationCode::factory()->create();
        $code->delete();
        $user = User::factory()->create();

        $this->expectException(InvitationCodeException::class);

        $this->service->redeem($code->code, $user);
    }

    public function test_redeem_nonexistent_code(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvitationCodeException::class);

        $this->service->redeem('NONEXISTENT', $user);
    }

    // ==================== 验证邀请码测试 ====================

    public function test_validate_code_valid(): void
    {
        $code = InvitationCode::factory()->create();

        $result = $this->service->validateCode($code->code);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['error_code']);
        $this->assertNull($result['error_message']);
        $this->assertArrayHasKey('invitation_code', $result);
    }

    public function test_validate_code_expired(): void
    {
        $code = InvitationCode::factory()->expired()->create();

        $result = $this->service->validateCode($code->code);

        $this->assertFalse($result['valid']);
        $this->assertEquals(InvitationCodeException::CODE_EXPIRED, $result['error_code']);
        $this->assertEquals('邀请码已过期', $result['error_message']);
    }

    public function test_validate_code_used_up(): void
    {
        $code = InvitationCode::factory()->usedUp(5)->create();

        $result = $this->service->validateCode($code->code);

        $this->assertFalse($result['valid']);
        $this->assertEquals(InvitationCodeException::CODE_USED_UP, $result['error_code']);
        $this->assertEquals('邀请码已用完', $result['error_message']);
    }

    public function test_validate_code_inactive(): void
    {
        $code = InvitationCode::factory()->inactive()->create();

        $result = $this->service->validateCode($code->code);

        $this->assertFalse($result['valid']);
        $this->assertEquals(InvitationCodeException::CODE_INACTIVE, $result['error_code']);
        $this->assertEquals('邀请码已禁用', $result['error_message']);
    }

    public function test_validate_code_not_found(): void
    {
        $this->expectException(InvitationCodeException::class);

        $this->service->validateCode('NONEXISTENT');
    }

    // ==================== 格式化方法测试 ====================

    public function test_format_invitation_code(): void
    {
        $code = InvitationCode::factory()->create();

        $formatted = $this->service->formatInvitationCode($code);

        $this->assertArrayHasKey('id', $formatted);
        $this->assertArrayHasKey('code', $formatted);
        $this->assertArrayHasKey('customer_group', $formatted);
        $this->assertArrayHasKey('description', $formatted);
        $this->assertArrayHasKey('max_uses', $formatted);
        $this->assertArrayHasKey('used_count', $formatted);
        $this->assertArrayHasKey('remaining_uses', $formatted);
        $this->assertArrayHasKey('is_active', $formatted);
        $this->assertArrayHasKey('is_valid', $formatted);
        $this->assertArrayHasKey('is_expired', $formatted);
        $this->assertArrayHasKey('is_used_up', $formatted);
        $this->assertArrayHasKey('status', $formatted);
        $this->assertArrayHasKey('status_label', $formatted);
    }

    public function test_format_usages(): void
    {
        $code = InvitationCode::factory()->create();
        $user = User::factory()->create();
        $code->usages()->create(['user_id' => $user->id]);

        $formatted = $this->service->formatUsages($code);

        $this->assertCount(1, $formatted);
        $this->assertArrayHasKey('id', $formatted[0]);
        $this->assertArrayHasKey('user', $formatted[0]);
        $this->assertArrayHasKey('created_at', $formatted[0]);
    }

    // ==================== ensureCustomerGroupExists 测试 ====================

    public function test_ensure_customer_group_exists(): void
    {
        $group = CustomerGroup::factory()->create();

        $this->assertNull($this->service->ensureCustomerGroupExists($group->id));
    }

    public function test_ensure_customer_group_exists_throws_on_missing(): void
    {
        $this->expectException(InvitationCodeException::class);

        $this->service->ensureCustomerGroupExists(999999);
    }
}
