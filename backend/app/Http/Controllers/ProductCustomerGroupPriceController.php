<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductCustomerGroupPriceRequest;
use App\Models\Product;
use App\Models\ProductCustomerGroupPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductCustomerGroupPriceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ProductCustomerGroupPrice::withRelations();

        if ($request->has('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->has('customer_group_id')) {
            $query->where('customer_group_id', $request->input('customer_group_id'));
        }

        if ($request->has('active') && $request->boolean('active')) {
            $query->active();
        }

        if ($request->has('include_inactive') && $request->boolean('include_inactive')) {
            $query->withTrashed();
        }

        $prices = $query->paginate($request->input('per_page', 15));

        $items = collect($prices->items())->map(function ($price) {
            return [
                'id' => $price->id,
                'product_id' => $price->product_id,
                'product' => $price->product ? [
                    'id' => $price->product->id,
                    'name' => $price->product->name,
                    'sku' => $price->product->sku,
                    'base_price' => $price->product->base_price,
                    'formatted_base_price' => number_format($price->product->base_price, 2, '.', ''),
                    'is_active' => $price->product->is_active,
                    'deleted_at' => $price->product->deleted_at,
                ] : null,
                'customer_group_id' => $price->customer_group_id,
                'customer_group' => $price->customerGroup ? [
                    'id' => $price->customerGroup->id,
                    'name' => $price->customerGroup->name,
                    'code' => $price->customerGroup->code,
                    'is_active' => $price->customerGroup->is_active,
                    'deleted_at' => $price->customerGroup->deleted_at,
                ] : null,
                'price' => $price->price,
                'formatted_price' => $price->formatted_price,
                'is_active' => $price->is_active,
                'status' => $price->status,
                'deleted_at' => $price->deleted_at,
                'created_at' => $price->created_at,
                'updated_at' => $price->updated_at,
            ];
        });

        return response()->json([
            'data' => $items,
            'pagination' => [
                'total' => $prices->total(),
                'per_page' => $prices->perPage(),
                'current_page' => $prices->currentPage(),
                'last_page' => $prices->lastPage(),
            ],
        ]);
    }

    public function store(ProductCustomerGroupPriceRequest $request): JsonResponse
    {
        $price = ProductCustomerGroupPrice::updateOrCreate(
            [
                'product_id' => $request->input('product_id'),
                'customer_group_id' => $request->input('customer_group_id'),
            ],
            [
                'price' => $request->input('price'),
            ]
        );

        $price->load(['product' => fn ($q) => $q->withTrashed(), 'customerGroup' => fn ($q) => $q->withTrashed()]);

        return response()->json([
            'message' => '价格配置保存成功',
            'data' => [
                'id' => $price->id,
                'product_id' => $price->product_id,
                'product' => $price->product ? [
                    'id' => $price->product->id,
                    'name' => $price->product->name,
                    'sku' => $price->product->sku,
                    'base_price' => $price->product->base_price,
                    'formatted_base_price' => number_format($price->product->base_price, 2, '.', ''),
                    'is_active' => $price->product->is_active,
                    'deleted_at' => $price->product->deleted_at,
                ] : null,
                'customer_group_id' => $price->customer_group_id,
                'customer_group' => $price->customerGroup ? [
                    'id' => $price->customerGroup->id,
                    'name' => $price->customerGroup->name,
                    'code' => $price->customerGroup->code,
                    'is_active' => $price->customerGroup->is_active,
                    'deleted_at' => $price->customerGroup->deleted_at,
                ] : null,
                'price' => $price->price,
                'formatted_price' => $price->formatted_price,
                'is_active' => $price->is_active,
                'status' => $price->status,
                'created_at' => $price->created_at,
                'updated_at' => $price->updated_at,
            ],
        ], 201);
    }

    public function show(ProductCustomerGroupPrice $productCustomerGroupPrice): JsonResponse
    {
        $productCustomerGroupPrice->load(['product' => fn ($q) => $q->withTrashed(), 'customerGroup' => fn ($q) => $q->withTrashed()]);

        return response()->json([
            'data' => [
                'id' => $productCustomerGroupPrice->id,
                'product_id' => $productCustomerGroupPrice->product_id,
                'product' => $productCustomerGroupPrice->product ? [
                    'id' => $productCustomerGroupPrice->product->id,
                    'name' => $productCustomerGroupPrice->product->name,
                    'sku' => $productCustomerGroupPrice->product->sku,
                    'base_price' => $productCustomerGroupPrice->product->base_price,
                    'formatted_base_price' => number_format($productCustomerGroupPrice->product->base_price, 2, '.', ''),
                    'is_active' => $productCustomerGroupPrice->product->is_active,
                    'deleted_at' => $productCustomerGroupPrice->product->deleted_at,
                ] : null,
                'customer_group_id' => $productCustomerGroupPrice->customer_group_id,
                'customer_group' => $productCustomerGroupPrice->customerGroup ? [
                    'id' => $productCustomerGroupPrice->customerGroup->id,
                    'name' => $productCustomerGroupPrice->customerGroup->name,
                    'code' => $productCustomerGroupPrice->customerGroup->code,
                    'is_active' => $productCustomerGroupPrice->customerGroup->is_active,
                    'deleted_at' => $productCustomerGroupPrice->customerGroup->deleted_at,
                ] : null,
                'price' => $productCustomerGroupPrice->price,
                'formatted_price' => $productCustomerGroupPrice->formatted_price,
                'is_active' => $productCustomerGroupPrice->is_active,
                'status' => $productCustomerGroupPrice->status,
                'deleted_at' => $productCustomerGroupPrice->deleted_at,
                'created_at' => $productCustomerGroupPrice->created_at,
                'updated_at' => $productCustomerGroupPrice->updated_at,
            ],
        ]);
    }

    public function update(ProductCustomerGroupPriceRequest $request, ProductCustomerGroupPrice $productCustomerGroupPrice): JsonResponse
    {
        $productCustomerGroupPrice->update($request->validated());

        $productCustomerGroupPrice->fresh()->load(['product' => fn ($q) => $q->withTrashed(), 'customerGroup' => fn ($q) => $q->withTrashed()]);

        return response()->json([
            'message' => '价格配置更新成功',
            'data' => [
                'id' => $productCustomerGroupPrice->id,
                'product_id' => $productCustomerGroupPrice->product_id,
                'product' => $productCustomerGroupPrice->product ? [
                    'id' => $productCustomerGroupPrice->product->id,
                    'name' => $productCustomerGroupPrice->product->name,
                    'sku' => $productCustomerGroupPrice->product->sku,
                    'base_price' => $productCustomerGroupPrice->product->base_price,
                    'formatted_base_price' => number_format($productCustomerGroupPrice->product->base_price, 2, '.', ''),
                    'is_active' => $productCustomerGroupPrice->product->is_active,
                    'deleted_at' => $productCustomerGroupPrice->product->deleted_at,
                ] : null,
                'customer_group_id' => $productCustomerGroupPrice->customer_group_id,
                'customer_group' => $productCustomerGroupPrice->customerGroup ? [
                    'id' => $productCustomerGroupPrice->customerGroup->id,
                    'name' => $productCustomerGroupPrice->customerGroup->name,
                    'code' => $productCustomerGroupPrice->customerGroup->code,
                    'is_active' => $productCustomerGroupPrice->customerGroup->is_active,
                    'deleted_at' => $productCustomerGroupPrice->customerGroup->deleted_at,
                ] : null,
                'price' => $productCustomerGroupPrice->price,
                'formatted_price' => $productCustomerGroupPrice->formatted_price,
                'is_active' => $productCustomerGroupPrice->is_active,
                'status' => $productCustomerGroupPrice->status,
                'created_at' => $productCustomerGroupPrice->created_at,
                'updated_at' => $productCustomerGroupPrice->updated_at,
            ],
        ]);
    }

    public function destroy(ProductCustomerGroupPrice $productCustomerGroupPrice): JsonResponse
    {
        $productCustomerGroupPrice->delete();

        return response()->json([
            'message' => '价格配置删除成功',
        ]);
    }

    public function getByProduct(Request $request, Product $product): JsonResponse
    {
        $activeOnly = ! $request->has('include_inactive') || ! $request->boolean('include_inactive');
        $allPrices = $product->getAllCustomerGroupPrices($activeOnly);
        $rawPricesQuery = $product->customerGroupPrices()->with(['customerGroup' => fn ($q) => $q->withTrashed()]);

        if ($activeOnly) {
            $rawPricesQuery->whereHas('customerGroup', fn ($q) => $q->active());
        }

        $rawPrices = $rawPricesQuery->get();

        return response()->json([
            'data' => [
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'base_price' => $product->base_price,
                    'formatted_base_price' => number_format($product->base_price, 2, '.', ''),
                    'is_active' => $product->is_active,
                ],
                'prices' => $allPrices,
                'raw_prices' => $rawPrices->map(function ($price) {
                    return [
                        'id' => $price->id,
                        'product_id' => $price->product_id,
                        'customer_group_id' => $price->customer_group_id,
                        'customer_group' => $price->customerGroup ? [
                            'id' => $price->customerGroup->id,
                            'name' => $price->customerGroup->name,
                            'code' => $price->customerGroup->code,
                            'is_active' => $price->customerGroup->is_active,
                            'deleted_at' => $price->customerGroup->deleted_at,
                        ] : null,
                        'price' => $price->price,
                        'formatted_price' => $price->formatted_price,
                        'is_active' => $price->is_active,
                        'status' => $price->status,
                        'created_at' => $price->created_at,
                        'updated_at' => $price->updated_at,
                    ];
                }),
            ],
        ]);
    }

    public function batchUpdateByProduct(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'prices' => 'required|array',
            'prices.*.customer_group_id' => 'required|exists:customer_groups,id',
            'prices.*.price' => 'required|numeric|min:0',
        ]);

        foreach ($validated['prices'] as $priceData) {
            ProductCustomerGroupPrice::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'customer_group_id' => $priceData['customer_group_id'],
                ],
                [
                    'price' => $priceData['price'],
                ]
            );
        }

        $product->fresh();
        $allPrices = $product->getAllCustomerGroupPrices();

        return response()->json([
            'message' => '价格配置批量更新成功',
            'data' => [
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'base_price' => $product->base_price,
                    'formatted_base_price' => number_format($product->base_price, 2, '.', ''),
                    'is_active' => $product->is_active,
                ],
                'prices' => $allPrices,
            ],
        ]);
    }
}
