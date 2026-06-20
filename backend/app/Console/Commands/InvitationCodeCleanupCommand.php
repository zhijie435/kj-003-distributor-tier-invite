<?php

namespace App\Console\Commands;

use App\Jobs\CleanupExpiredInvitationCodesJob;
use App\Models\InvitationCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InvitationCodeCleanupCommand extends Command
{
    protected $signature = 'invitation-codes:cleanup
        {--days= : 清理多少天前过期的邀请码 (默认读取配置)}
        {--queue : 使用队列异步执行}
        {--dry-run : 仅预览不实际删除}
        {--force : 跳过确认直接执行}';

    protected $description = '清理过期邀请码';

    public function handle(): int
    {
        $days = $this->option('days') ?? (int) config('invitation-codes.expired_cleanup_days', 30);

        if ($days <= 0) {
            $this->error('清理天数必须大于0');

            return self::FAILURE;
        }

        $cutoffDate = now()->subDays($days);

        $query = InvitationCode::whereNotNull('expires_at')
            ->where('expires_at', '<', $cutoffDate);

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info("没有找到 {$days} 天前过期的邀请码");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("预览模式：将删除 {$count} 个过期邀请码（{$days} 天前过期的）");
            $this->table(
                ['ID', '邀请码', '客户分组ID', '过期时间', '使用次数'],
                (clone $query)->limit(20)->get()->map(fn ($c) => [
                    $c->id,
                    $c->code,
                    $c->customer_group_id,
                    $c->expires_at,
                    $c->used_count,
                ])->toArray()
            );

            if ($count > 20) {
                $this->line("... 还有 ".($count - 20)." 个邀请码");
            }

            return self::SUCCESS;
        }

        if ($this->option('queue')) {
            CleanupExpiredInvitationCodesJob::dispatch($days);
            $this->info("已将清理任务加入队列，将清理 {$count} 个过期邀请码");

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            if (! $this->confirm("确定要删除 {$count} 个 {$days} 天前过期的邀请码吗？", false)) {
                $this->info('操作已取消');

                return self::SUCCESS;
            }
        }

        $deleted = 0;
        (clone $query)->chunkById(100, function ($codes) use (&$deleted) {
            DB::transaction(function () use ($codes, &$deleted) {
                foreach ($codes as $code) {
                    $code->delete();
                    $deleted++;
                }
            });
        });

        $this->info("成功清理 {$deleted} 个过期邀请码");

        return self::SUCCESS;
    }
}
