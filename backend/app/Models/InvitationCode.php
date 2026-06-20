<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable(['code', 'customer_group_id', 'description', 'max_uses', 'used_count', 'expires_at', 'is_active'])]
class InvitationCode extends Model
{
    use HasFactory, SoftDeletes;

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'max_uses' => 'integer',
        'used_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (InvitationCode $invitationCode) {
            if (empty($invitationCode->code)) {
                $invitationCode->code = self::generateCode();
            }
        });
    }

    public static function generateCode(int $length = 8): string
    {
        do {
            $code = strtoupper(Str::random($length));
        } while (self::withTrashed()->where('code', $code)->exists());

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

    public function scopeForGroup($query, $customerGroupId)
    {
        return $query->where('customer_group_id', $customerGroupId);
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

    public function getIsValidAttribute(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses > 0 && $this->used_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getIsUsedUpAttribute(): bool
    {
        return $this->max_uses > 0 && $this->used_count >= $this->max_uses;
    }

    public function getRemainingUsesAttribute(): ?int
    {
        if ($this->max_uses <= 0) {
            return null;
        }

        return max(0, $this->max_uses - $this->used_count);
    }

    public function getUsesDisplayAttribute(): string
    {
        if ($this->max_uses <= 0) {
            return "{$this->used_count} / 不限";
        }

        return "{$this->used_count} / {$this->max_uses}";
    }

    public function apply(User $user): bool
    {
        if (! $this->is_valid) {
            return false;
        }

        if ($this->usages()->where('user_id', $user->id)->exists()) {
            return false;
        }

        $this->usages()->create(['user_id' => $user->id]);
        $this->increment('used_count');

        $customerGroup = CustomerGroup::find($this->customer_group_id);
        if ($customerGroup) {
            $customerGroup->models()->syncWithoutDetaching([$user->id]);
        }

        return true;
    }
}
