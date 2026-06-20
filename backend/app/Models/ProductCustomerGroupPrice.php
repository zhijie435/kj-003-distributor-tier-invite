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

    protected function price(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round(floatval($value), 2),
            set: fn ($value) => round(floatval($value), 2),
        );
    }

    protected $appends = [
        'formatted_price',
    ];

    protected function getFormattedPriceAttribute()
    {
        return number_format($this->price, 2, '.', '');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function customerGroup()
    {
        return $this->belongsTo(CustomerGroup::class);
    }
}
