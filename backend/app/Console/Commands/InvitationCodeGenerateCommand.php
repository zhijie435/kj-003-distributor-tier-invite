<?php

namespace App\Console\Commands;

use App\Jobs\BatchGenerateInvitationCodesJob;
use App\Models\CustomerGroup;
use App\Services\InvitationCodeService;
use Illuminate\Console\Command;

class InvitationCodeGenerateCommand extends Command
{
    protected $signature = 'invitation-codes:generate
        {customer-group : 客户分组ID或代码}
        {count=1 : 生成数量 (默认1个)}
        {--code= : 自定义邀请码 (仅单个生成时可用)}
        {--length= : 邀请码长度}
        {--max-uses=0 : 最大使用次数 (0表示不限)}
        {--expires-days= : 多少天后过期}
        {--expires-at= : 指定过期时间 (Y-m-d H:i:s)}
        {--description= : 描述}
        {--queue : 使用队列异步生成}
        {--output=table : 输出格式 (table/json/codes)}';

    protected $description = '生成邀请码';

    public function handle(InvitationCodeService $service): int
    {
        $customerGroupIdentifier = $this->argument('customer-group');
        $count = (int) $this->argument('count');

        $customerGroup = CustomerGroup::where('id', $customerGroupIdentifier)
            ->orWhere('code', $customerGroupIdentifier)
            ->first();

        if (! $customerGroup) {
            $this->error("客户分组不存在: {$customerGroupIdentifier}");

            return self::FAILURE;
        }

        if ($count > 1 && $this->option('code')) {
            $this->error('批量生成时不能指定自定义邀请码');

            return self::FAILURE;
        }

        $data = [
            'customer_group_id' => $customerGroup->id,
            'max_uses' => (int) $this->option('max-uses'),
            'description' => $this->option('description'),
        ];

        if ($this->option('length')) {
            $data['code_length'] = (int) $this->option('length');
        }

        if ($this->option('code')) {
            $data['code'] = $this->option('code');
        }

        if ($this->option('expires-at')) {
            $data['expires_at'] = $this->option('expires-at');
        } elseif ($this->option('expires-days')) {
            $data['expires_at'] = now()->addDays((int) $this->option('expires-days'));
        }

        if ($this->option('queue') && config('invitation-codes.queue_enabled')) {
            $queueData = collect($data)->only([
                'code_length', 'description', 'max_uses', 'expires_at',
            ])->toArray();

            BatchGenerateInvitationCodesJob::dispatch(
                $customerGroup->id,
                $count,
                $queueData
            );

            $this->info("已将 {$count} 个邀请码加入队列，客户分组: {$customerGroup->name}");

            return self::SUCCESS;
        }

        if ($count === 1) {
            $code = $service->create($data);
            $this->outputCode($code, $this->option('output'));

            return self::SUCCESS;
        }

        $batchData = array_merge($data, ['count' => $count]);
        $codes = $service->batchGenerate($batchData);

        $this->outputCodes($codes, $this->option('output'));

        return self::SUCCESS;
    }

    protected function outputCode($code, string $format): void
    {
        if ($format === 'codes') {
            $this->line($code->code);

            return;
        }

        if ($format === 'json') {
            $this->line(json_encode([
                'id' => $code->id,
                'code' => $code->code,
                'customer_group' => $code->customerGroup ? [
                    'id' => $code->customerGroup->id,
                    'name' => $code->customerGroup->name,
                    'code' => $code->customerGroup->code,
                ] : null,
                'max_uses' => $code->max_uses,
                'expires_at' => $code->expires_at,
                'is_active' => $code->is_active,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return;
        }

        $this->info('邀请码创建成功:');
        $this->table(
            ['字段', '值'],
            [
                ['ID', $code->id],
                ['邀请码', $code->code],
                ['客户分组', $code->customerGroup->name ?? '-'],
                ['最大使用次数', $code->is_unlimited ? '不限' : $code->max_uses],
                ['过期时间', $code->expires_at ?? '永不过期'],
                ['状态', $code->status_label],
            ]
        );
    }

    protected function outputCodes($codes, string $format): void
    {
        if ($format === 'codes') {
            $codes->each(fn ($c) => $this->line($c->code));

            return;
        }

        if ($format === 'json') {
            $this->line(json_encode(
                $codes->map(fn ($c) => [
                    'id' => $c->id,
                    'code' => $c->code,
                ])->toArray(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            ));

            return;
        }

        $this->info("成功生成 {$codes->count()} 个邀请码:");
        $this->table(
            ['ID', '邀请码', '客户分组', '最大使用次数', '过期时间'],
            $codes->map(fn ($c) => [
                $c->id,
                $c->code,
                $c->customerGroup->name ?? '-',
                $c->is_unlimited ? '不限' : $c->max_uses,
                $c->expires_at ?? '永不过期',
            ])->toArray()
        );
    }
}
