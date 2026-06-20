<?php

namespace App\Services;

use App\Exceptions\InvitationCodeException;
use App\Models\CustomerGroup;
use App\Models\InvitationCode;
use App\Models\InvitationCodeUsage;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

class InvitationCodeService
{
    public function getQuery(array $filters = []): Builder
    {
        $query = InvitationCode::with(['customerGroup' => fn ($q) => $q->withTrashed()]);

        if (! empty($filters['customer_group_id'])) {
            $query->forGroup($filters['customer_group_id']);
        }

        if (! empty($filters['status'])) {
            $this->applyStatusFilter($query, $filters['status']);
        }

        if (! empty($filters['active'])) {
            $query->active();
        }

        if (! empty($filters['is_valid'])) {
            $query->valid();
        }

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['include_trashed'])) {
            $query->withTrashed();
        }

        if (! empty($filters['only_trashed'])) {
            $query->onlyTrashed();
        }

        return $query;
    }

    protected function applyStatusFilter(Builder $query, string $status): void
    {
        match ($status) {
            InvitationCode::STATUS_ACTIVE => $query->valid(),
            InvitationCode::STATUS_INACTIVE => $query->where('is_active', false)->notExpired()->notUsedUp(),
            InvitationCode::STATUS_EXPIRED => $query->expired(),
            InvitationCode::STATUS_USED_UP => $query->usedUp(),
            InvitationCode::STATUS_DELETED => $query->onlyTrashed(),
            default => null,
        };
    }

    public function paginate(array $filters = [], int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        return $this->getQuery($filters)
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function getByCustomerGroup(CustomerGroup $customerGroup, array $filters = []): Collection
    {
        $filters['customer_group_id'] = $customerGroup->id;

        return $this->getQuery($filters)
            ->orderByDesc('created_at')
            ->get();
    }

    public function findById(int $id, array $relations = []): ?InvitationCode
    {
        return InvitationCode::with($relations)->find($id);
    }

    public function findByCode(string $code, array $relations = []): ?InvitationCode
    {
        return InvitationCode::with($relations)
            ->where('code', strtoupper(trim($code)))
            ->first();
    }

    public function getByCodeOrFail(string $code, array $relations = []): InvitationCode
    {
        $invitationCode = $this->findByCode($code, $relations);

        if (! $invitationCode) {
            throw InvitationCodeException::notFound();
        }

        return $invitationCode;
    }

    public function create(array $data): InvitationCode
    {
        $this->ensureCustomerGroupExists($data['customer_group_id']);

        $createData = collect($data)->only([
            'customer_group_id', 'code', 'description', 'max_uses',
            'expires_at', 'is_active',
        ])->toArray();

        $createData['max_uses'] = $createData['max_uses'] ?? InvitationCode::UNLIMITED_USES;
        $createData['is_active'] = $createData['is_active'] ?? true;

        if (! empty($data['code_length'])) {
            $createData['code_length'] = (int) $data['code_length'];
        }

        return DB::transaction(function () use ($createData) {
            return InvitationCode::create($createData)->load([
                'customerGroup' => fn ($q) => $q->withTrashed(),
            ]);
        });
    }

    public function update(InvitationCode $invitationCode, array $data): InvitationCode
    {
        if (! empty($data['customer_group_id'])) {
            $this->ensureCustomerGroupExists($data['customer_group_id']);
        }

        $updateData = collect($data)->only([
            'customer_group_id', 'code', 'description', 'max_uses',
            'expires_at', 'is_active',
        ])->toArray();

        DB::transaction(function () use ($invitationCode, $updateData) {
            $invitationCode->update($updateData);
        });

        return $invitationCode->fresh()->load([
            'customerGroup' => fn ($q) => $q->withTrashed(),
        ]);
    }

    public function delete(InvitationCode $invitationCode): void
    {
        DB::transaction(function () use ($invitationCode) {
            $invitationCode->delete();
        });
    }

    public function restore(int $id): InvitationCode
    {
        $invitationCode = InvitationCode::withTrashed()->find($id);

        if (! $invitationCode) {
            throw InvitationCodeException::notFound();
        }

        DB::transaction(function () use ($invitationCode) {
            $invitationCode->restore();
        });

        return $invitationCode->fresh()->load([
            'customerGroup' => fn ($q) => $q->withTrashed(),
        ]);
    }

    public function toggleActive(InvitationCode $invitationCode): InvitationCode
    {
        DB::transaction(function () use ($invitationCode) {
            $invitationCode->toggleIsActive();
        });

        return $invitationCode->fresh()->load([
            'customerGroup' => fn ($q) => $q->withTrashed(),
        ]);
    }

    public function batchGenerate(array $data): Collection
    {
        $this->ensureCustomerGroupExists($data['customer_group_id']);

        $count = (int) $data['count'];
        $codeLength = (int) ($data['code_length'] ?? InvitationCode::DEFAULT_CODE_LENGTH);

        $baseData = collect($data)->only([
            'customer_group_id', 'description', 'max_uses', 'expires_at',
        ])->toArray();
        $baseData['max_uses'] = $baseData['max_uses'] ?? InvitationCode::UNLIMITED_USES;
        $baseData['is_active'] = true;
        $baseData['code_length'] = $codeLength;

        $codes = DB::transaction(function () use ($count, $baseData) {
            $created = [];
            for ($i = 0; $i < $count; $i++) {
                $created[] = InvitationCode::create($baseData);
            }

            return collect($created)->load([
                'customerGroup' => fn ($q) => $q->withTrashed(),
            ]);
        });

        return $codes;
    }

    public function redeem(string $code, User $user): array
    {
        $invitationCode = $this->getByCodeOrFail($code, [
            'customerGroup' => fn ($q) => $q->withTrashed(),
        ]);

        try {
            DB::transaction(function () use ($invitationCode, $user) {
                $lockedCode = InvitationCode::where('id', $invitationCode->id)
                    ->lockForUpdate()
                    ->first();

                if (! $lockedCode) {
                    throw InvitationCodeException::notFound();
                }

                $lockedCode->applyTo($user);
            });
        } catch (InvitationCodeException $e) {
            throw $e;
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')
                || str_contains($e->getMessage(), 'unique')
                || str_contains($e->getMessage(), '1062')
            ) {
                throw InvitationCodeException::alreadyUsed();
            }
            throw InvitationCodeException::applyFailed($e->getMessage());
        }

        $invitationCode = $invitationCode->fresh()->load([
            'customerGroup' => fn ($q) => $q->withTrashed(),
        ]);

        return [
            'invitation_code' => $this->formatInvitationCode($invitationCode),
            'customer_group' => $invitationCode->customerGroup ? [
                'id' => $invitationCode->customerGroup->id,
                'name' => $invitationCode->customerGroup->name,
                'code' => $invitationCode->customerGroup->code,
            ] : null,
        ];
    }

    public function validateCode(string $code): array
    {
        $invitationCode = $this->getByCodeOrFail($code, [
            'customerGroup' => fn ($q) => $q->withTrashed(),
        ]);

        $valid = true;
        $errorCode = null;
        $errorMessage = null;

        try {
            switch ($invitationCode->status) {
                case InvitationCode::STATUS_EXPIRED:
                    $valid = false;
                    $errorCode = InvitationCodeException::CODE_EXPIRED;
                    $errorMessage = '邀请码已过期';
                    break;
                case InvitationCode::STATUS_USED_UP:
                    $valid = false;
                    $errorCode = InvitationCodeException::CODE_USED_UP;
                    $errorMessage = '邀请码已用完';
                    break;
                case InvitationCode::STATUS_INACTIVE:
                    $valid = false;
                    $errorCode = InvitationCodeException::CODE_INACTIVE;
                    $errorMessage = '邀请码已禁用';
                    break;
                case InvitationCode::STATUS_DELETED:
                    $valid = false;
                    $errorCode = InvitationCodeException::CODE_NOT_FOUND;
                    $errorMessage = '邀请码不存在';
                    break;
            }

            if ($valid && ! $invitationCode->customerGroup) {
                $valid = false;
                $errorCode = InvitationCodeException::CUSTOMER_GROUP_NOT_FOUND;
                $errorMessage = '关联的客户分组不存在';
            }
        } catch (Throwable $e) {
            $valid = false;
            $errorCode = InvitationCodeException::CODE_INVALID;
            $errorMessage = '邀请码无效';
        }

        return [
            'valid' => $valid,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'invitation_code' => $this->formatInvitationCode($invitationCode),
        ];
    }

    public function ensureCustomerGroupExists(int $customerGroupId): void
    {
        $exists = CustomerGroup::where('id', $customerGroupId)->exists();

        if (! $exists) {
            throw InvitationCodeException::customerGroupNotFound();
        }
    }

    public function formatInvitationCode(InvitationCode $code): array
    {
        return [
            'id' => $code->id,
            'code' => $code->code,
            'customer_group_id' => $code->customer_group_id,
            'customer_group' => $code->customerGroup ? [
                'id' => $code->customerGroup->id,
                'name' => $code->customerGroup->name,
                'code' => $code->customerGroup->code,
                'is_active' => $code->customerGroup->is_active,
                'deleted_at' => $code->customerGroup->deleted_at,
            ] : null,
            'description' => $code->description,
            'max_uses' => $code->max_uses,
            'used_count' => $code->used_count,
            'remaining_uses' => $code->remaining_uses,
            'remaining_percent' => $code->remaining_percent,
            'uses_display' => $code->uses_display,
            'expires_at' => $code->expires_at,
            'is_active' => $code->is_active,
            'is_valid' => $code->is_valid,
            'is_expired' => $code->is_expired,
            'is_used_up' => $code->is_used_up,
            'is_unlimited' => $code->is_unlimited,
            'status' => $code->status,
            'status_label' => $code->status_label,
            'deleted_at' => $code->deleted_at,
            'created_at' => $code->created_at,
            'updated_at' => $code->updated_at,
        ];
    }

    public function formatUsages(InvitationCode $code): array
    {
        return $code->usages->map(function (InvitationCodeUsage $usage) {
            return [
                'id' => $usage->id,
                'user' => $usage->user ? [
                    'id' => $usage->user->id,
                    'name' => $usage->user->name,
                    'email' => $usage->user->email,
                ] : null,
                'created_at' => $usage->created_at,
            ];
        })->toArray();
    }

    public function authorize(string $ability, $arguments = []): void
    {
        if (! Gate::allows($ability, $arguments)) {
            $actionMap = [
                'viewAny' => '查看列表',
                'view' => '查看详情',
                'create' => '创建',
                'update' => '更新',
                'delete' => '删除',
                'toggleActive' => '切换状态',
                'batchGenerate' => '批量生成',
                'redeem' => '使用',
                'restore' => '恢复',
            ];
            $action = $actionMap[$ability] ?? $ability;
            throw InvitationCodeException::unauthorized($action);
        }
    }
}
