<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['product_id', 'customer_group_id', 'price'])]
class ProductCustomerGroupPrice extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_customer_group_prices';

    protected $casts = [
        'price' => 'float',
    ];

    protected $appends = [
        'formatted_price',
        'is_active',
        'status',
    ];

    protected function price(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round(floatval($value), 2),
            set: fn ($value) => round(floatval($value), 2),
        );
    }

    protected function getFormattedPriceAttribute()
    {
        return number_format($this->price, 2, '.', '');
    }

    protected function getIsActiveAttribute()
    {
        if (! $this->relationLoaded('product') || ! $this->relationLoaded('customerGroup')) {
            $this->load(['product', 'customerGroup']);
        }

        return $this->product && $this->product->is_active
            && $this->customerGroup && $this->customerGroup->is_active;
    }

    protected function getStatusAttribute()
    {
        if (! $this->relationLoaded('product') || ! $this->relationLoaded('customerGroup')) {
            $this->load(['product', 'customerGroup']);
        }

        $status = [];

        if (! $this->product) {
            $status[] = '产品已删除';
        } elseif (! $this->product->is_active) {
            $status[] = '产品已禁用';
        }

        if (! $this->customerGroup) {
            $status[] = '客户组已删除';
        } elseif (! $this->customerGroup->is_active) {
            $status[] = '客户组已禁用';
        }

        return empty($status) ? '正常' : implode('、', $status);
    }

    public function scopeActive($query)
    {
        return $query->whereHas('product', fn ($q) => $q->active())
            ->whereHas('customerGroup', fn ($q) => $q->active());
    }

    public function scopeWithRelations($query)
    {
        return $query->with(['product' => fn ($q) => $q->withTrashed(), 'customerGroup' => fn ($q) => $q->withTrashed()]);
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function customerGroup()
    {
        return $this->belongsTo(CustomerGroup::class)->withTrashed();
    }
}
