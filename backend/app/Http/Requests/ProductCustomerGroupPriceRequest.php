<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductCustomerGroupPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'customer_group_id' => 'required|exists:customer_groups,id',
            'price' => 'required|numeric|min:0',
        ];
    }
}
