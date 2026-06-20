<?php

namespace Tests\Unit;

use App\Exceptions\InvitationCodeException;
use App\Models\CustomerGroup;
use App\Models\InvitationCode;
use App\Models\User;
use App\Policies\InvitationCodePolicy;
use App\Services\InvitationCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Tests\TestCase;

class InvitationCodeExceptionTest extends TestCase
{
    use RefreshDatabase;

    // ==================== 异常类静态工厂方法测试 ====================

    public function test_not_found_exception(): void
    {
        $exception = InvitationCodeException::notFound();

        $this->assertInstanceOf(InvitationCodeException::class, $exception);
        $this->assertEquals('邀请码不存在', $exception->getMessage());
        $this->assertEquals(InvitationCodeException::CODE_NOT_FOUND, $exception->getErrorCode());
        $this->assertEquals(404, $exception->getStatusCode());
    }

    public function test_expired_exception(): void
    {
        $exception = InvitationCodeException::expired();

        $this->assertEquals('邀请码已过期', $exception->getMessage());
        $this->assertEquals(InvitationCodeException::CODE_EXPIRED, $exception->getErrorCode());
        $this->assertEquals(422, $exception->getStatusCode());
    }

    public function test_used_up_exception(): void
    {
        $exception = InvitationCodeException::usedUp();

        $this->assertEquals('邀请码已用完', $exception->getMessage());
        $this->assertEquals(InvitationCodeException::CODE_USED_UP, $exception->getErrorCode());
        $this->assertEquals(422, $exception->getStatusCode());
    }

    public function test_inactive_exception(): void
    {
        $exception = InvitationCodeException::inactive();

        $this->assertEquals('邀请码已禁用', $exception->getMessage());
        $this->assertEquals(InvitationCodeException::CODE_INACTIVE, $exception->getErrorCode());
        $this->assertEquals(422, $exception->getStatusCode());
    }

    public function test_invalid_exception(): void
    {
        $exception = InvitationCodeException::invalid();

        $this->assertEquals('邀请码无效', $exception->getMessage());
        $this->assertEquals(InvitationCodeException::CODE_INVALID, $exception->getErrorCode());
        $this->assertEquals(422, $exception->getStatusCode());
    }

    public function test_already_used_exception(): void
    {
        $exception = InvitationCodeException::alreadyUsed();

        $this->assertEquals('您已使用过该邀请码', $exception->getMessage());
        $this->assertEquals(InvitationCodeException::CODE_ALREADY_USED, $exception->getErrorCode());
        $this->assertEquals(422, $exception->getStatusCode());
    }

    public function test_apply_failed_exception(): void
    {
        $exception = InvitationCodeException::applyFailed('Custom reason');

        $this->assertEquals('Custom reason', $exception->getMessage());
        $this->assertEquals(InvitationCodeException::CODE_APPLY_FAILED, $exception->getErrorCode());
        $this->assertEquals(422, $exception->getStatusCode());
    }

    public function test_apply_failed_exception_default_message(): void
    {
        $exception = InvitationCodeException::applyFailed();

        $this->assertEquals('邀请码使用失败', $exception->getMessage());
    }

    public function test_customer_group_not_found_exception(): void
    {
        $exception = InvitationCodeException::customerGroupNotFound();

        $this->assertEquals('关联的客户分组不存在', $exception->getMessage());
        $this->assertEquals(InvitationCodeException::CUSTOMER_GROUP_NOT_FOUND, $exception->getErrorCode());
        $this->assertEquals(422, $exception->getStatusCode());
    }

    public function test_unauthorized_exception(): void
    {
        $exception = InvitationCodeException::unauthorized('创建');

        $this->assertEquals('无权执行该创建', $exception->getMessage());
        $this->assertEquals(InvitationCodeException::UNAUTHORIZED, $exception->getErrorCode());
        $this->assertEquals(403, $exception->getStatusCode());
    }

    public function test_unauthorized_exception_default_action(): void
    {
        $exception = InvitationCodeException::unauthorized();

        $this->assertEquals('无权执行该操作', $exception->getMessage());
    }

    // ==================== render() 方法测试 ====================

    public function test_render_returns_json_response(): void
    {
        $exception = InvitationCodeException::notFound();

        $response = $exception->render();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(404, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals('邀请码不存在', $data['message']);
        $this->assertEquals(InvitationCodeException::CODE_NOT_FOUND, $data['error_code']);
    }

    public function test_render_with_context(): void
    {
        $exception = new InvitationCodeException(
            'Test error',
            InvitationCodeException::CODE_INVALID,
            422,
            ['field' => 'code', 'value' => 'test']
        );

        $response = $exception->render();
        $data = $response->getData(true);

        $this->assertArrayHasKey('context', $data);
        $this->assertEquals('code', $data['context']['field']);
    }

    public function test_render_with_debug_info(): void
    {
        $previous = new \RuntimeException('Previous error');
        $exception = new InvitationCodeException(
            'With previous',
            InvitationCodeException::CODE_APPLY_FAILED,
            422,
            [],
            $previous
        );

        config(['app.debug' => true]);

        $response = $exception->render();
        $data = $response->getData(true);

        $this->assertArrayHasKey('debug', $data);
        $this->assertArrayHasKey('trace', $data['debug']);
    }

    public function test_render_without_debug_info_when_not_in_debug_mode(): void
    {
        $previous = new \RuntimeException('Previous error');
        $exception = new InvitationCodeException(
            'With previous',
            InvitationCodeException::CODE_APPLY_FAILED,
            422,
            [],
            $previous
        );

        config(['app.debug' => false]);

        $response = $exception->render();
        $data = $response->getData(true);

        $this->assertArrayNotHasKey('debug', $data);
    }

    // ==================== 异常上下文和状态码获取测试 ====================

    public function test_get_context_returns_array(): void
    {
        $exception = new InvitationCodeException(
            'Test',
            InvitationCodeException::CODE_INVALID,
            422,
            ['key' => 'value']
        );

        $this->assertEquals(['key' => 'value'], $exception->getContext());
    }

    public function test_get_context_returns_empty_array_by_default(): void
    {
        $exception = InvitationCodeException::notFound();
        $this->assertEmpty($exception->getContext());
    }

    // ==================== 服务层授权异常测试 ====================

    public function test_service_authorize_allows_when_gate_passes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $service = app(InvitationCodeService::class);

        $this->assertNull($service->authorize('redeem', InvitationCode::class));
    }

    public function test_service_authorize_throws_when_not_authenticated(): void
    {
        $service = app(InvitationCodeService::class);

        $this->expectException(InvitationCodeException::class);
        $this->expectExceptionMessage('无权执行该查看列表');

        $service->authorize('viewAny', InvitationCode::class);
    }

    public function test_service_authorize_throws_with_correct_action_message(): void
    {
        $service = app(InvitationCodeService::class);

        try {
            $service->authorize('batchGenerate', InvitationCode::class);
        } catch (InvitationCodeException $e) {
            $this->assertEquals('无权执行该批量生成', $e->getMessage());
            $this->assertEquals(InvitationCodeException::UNAUTHORIZED, $e->getErrorCode());
            return;
        }

        $this->fail('Expected exception was not thrown');
    }

    public function test_service_authorize_uses_default_action_for_unknown_ability(): void
    {
        $service = app(InvitationCodeService::class);

        try {
            $service->authorize('unknownAbility', InvitationCode::class);
        } catch (InvitationCodeException $e) {
            $this->assertEquals('无权执行该unknownAbility', $e->getMessage());
            return;
        }

        $this->fail('Expected exception was not thrown');
    }

    // ==================== 兑换异常分支测试 ====================

    public function test_redeem_throws_not_found_for_nonexistent_code(): void
    {
        $user = User::factory()->create();
        $service = app(InvitationCodeService::class);

        $this->expectException(InvitationCodeException::class);
        $this->expectExceptionMessage('邀请码不存在');

        $service->redeem('NONEXISTENT', $user);
    }

    public function test_redeem_throws_already_used_on_duplicate_entry(): void
    {
        $code = InvitationCode::factory()->create();
        $user = User::factory()->create();
        $service = app(InvitationCodeService::class);

        $service->redeem($code->code, $user);

        $this->expectException(InvitationCodeException::class);
        $this->expectExceptionMessage('您已使用过该邀请码');

        $service->redeem($code->code, $user);
    }

    public function test_validate_code_handles_deleted_customer_group(): void
    {
        $group = CustomerGroup::factory()->create();
        $code = InvitationCode::factory()->forGroup($group)->create();
        $group->delete();

        $service = app(InvitationCodeService::class);
        $result = $service->validateCode($code->code);

        $this->assertFalse($result['valid']);
        $this->assertEquals(InvitationCodeException::CUSTOMER_GROUP_NOT_FOUND, $result['error_code']);
    }

    // ==================== 错误码常量测试 ====================

    public function test_error_code_constants_are_unique(): void
    {
        $codes = [
            InvitationCodeException::CODE_NOT_FOUND,
            InvitationCodeException::CODE_EXPIRED,
            InvitationCodeException::CODE_USED_UP,
            InvitationCodeException::CODE_INACTIVE,
            InvitationCodeException::CODE_INVALID,
            InvitationCodeException::CODE_ALREADY_USED,
            InvitationCodeException::CODE_APPLY_FAILED,
            InvitationCodeException::CUSTOMER_GROUP_NOT_FOUND,
            InvitationCodeException::UNAUTHORIZED,
            InvitationCodeException::VALIDATION_ERROR,
        ];

        $this->assertCount(count($codes), array_unique($codes));
    }
}
