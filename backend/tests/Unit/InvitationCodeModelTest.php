<?php

namespace Tests\Unit;

use App\Exceptions\InvitationCodeException;
use App\Models\CustomerGroup;
use App\Models\InvitationCode;
use App\Models\InvitationCodeUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationCodeModelTest extends TestCase
{
    use RefreshDatabase;

    // ==================== 常量和基本属性测试 ====================

    public function test_default_code_length_constant(): void
    {
        $this->assertEquals(8, InvitationCode::DEFAULT_CODE_LENGTH);
    }

    public function test_min_and_max_code_length_constants(): void
    {
        $this->assertEquals(4, InvitationCode::MIN_CODE_LENGTH);
        $this->assertEquals(20, InvitationCode::MAX_CODE_LENGTH);
    }

    public function test_status_constants(): void
    {
        $this->assertEquals('active', InvitationCode::STATUS_ACTIVE);
        $this->assertEquals('inactive', InvitationCode::STATUS_INACTIVE);
        $this->assertEquals('expired', InvitationCode::STATUS_EXPIRED);
        $this->assertEquals('used_up', InvitationCode::STATUS_USED_UP);
        $this->assertEquals('deleted', InvitationCode::STATUS_DELETED);
    }

    // ==================== 生成邀请码测试 ====================

    public function test_generate_code_creates_code_with_default_length(): void
    {
        $code = InvitationCode::generateCode();
        $this->assertEquals(InvitationCode::DEFAULT_CODE_LENGTH, strlen($code));
        $this->assertMatchesRegularExpression('/^[A-Z0-9]+$/', $code);
    }

    public function test_generate_code_respects_min_length(): void
    {
        $code = InvitationCode::generateCode(2);
        $this->assertEquals(InvitationCode::MIN_CODE_LENGTH, strlen($code));
    }

    public function test_generate_code_respects_max_length(): void
    {
        $code = InvitationCode::generateCode(100);
        $this->assertEquals(InvitationCode::MAX_CODE_LENGTH, strlen($code));
    }

    public function test_generate_code_returns_unique_codes(): void
    {
        $codes = [];
        for ($i = 0; $i < 20; $i++) {
            $code = InvitationCode::generateCode();
            $this->assertNotContains($code, $codes);
            $codes[] = $code;
            InvitationCode::factory()->withCustomCode($code)->create();
        }
    }

    // ==================== Creating 事件测试 ====================

    public function test_creating_event_generates_code_when_empty(): void
    {
        $group = CustomerGroup::factory()->create();
        $code = InvitationCode::create([
            'customer_group_id' => $group->id,
            'code' => '',
            'max_uses' => 0,
        ]);

        $this->assertNotNull($code->code);
        $this->assertEquals(InvitationCode::DEFAULT_CODE_LENGTH, strlen($code->code));
    }

    public function test_creating_event_respects_code_length(): void
    {
        $group = CustomerGroup::factory()->create();
        $code = InvitationCode::create([
            'customer_group_id' => $group->id,
            'code' => '',
            'max_uses' => 0,
            'code_length' => 12,
        ]);

        $this->assertEquals(12, strlen($code->code));
    }

    public function test_creating_event_preserves_existing_code(): void
    {
        $group = CustomerGroup::factory()->create();
        $code = InvitationCode::create([
            'customer_group_id' => $group->id,
            'code' => 'EXISTING',
            'max_uses' => 0,
        ]);

        $this->assertEquals('EXISTING', $code->code);
    }

    // ==================== Saving 事件测试 ====================

    public function test_saving_event_normalizes_code_to_uppercase(): void
    {
        $group = CustomerGroup::factory()->create();
        $code = InvitationCode::create([
            'customer_group_id' => $group->id,
            'code' => 'lowercase',
            'max_uses' => 0,
        ]);

        $this->assertEquals('LOWERCASE', $code->fresh()->code);
    }

    public function test_saving_event_trims_whitespace(): void
    {
        $group = CustomerGroup::factory()->create();
        $code = InvitationCode::create([
            'customer_group_id' => $group->id,
            'code' => '  PADDED  ',
            'max_uses' => 0,
        ]);

        $this->assertEquals('PADDED', $code->fresh()->code);
    }

    public function test_saving_event_removes_code_length_attribute(): void
    {
        $group = CustomerGroup::factory()->create();
        $code = InvitationCode::create([
            'customer_group_id' => $group->id,
            'code' => '',
            'max_uses' => 0,
            'code_length' => 10,
        ]);

        $this->assertFalse($code->offsetExists('code_length'));
    }

    // ==================== Scopes 测试 ====================

    public function test_scope_active_returns_active_and_not_expired(): void
    {
        InvitationCode::factory()->count(2)->create();
        InvitationCode::factory()->inactive()->count(3)->create();
        InvitationCode::factory()->expired()->count(1)->create();

        $this->assertEquals(2, InvitationCode::active()->count());
    }

    public function test_scope_not_expired(): void
    {
        InvitationCode::factory()->count(2)->create();
        InvitationCode::factory()->expired()->count(3)->create();

        $this->assertEquals(2, InvitationCode::notExpired()->count());
    }

    public function test_scope_expired(): void
    {
        InvitationCode::factory()->count(2)->create();
        InvitationCode::factory()->expired()->count(3)->create();

        $this->assertEquals(3, InvitationCode::expired()->count());
    }

    public function test_scope_used_up(): void
    {
        InvitationCode::factory()->usedUp(5)->count(2)->create();
        InvitationCode::factory()->limitedUses(5)->count(3)->create();

        $this->assertEquals(2, InvitationCode::usedUp()->count());
    }

    public function test_scope_not_used_up(): void
    {
        InvitationCode::factory()->usedUp(5)->count(2)->create();
        InvitationCode::factory()->limitedUses(5)->count(3)->create();
        InvitationCode::factory()->count(2)->create();

        $this->assertEquals(5, InvitationCode::notUsedUp()->count());
    }

    public function test_scope_valid(): void
    {
        InvitationCode::factory()->count(2)->create();
        InvitationCode::factory()->expired()->count(1)->create();
        InvitationCode::factory()->usedUp(5)->count(1)->create();
        InvitationCode::factory()->inactive()->count(1)->create();

        $this->assertEquals(2, InvitationCode::valid()->count());
    }

    public function test_scope_for_group(): void
    {
        $group1 = CustomerGroup::factory()->create();
        $group2 = CustomerGroup::factory()->create();
        InvitationCode::factory()->forGroup($group1)->count(3)->create();
        InvitationCode::factory()->forGroup($group2)->count(2)->create();

        $this->assertEquals(3, InvitationCode::forGroup($group1->id)->count());
    }

    public function test_scope_search_by_code(): void
    {
        InvitationCode::factory()->withCustomCode('SEARCH123')->create();
        InvitationCode::factory()->count(3)->create();

        $this->assertEquals(1, InvitationCode::search('SEARCH')->count());
    }

    public function test_scope_search_by_description(): void
    {
        InvitationCode::factory()->create(['description' => 'Find me please']);
        InvitationCode::factory()->count(3)->create();

        $this->assertEquals(1, InvitationCode::search('Find me')->count());
    }

    // ==================== 关系测试 ====================

    public function test_customer_group_relation(): void
    {
        $group = CustomerGroup::factory()->create();
        $code = InvitationCode::factory()->forGroup($group)->create();

        $this->assertInstanceOf(CustomerGroup::class, $code->customerGroup);
        $this->assertEquals($group->id, $code->customerGroup->id);
    }

    public function test_customer_group_relation_with_trashed(): void
    {
        $group = CustomerGroup::factory()->create();
        $code = InvitationCode::factory()->forGroup($group)->create();
        $group->delete();

        $this->assertNotNull($code->fresh()->customerGroup);
    }

    public function test_usages_relation(): void
    {
        $code = InvitationCode::factory()->create();
        $user = User::factory()->create();
        $code->usages()->create(['user_id' => $user->id]);

        $this->assertCount(1, $code->usages);
        $this->assertInstanceOf(InvitationCodeUsage::class, $code->usages->first());
    }

    public function test_users_relation(): void
    {
        $code = InvitationCode::factory()->create();
        $user = User::factory()->create();
        $code->usages()->create(['user_id' => $user->id]);

        $this->assertCount(1, $code->users);
        $this->assertInstanceOf(User::class, $code->users->first());
    }

    // ==================== 访问器测试 ====================

    public function test_status_attribute_active(): void
    {
        $code = InvitationCode::factory()->create();
        $this->assertEquals(InvitationCode::STATUS_ACTIVE, $code->status);
    }

    public function test_status_attribute_inactive(): void
    {
        $code = InvitationCode::factory()->inactive()->create();
        $this->assertEquals(InvitationCode::STATUS_INACTIVE, $code->status);
    }

    public function test_status_attribute_expired(): void
    {
        $code = InvitationCode::factory()->expired()->create();
        $this->assertEquals(InvitationCode::STATUS_EXPIRED, $code->status);
    }

    public function test_status_attribute_used_up(): void
    {
        $code = InvitationCode::factory()->usedUp(5)->create();
        $this->assertEquals(InvitationCode::STATUS_USED_UP, $code->status);
    }

    public function test_status_attribute_deleted(): void
    {
        $code = InvitationCode::factory()->create();
        $code->delete();
        $this->assertEquals(InvitationCode::STATUS_DELETED, $code->status);
    }

    public function test_status_label_attribute(): void
    {
        $code = InvitationCode::factory()->create();
        $this->assertEquals('有效', $code->status_label);

        $code = InvitationCode::factory()->inactive()->create();
        $this->assertEquals('已禁用', $code->status_label);

        $code = InvitationCode::factory()->expired()->create();
        $this->assertEquals('已过期', $code->status_label);

        $code = InvitationCode::factory()->usedUp(5)->create();
        $this->assertEquals('已用完', $code->status_label);
    }

    public function test_is_valid_attribute(): void
    {
        $code = InvitationCode::factory()->create();
        $this->assertTrue($code->is_valid);

        $code = InvitationCode::factory()->expired()->create();
        $this->assertFalse($code->is_valid);
    }

    public function test_is_expired_attribute(): void
    {
        $code = InvitationCode::factory()->create();
        $this->assertFalse($code->is_expired);

        $code = InvitationCode::factory()->expired()->create();
        $this->assertTrue($code->is_expired);
    }

    public function test_is_used_up_attribute(): void
    {
        $code = InvitationCode::factory()->create();
        $this->assertFalse($code->is_used_up);

        $code = InvitationCode::factory()->limitedUses(5)->create(['used_count' => 3]);
        $this->assertFalse($code->is_used_up);

        $code = InvitationCode::factory()->usedUp(5)->create();
        $this->assertTrue($code->is_used_up);
    }

    public function test_is_unlimited_attribute(): void
    {
        $code = InvitationCode::factory()->create();
        $this->assertTrue($code->is_unlimited);

        $code = InvitationCode::factory()->limitedUses(5)->create();
        $this->assertFalse($code->is_unlimited);
    }

    public function test_remaining_uses_attribute(): void
    {
        $code = InvitationCode::factory()->create();
        $this->assertNull($code->remaining_uses);

        $code = InvitationCode::factory()->limitedUses(10)->create(['used_count' => 3]);
        $this->assertEquals(7, $code->remaining_uses);

        $code = InvitationCode::factory()->usedUp(5)->create();
        $this->assertEquals(0, $code->remaining_uses);
    }

    public function test_uses_display_attribute(): void
    {
        $code = InvitationCode::factory()->create(['used_count' => 5]);
        $this->assertEquals('5 / 不限', $code->uses_display);

        $code = InvitationCode::factory()->limitedUses(10)->create(['used_count' => 3]);
        $this->assertEquals('3 / 10', $code->uses_display);
    }

    public function test_remaining_percent_attribute(): void
    {
        $code = InvitationCode::factory()->create();
        $this->assertNull($code->remaining_percent);

        $code = InvitationCode::factory()->limitedUses(10)->create(['used_count' => 5]);
        $this->assertEquals(50.0, $code->remaining_percent);

        $code = InvitationCode::factory()->limitedUses(10)->create(['used_count' => 10]);
        $this->assertEquals(0.0, $code->remaining_percent);
    }

    // ==================== 业务方法测试 ====================

    public function test_has_been_used_by(): void
    {
        $code = InvitationCode::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->assertFalse($code->hasBeenUsedBy($user1));

        $code->usages()->create(['user_id' => $user1->id]);
        $this->assertTrue($code->hasBeenUsedBy($user1));
        $this->assertFalse($code->hasBeenUsedBy($user2));
    }

    public function test_validate_redeemable_by_active_code(): void
    {
        $code = InvitationCode::factory()->create();
        $user = User::factory()->create();

        $this->assertNull($code->validateRedeemableBy($user));
    }

    public function test_validate_redeemable_by_expired_throws(): void
    {
        $code = InvitationCode::factory()->expired()->create();
        $user = User::factory()->create();

        $this->expectException(InvitationCodeException::class);
        $this->expectExceptionMessage('邀请码已过期');

        $code->validateRedeemableBy($user);
    }

    public function test_validate_redeemable_by_used_up_throws(): void
    {
        $code = InvitationCode::factory()->usedUp(5)->create();
        $user = User::factory()->create();

        $this->expectException(InvitationCodeException::class);
        $this->expectExceptionMessage('邀请码已用完');

        $code->validateRedeemableBy($user);
    }

    public function test_validate_redeemable_by_inactive_throws(): void
    {
        $code = InvitationCode::factory()->inactive()->create();
        $user = User::factory()->create();

        $this->expectException(InvitationCodeException::class);
        $this->expectExceptionMessage('邀请码已禁用');

        $code->validateRedeemableBy($user);
    }

    public function test_validate_redeemable_by_already_used_throws(): void
    {
        $code = InvitationCode::factory()->create();
        $user = User::factory()->create();
        $code->usages()->create(['user_id' => $user->id]);

        $this->expectException(InvitationCodeException::class);
        $this->expectExceptionMessage('您已使用过该邀请码');

        $code->validateRedeemableBy($user);
    }

    public function test_apply_to_user(): void
    {
        $code = InvitationCode::factory()->create();
        $user = User::factory()->create();

        $code->applyTo($user);

        $this->assertEquals(1, $code->fresh()->used_count);
        $this->assertTrue($code->hasBeenUsedBy($user));
        $this->assertTrue($user->customerGroups->contains($code->customer_group_id));
    }

    public function test_apply_to_user_without_customer_group(): void
    {
        $code = InvitationCode::factory()->create();
        $user = User::factory()->create();

        $code->setRelation('customerGroup', null);

        $this->expectException(InvitationCodeException::class);
        $this->expectExceptionMessage('关联的客户分组不存在');

        $code->applyTo($user);
    }

    public function test_toggle_is_active(): void
    {
        $code = InvitationCode::factory()->create(['is_active' => true]);

        $code->toggleIsActive();
        $this->assertFalse($code->fresh()->is_active);

        $code->toggleIsActive();
        $this->assertTrue($code->fresh()->is_active);
    }

    // ==================== SoftDeletes 测试 ====================

    public function test_soft_deletes(): void
    {
        $code = InvitationCode::factory()->create();
        $code->delete();

        $this->assertSoftDeleted($code);
        $this->assertNotNull($code->deleted_at);
        $this->assertEquals(0, InvitationCode::count());
        $this->assertEquals(1, InvitationCode::withTrashed()->count());
    }

    public function test_restore_soft_deleted(): void
    {
        $code = InvitationCode::factory()->create();
        $code->delete();
        $this->assertSoftDeleted($code);

        InvitationCode::withTrashed()->find($code->id)->restore();
        $this->assertFalse($code->fresh()->trashed());
    }
}
