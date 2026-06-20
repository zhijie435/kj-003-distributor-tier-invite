<?php

namespace App\Models;

use App\Exceptions\InvitationCodeException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['code', 'customer_group_id', 'description', 'max_uses', 'used_count', 'expires_at', 'is_active'])]
class InvitationCode extends Model
{
    use HasFactory, SoftDeletes;

    public const DEFAULT_CODE_LENGTH = 8;
    public const MIN_CODE_LENGTH = 4;
    public const MAX_CODE_LENGTH = 20;

    public const UNLIMITED_USES = 0;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_USED_UP = 'used_up';
    public const STATUS_DELETED = 'deleted';

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'max_uses' => 'integer',
        'used_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (InvitationCode $invitationCode) {
            $codeLength = $invitationCode->getAttribute('code_length') ?? self::DEFAULT_CODE_LENGTH;
            if (empty($invitationCode->code)) {
                $invitationCode->code = self::generateCode($codeLength);
            }
        });

        static::saving(function (InvitationCode $invitationCode) {
            if ($invitationCode->isDirty('code')) {
                $invitationCode->code = strtoupper(trim($invitationCode->code));
            }
            if ($invitationCode->getAttribute('code_length')) {
                $invitationCode->offsetUnset('code_length');
            }
        });
    }

    public static function generateCode(int $length = self::DEFAULT_CODE_LENGTH): string
    {
        $length = max(self::MIN_CODE_LENGTH, min($length, self::MAX_CODE_LENGTH));

        $maxAttempts = 100;
        $attempt = 0;

        do {
            $attempt++;
            $code = strtoupper(Str::random($length));
        } while (self::withTrashed()->where('code', $code)->exists() && $attempt < $maxAttempts);

        if ($attempt >= $maxAttempts) {
            throw InvitationCodeException::applyFailed('邀请码生成失败，请重试或增加码长');
        }

        return $code;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    public function scopeUsedUp($query)
    {
        return $query->where('max_uses', '>', 0)
            ->whereColumn('used_count', '>=', 'max_uses');
    }

    public function scopeNotUsedUp($query)
    {
        return $query->where(function ($q) {
            $q->where('max_uses', self::UNLIMITED_USES)
                ->orWhereColumn('used_count', '<', 'max_uses');
        });
    }

    public function scopeValid($query)
    {
        return $query->active()->notUsedUp();
    }

    public function scopeForGroup($query, $customerGroupId)
    {
        return $query->where('customer_group_id', $customerGroupId);
    }

    public function scopeSearch($query, string $keyword)
    {
        return $query->where(function ($q) use ($keyword) {
            $q->where('code', 'like', "%{$keyword}%")
                ->orWhere('description', 'like', "%{$keyword}%");
        });
    }

    public function customerGroup()
    {
        return $this->belongsTo(CustomerGroup::class)->withTrashed();
    }

    public function usages()
    {
        return $this->hasMany(InvitationCodeUsage::class);
    }

    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'invitation_code_usages',
            'invitation_code_id',
            'user_id'
        )->withTimestamps();
    }

    public function getStatusAttribute(): string
    {
        if ($this->trashed()) {
            return self::STATUS_DELETED;
        }
        if ($this->is_used_up) {
            return self::STATUS_USED_UP;
        }
        if ($this->is_expired) {
            return self::STATUS_EXPIRED;
        }
        if (! $this->is_active) {
            return self::STATUS_INACTIVE;
        }

        return self::STATUS_ACTIVE;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => '有效',
            self::STATUS_INACTIVE => '已禁用',
            self::STATUS_EXPIRED => '已过期',
            self::STATUS_USED_UP => '已用完',
            self::STATUS_DELETED => '已删除',
            default => '未知',
        };
    }

    public function getIsValidAttribute(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getIsUsedUpAttribute(): bool
    {
        return $this->max_uses > 0 && $this->used_count >= $this->max_uses;
    }

    public function getIsUnlimitedAttribute(): bool
    {
        return $this->max_uses <= 0;
    }

    public function getRemainingUsesAttribute(): ?int
    {
        if ($this->is_unlimited) {
            return null;
        }

        return max(0, $this->max_uses - $this->used_count);
    }

    public function getUsesDisplayAttribute(): string
    {
        if ($this->is_unlimited) {
            return "{$this->used_count} / 不限";
        }

        return "{$this->used_count} / {$this->max_uses}";
    }

    public function getRemainingPercentAttribute(): ?float
    {
        if ($this->is_unlimited || $this->max_uses <= 0) {
            return null;
        }

        return round(($this->remaining_uses / $this->max_uses) * 100, 2);
    }

    public function hasBeenUsedBy(User $user): bool
    {
        return $this->usages()->where('user_id', $user->id)->exists();
    }

    public function validateRedeemableBy(User $user): void
    {
        switch ($this->status) {
            case self::STATUS_EXPIRED:
                throw InvitationCodeException::expired();
            case self::STATUS_USED_UP:
                throw InvitationCodeException::usedUp();
            case self::STATUS_INACTIVE:
                throw InvitationCodeException::inactive();
            case self::STATUS_DELETED:
                throw InvitationCodeException::notFound();
        }

        if (! $this->is_valid) {
            throw InvitationCodeException::invalid();
        }

        if ($this->hasBeenUsedBy($user)) {
            throw InvitationCodeException::alreadyUsed();
        }
    }

    public function applyTo(User $user): void
    {
        $this->validateRedeemableBy($user);

        $customerGroup = $this->customerGroup;
        if (! $customerGroup) {
            throw InvitationCodeException::customerGroupNotFound();
        }

        $this->usages()->create(['user_id' => $user->id]);
        $this->increment('used_count');

        $customerGroup->models()->syncWithoutDetaching([$user->id]);
    }

    public function toggleIsActive(): bool
    {
        return $this->update([
            'is_active' => ! $this->is_active,
        ]);
    }
}
