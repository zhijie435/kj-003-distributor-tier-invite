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

    public function scopeWithPrices($query, $customerGroupId = null)
    {
        $query->with(['customerGroupPrices' => function ($q) use ($customerGroupId) {
            if ($customerGroupId) {
                $q->where('customer_group_id', $customerGroupId);
            }
            $q->with('customerGroup');
        }]);
    }

    public function customerGroupPrices()
    {
        return $this->hasMany(ProductCustomerGroupPrice::class);
    }

    public function getPriceForCustomerGroup($customerGroupId)
    {
        $priceModel = $this->customerGroupPrices()
            ->where('customer_group_id', $customerGroupId)
            ->first();

        return $priceModel ? $priceModel->price : $this->base_price;
    }

    public function getAllCustomerGroupPrices()
    {
        $customerGroups = CustomerGroup::active()->ordered()->get(['id', 'name', 'code']);
        $prices = $this->customerGroupPrices()->get()->keyBy('customer_group_id');

        return $customerGroups->map(function ($group) use ($prices) {
            $price = $prices->get($group->id);

            return [
                'customer_group_id' => $group->id,
                'customer_group_name' => $group->name,
                'customer_group_code' => $group->code,
                'price' => $price ? $price->price : $this->base_price,
                'is_custom' => ! is_null($price),
            ];
        });
    }
}
