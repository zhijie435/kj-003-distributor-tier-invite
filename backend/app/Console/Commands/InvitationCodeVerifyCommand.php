<?php

namespace App\Console\Commands;

use App\Exceptions\InvitationCodeException;
use App\Models\CustomerGroup;
use App\Models\InvitationCode;
use App\Models\User;
use App\Services\InvitationCodeService;
use Illuminate\Console\Command;

class InvitationCodeVerifyCommand extends Command
{
    protected $signature = 'invitation-codes:verify
        {--smoke : 仅运行冒烟测试}
        {--output=table : 输出格式 (table/json)}';

    protected $description = '验收邀请码功能';

    protected array $results = [];

    public function handle(InvitationCodeService $service): int
    {
        $this->info('===== 开始验收经销商分级邀请码功能 =====');
        $this->newLine();

        $this->verifyCustomerGroups();
        $this->verifyInvitationCodeCrud($service);
        $this->verifyRedeemFunction($service);
        $this->verifyBatchGenerate($service);
        $this->verifyValidation($service);

        if (! $this->option('smoke')) {
            $this->verifyEdgeCases($service);
        }

        $this->outputResults();

        $passed = collect($this->results)->where('passed', true)->count();
        $total = count($this->results);
        $failed = $total - $passed;

        $this->newLine();
        $this->info("===== 验收完成: {$passed}/{$total} 通过，{$failed} 失败 =====");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    protected function verifyCustomerGroups(): void
    {
        $this->info('[1/6] 检查经销商分级客户分组...');

        $requiredCodes = [
            config('invitation-codes.customer_groups.normal', 'NORMAL'),
            config('invitation-codes.customer_groups.silver', 'SILVER'),
            config('invitation-codes.customer_groups.gold', 'GOLD'),
            config('invitation-codes.customer_groups.diamond', 'DIAMOND'),
        ];

        $found = 0;
        foreach ($requiredCodes as $code) {
            $exists = CustomerGroup::where('code', $code)->exists();
            $this->addResult("客户分组 {$code} 存在", $exists);
            if ($exists) {
                $found++;
            }
        }

        $this->addResult('四个经销商分级分组完整', $found === 4);
    }

    protected function verifyInvitationCodeCrud(InvitationCodeService $service): void
    {
        $this->info('[2/6] 测试邀请码 CRUD...');

        $group = CustomerGroup::first();
        if (! $group) {
            $this->addResult('存在可用客户分组', false);

            return;
        }
        $this->addResult('存在可用客户分组', true);

        try {
            $code = $service->create([
                'customer_group_id' => $group->id,
                'description' => '验收测试邀请码',
                'max_uses' => 10,
            ]);
            $this->addResult('创建邀请码成功', true);
            $this->addResult('邀请码长度符合配置', strlen($code->code) >= 4 && strlen($code->code) <= 20);

            $updated = $service->update($code, [
                'description' => '验收测试邀请码-已更新',
                'max_uses' => 20,
            ]);
            $this->addResult('更新邀请码成功', $updated->description === '验收测试邀请码-已更新' && $updated->max_uses === 20);

            $found = $service->findByCode($code->code);
            $this->addResult('查询邀请码成功', $found && $found->id === $code->id);

            $service->delete($code);
            $this->addResult('软删除邀请码成功', $code->fresh()->trashed());

            $service->restore($code->id);
            $this->addResult('恢复软删除邀请码成功', ! $code->fresh()->trashed());

            $code->forceDelete();
        } catch (\Throwable $e) {
            $this->addResult('邀请码 CRUD 操作', false, $e->getMessage());
        }
    }

    protected function verifyRedeemFunction(InvitationCodeService $service): void
    {
        $this->info('[3/6] 测试邀请码兑换功能...');

        $group = CustomerGroup::first();
        $user = User::first();

        if (! $group || ! $user) {
            $this->addResult('测试兑换所需数据存在', false);

            return;
        }

        try {
            $code = $service->create([
                'customer_group_id' => $group->id,
                'max_uses' => 5,
            ]);

            $result = $service->redeem($code->code, $user);
            $this->addResult('兑换邀请码成功', isset($result['invitation_code']) && isset($result['customer_group']));

            $code = $code->fresh();
            $this->addResult('兑换后使用次数递增', $code->used_count === 1);
            $this->addResult('用户加入客户分组', $user->fresh()->customerGroups->contains($group->id));

            try {
                $service->redeem($code->code, $user);
                $this->addResult('重复兑换抛出异常', false);
            } catch (InvitationCodeException $e) {
                $this->addResult('重复兑换抛出异常', $e->getErrorCode() === InvitationCodeException::CODE_ALREADY_USED);
            }

            $code->forceDelete();
        } catch (\Throwable $e) {
            $this->addResult('兑换功能测试', false, $e->getMessage());
        }
    }

    protected function verifyBatchGenerate(InvitationCodeService $service): void
    {
        $this->info('[4/6] 测试批量生成邀请码...');

        $group = CustomerGroup::first();
        if (! $group) {
            $this->addResult('批量生成测试分组存在', false);

            return;
        }

        try {
            $codes = $service->batchGenerate([
                'customer_group_id' => $group->id,
                'count' => 5,
                'code_length' => 10,
                'max_uses' => 100,
            ]);

            $this->addResult('批量生成数量正确', $codes->count() === 5);

            $allCorrectLength = $codes->every(fn ($c) => strlen($c->code) === 10);
            $this->addResult('批量生成长度符合要求', $allCorrectLength);

            $allUnique = $codes->pluck('code')->unique()->count() === 5;
            $this->addResult('批量生成邀请码唯一', $allUnique);

            InvitationCode::whereIn('id', $codes->pluck('id'))->forceDelete();
        } catch (\Throwable $e) {
            $this->addResult('批量生成测试', false, $e->getMessage());
        }
    }

    protected function verifyValidation(InvitationCodeService $service): void
    {
        $this->info('[5/6] 测试邀请码验证功能...');

        $group = CustomerGroup::first();
        if (! $group) {
            $this->addResult('验证测试分组存在', false);

            return;
        }

        try {
            $validCode = $service->create([
                'customer_group_id' => $group->id,
                'max_uses' => 0,
            ]);
            $result = $service->validateCode($validCode->code);
            $this->addResult('验证有效邀请码返回 true', $result['valid'] === true);

            $expiredCode = InvitationCode::factory()
                ->forGroup($group)
                ->expired()
                ->create();
            $result = $service->validateCode($expiredCode->code);
            $this->addResult('验证过期邀请码返回 false', $result['valid'] === false && $result['error_code'] === InvitationCodeException::CODE_EXPIRED);

            $usedUpCode = InvitationCode::factory()
                ->forGroup($group)
                ->usedUp(5)
                ->create();
            $result = $service->validateCode($usedUpCode->code);
            $this->addResult('验证用完邀请码返回 false', $result['valid'] === false && $result['error_code'] === InvitationCodeException::CODE_USED_UP);

            $inactiveCode = InvitationCode::factory()
                ->forGroup($group)
                ->inactive()
                ->create();
            $result = $service->validateCode($inactiveCode->code);
            $this->addResult('验证禁用邀请码返回 false', $result['valid'] === false && $result['error_code'] === InvitationCodeException::CODE_INACTIVE);

            $validCode->forceDelete();
            $expiredCode->forceDelete();
            $usedUpCode->forceDelete();
            $inactiveCode->forceDelete();
        } catch (\Throwable $e) {
            $this->addResult('验证功能测试', false, $e->getMessage());
        }
    }

    protected function verifyEdgeCases(InvitationCodeService $service): void
    {
        $this->info('[6/6] 测试边界情况...');

        $group = CustomerGroup::first();
        if (! $group) {
            $this->addResult('边界测试分组存在', false);

            return;
        }

        try {
            $code1 = $service->create([
                'customer_group_id' => $group->id,
                'code' => 'lowercase',
                'max_uses' => 0,
            ]);
            $this->addResult('小写邀请码自动转大写', $code1->code === 'LOWERCASE');
            $code1->forceDelete();

            $code2 = $service->create([
                'customer_group_id' => $group->id,
                'code' => '  SPACED  ',
                'max_uses' => 0,
            ]);
            $this->addResult('邀请码自动去除空格', $code2->code === 'SPACED');
            $code2->forceDelete();

            $code3 = $service->create([
                'customer_group_id' => $group->id,
                'max_uses' => 0,
                'code_length' => 16,
            ]);
            $this->addResult('自定义长度邀请码', strlen($code3->code) === 16);
            $code3->forceDelete();

            $toggled = $service->create([
                'customer_group_id' => $group->id,
                'max_uses' => 0,
            ]);
            $service->toggleActive($toggled);
            $this->addResult('切换邀请码激活状态', $toggled->fresh()->is_active === false);
            $toggled->forceDelete();
        } catch (\Throwable $e) {
            $this->addResult('边界情况测试', false, $e->getMessage());
        }
    }

    protected function addResult(string $name, bool $passed, string $message = ''): void
    {
        $this->results[] = [
            'name' => $name,
            'passed' => $passed,
            'message' => $message,
        ];

        $status = $passed ? '✓' : '✗';
        $color = $passed ? 'info' : 'error';
        $line = "  {$status} {$name}";
        if ($message) {
            $line .= " - {$message}";
        }
        $this->{$color}($line);
    }

    protected function outputResults(): void
    {
        $format = $this->option('output');

        if ($format === 'json') {
            $this->line(json_encode($this->results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return;
        }

        $this->newLine();
        $this->info('===== 验收详情 =====');
        $this->table(
            ['测试项', '结果', '备注'],
            collect($this->results)->map(fn ($r) => [
                $r['name'],
                $r['passed'] ? '通过' : '失败',
                $r['message'],
            ])->toArray()
        );
    }
}
