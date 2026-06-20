<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'sku', 'description', 'base_price', 'is_active', 'sort_order'])]
class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $casts = [
        'is_active' => 'boolean',
        'base_price' => 'decimal:2',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function customerGroupPrices()
    {
        return $this->hasMany(ProductCustomerGroupPrice::class);
    }

    public function getPriceForCustomerGroup($customerGroupId)
    {
        $price = $this->customerGroupPrices()
            ->where('customer_group_id', $customerGroupId)
            ->value('price');

        return $price ?? $this->base_price;
    }
}
