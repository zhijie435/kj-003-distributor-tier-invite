<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'sku', 'description', 'base_price', 'is_active', 'sort_order'])]
class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $casts = [
        'is_active' => 'boolean',
        'base_price' => 'float',
    ];

    protected function basePrice(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round(floatval($value), 2),
            set: fn ($value) => round(floatval($value), 2),
        );
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeWithPrices($query, $customerGroupId = null, $activeOnly = false)
    {
        $query->with(['customerGroupPrices' => function ($q) use ($customerGroupId, $activeOnly) {
            if ($customerGroupId) {
                $q->where('customer_group_id', $customerGroupId);
            }
            if ($activeOnly) {
                $q->whereHas('customerGroup', fn ($subQ) => $subQ->active());
            }
            $q->with('customerGroup');
        }]);
    }

    public function customerGroupPrices()
    {
        return $this->hasMany(ProductCustomerGroupPrice::class);
    }

    public function activeCustomerGroupPrices()
    {
        return $this->hasMany(ProductCustomerGroupPrice::class)
            ->whereHas('customerGroup', fn ($q) => $q->active());
    }

    public function getPriceForCustomerGroup($customerGroupId, $checkStatus = true)
    {
        if ($checkStatus && ! $this->is_active) {
            return null;
        }

        $query = $this->customerGroupPrices()
            ->where('customer_group_id', $customerGroupId);

        if ($checkStatus) {
            $query->whereHas('customerGroup', fn ($q) => $q->active());
        }

        $priceModel = $query->first();

        return $priceModel ? $priceModel->price : $this->base_price;
    }

    public function getAllCustomerGroupPrices($activeOnly = true)
    {
        $customerGroupsQuery = CustomerGroup::ordered();

        if ($activeOnly) {
            $customerGroupsQuery->active();
        }

        $customerGroups = $customerGroupsQuery->get(['id', 'name', 'code', 'is_active']);

        $pricesQuery = $this->customerGroupPrices();

        if ($activeOnly) {
            $pricesQuery->whereHas('customerGroup', fn ($q) => $q->active());
        }

        $prices = $pricesQuery->with('customerGroup')->get()->keyBy('customer_group_id');

        return $customerGroups->map(function ($group) use ($prices, $activeOnly) {
            $price = $prices->get($group->id);
            $isGroupActive = $group->is_active;
            $isProductActive = $this->is_active;
            $isPriceActive = $price ? ($price->customerGroup && $price->customerGroup->is_active) : false;

            return [
                'customer_group_id' => $group->id,
                'customer_group_name' => $group->name,
                'customer_group_code' => $group->code,
                'customer_group_is_active' => $isGroupActive,
                'product_is_active' => $isProductActive,
                'price' => $price ? $price->price : $this->base_price,
                'formatted_price' => number_format($price ? $price->price : $this->base_price, 2, '.', ''),
                'is_custom' => ! is_null($price),
                'is_active' => $isProductActive && $isGroupActive,
                'is_price_active' => $isProductActive && ($price ? $isPriceActive : $isGroupActive),
            ];
        });
    }
}
