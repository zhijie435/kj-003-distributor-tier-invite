<?php

namespace App\Jobs;

use App\Models\CustomerGroup;
use App\Models\InvitationCode;
use App\Services\InvitationCodeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class BatchGenerateInvitationCodesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    protected int $customerGroupId;

    protected int $count;

    protected array $options;

    protected ?int $userId;

    public function __construct(
        int $customerGroupId,
        int $count,
        array $options = [],
        ?int $userId = null
    ) {
        $this->customerGroupId = $customerGroupId;
        $this->count = $count;
        $this->options = $options;
        $this->userId = $userId;

        $queueName = config('invitation-codes.queue', 'invitation-codes');
        if ($queueName) {
            $this->onQueue($queueName);
        }
    }

    public function handle(InvitationCodeService $service): void
    {
        $customerGroup = CustomerGroup::find($this->customerGroupId);

        if (! $customerGroup) {
            Log::error('批量生成邀请码失败：客户分组不存在', [
                'customer_group_id' => $this->customerGroupId,
                'user_id' => $this->userId,
            ]);

            return;
        }

        $codeLength = (int) ($this->options['code_length'] ?? InvitationCode::DEFAULT_CODE_LENGTH);
        $maxUses = (int) ($this->options['max_uses'] ?? InvitationCode::UNLIMITED_USES);
        $description = $this->options['description'] ?? null;
        $expiresAt = $this->options['expires_at'] ?? null;

        $batchSize = 50;
        $totalCreated = 0;
        $generatedCodes = [];

        try {
            for ($offset = 0; $offset < $this->count; $offset += $batchSize) {
                $currentBatchSize = min($batchSize, $this->count - $offset);

                DB::transaction(function () use (
                    $customerGroup,
                    $currentBatchSize,
                    $codeLength,
                    $maxUses,
                    $description,
                    $expiresAt,
                    &$totalCreated,
                    &$generatedCodes
                ) {
                    for ($i = 0; $i < $currentBatchSize; $i++) {
                        $code = InvitationCode::create([
                            'customer_group_id' => $customerGroup->id,
                            'code_length' => $codeLength,
                            'description' => $description,
                            'max_uses' => $maxUses,
                            'expires_at' => $expiresAt,
                            'is_active' => true,
                        ]);

                        $generatedCodes[] = $code->code;
                        $totalCreated++;
                    }
                });

                if ($totalCreated % 100 === 0) {
                    Log::info('批量生成邀请码进度', [
                        'customer_group_id' => $customerGroup->id,
                        'customer_group_name' => $customerGroup->name,
                        'progress' => "{$totalCreated}/{$this->count}",
                        'user_id' => $this->userId,
                    ]);
                }
            }

            Log::info('批量生成邀请码完成', [
                'customer_group_id' => $customerGroup->id,
                'customer_group_name' => $customerGroup->name,
                'total_count' => $this->count,
                'created_count' => $totalCreated,
                'user_id' => $this->userId,
            ]);
        } catch (Throwable $e) {
            Log::error('批量生成邀请码异常', [
                'customer_group_id' => $this->customerGroupId,
                'count' => $this->count,
                'created_count' => $totalCreated,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $this->userId,
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('批量生成邀请码任务最终失败', [
            'customer_group_id' => $this->customerGroupId,
            'count' => $this->count,
            'error' => $exception->getMessage(),
            'user_id' => $this->userId,
        ]);
    }
}
