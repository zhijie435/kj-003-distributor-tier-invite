<?php

namespace App\Jobs;

use App\Models\InvitationCode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CleanupExpiredInvitationCodesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    protected int $days;

    public function __construct(?int $days = null)
    {
        $this->days = $days ?? (int) config('invitation-codes.expired_cleanup_days', 30);

        $queueName = config('invitation-codes.queue', 'invitation-codes');
        if ($queueName) {
            $this->onQueue($queueName);
        }
    }

    public function handle(): void
    {
        if ($this->days <= 0) {
            Log::info('跳过过期邀请码清理：未配置清理天数');

            return;
        }

        $cutoffDate = now()->subDays($this->days);

        Log::info('开始清理过期邀请码', [
            'days' => $this->days,
            'cutoff_date' => $cutoffDate->toDateTimeString(),
        ]);

        $deletedCount = 0;
        $chunkSize = 100;

        try {
            InvitationCode::whereNotNull('expires_at')
                ->where('expires_at', '<', $cutoffDate)
                ->chunkById($chunkSize, function ($codes) use (&$deletedCount) {
                    DB::transaction(function () use ($codes, &$deletedCount) {
                        foreach ($codes as $code) {
                            $code->delete();
                            $deletedCount++;
                        }
                    });

                    Log::info('过期邀请码清理进度', [
                        'deleted_count' => $deletedCount,
                    ]);
                });

            Log::info('过期邀请码清理完成', [
                'days' => $this->days,
                'total_deleted' => $deletedCount,
            ]);
        } catch (Throwable $e) {
            Log::error('过期邀请码清理异常', [
                'days' => $this->days,
                'deleted_count' => $deletedCount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('过期邀请码清理任务最终失败', [
            'days' => $this->days,
            'error' => $exception->getMessage(),
        ]);
    }
}
