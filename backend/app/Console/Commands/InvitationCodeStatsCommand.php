<?php

namespace App\Console\Commands;

use App\Models\CustomerGroup;
use App\Models\InvitationCode;
use Illuminate\Console\Command;

class InvitationCodeStatsCommand extends Command
{
    protected $signature = 'invitation-codes:stats
        {--group= : 按客户分组ID筛选}
        {--format=table : 输出格式 (table/json)}';

    protected $description = '查看邀请码统计信息';

    public function handle(): int
    {
        $format = $this->option('format') ?? 'table';
        $groupId = $this->option('group');

        $query = InvitationCode::query();

        if ($groupId) {
            $query->where('customer_group_id', $groupId);
        }

        $total = (clone $query)->count();
        $active = (clone $query)->active()->count();
        $inactive = (clone $query)->where('is_active', false)->notExpired()->notUsedUp()->count();
        $expired = (clone $query)->expired()->count();
        $usedUp = (clone $query)->usedUp()->count();
        $deleted = InvitationCode::onlyTrashed()->when($groupId, fn ($q) => $q->where('customer_group_id', $groupId))->count();

        $totalUsedCount = (clone $query)->sum('used_count');
        $totalMaxUses = (clone $query)->where('max_uses', '>', 0)->sum('max_uses');

        $stats = [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'expired' => $expired,
            'used_up' => $usedUp,
            'deleted' => $deleted,
            'total_used_count' => $totalUsedCount,
            'total_max_uses' => $totalMaxUses,
        ];

        if ($format === 'json') {
            $this->line(json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info('===== 邀请码统计 =====');
        $this->table(
            ['指标', '数量'],
            [
                ['总数', $total],
                ['有效', $active],
                ['已禁用', $inactive],
                ['已过期', $expired],
                ['已用完', $usedUp],
                ['已删除', $deleted],
                ['累计使用次数', $totalUsedCount],
                ['限制使用总数', $totalMaxUses],
            ]
        );

        $this->newLine();
        $this->info('===== 按客户分组统计 =====');

        $groupStats = CustomerGroup::withCount([
            'invitationCodes',
            'invitationCodes as active_invitation_codes_count' => fn ($q) => $q->active(),
            'invitationCodes as expired_invitation_codes_count' => fn ($q) => $q->expired(),
        ])->ordered()->get();

        $this->table(
            ['分组名称', '分组代码', '邀请码总数', '有效', '已过期'],
            $groupStats->map(fn ($g) => [
                $g->name,
                $g->code,
                $g->invitation_codes_count,
                $g->active_invitation_codes_count,
                $g->expired_invitation_codes_count,
            ])->toArray()
        );

        return self::SUCCESS;
    }
}
