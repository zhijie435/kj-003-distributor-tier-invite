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
        $query = ProductCustomerGroupPrice::with(['product', 'customerGroup']);

        if ($request->has('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        if ($request->has('customer_group_id')) {
            $query->where('customer_group_id', $request->input('customer_group_id'));
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
                ] : null,
                'customer_group_id' => $price->customer_group_id,
                'customer_group' => $price->customerGroup ? [
                    'id' => $price->customerGroup->id,
                    'name' => $price->customerGroup->name,
                    'code' => $price->customerGroup->code,
                ] : null,
                'price' => $price->price,
                'formatted_price' => $price->formatted_price,
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

        return response()->json([
            'message' => '价格配置保存成功',
            'data' => $price->load(['product', 'customerGroup']),
        ], 201);
    }

    public function show(ProductCustomerGroupPrice $productCustomerGroupPrice): JsonResponse
    {
        return response()->json([
            'data' => $productCustomerGroupPrice->load(['product', 'customerGroup']),
        ]);
    }

    public function update(ProductCustomerGroupPriceRequest $request, ProductCustomerGroupPrice $productCustomerGroupPrice): JsonResponse
    {
        $productCustomerGroupPrice->update($request->validated());

        return response()->json([
            'message' => '价格配置更新成功',
            'data' => $productCustomerGroupPrice->fresh()->load(['product', 'customerGroup']),
        ]);
    }

    public function destroy(ProductCustomerGroupPrice $productCustomerGroupPrice): JsonResponse
    {
        $productCustomerGroupPrice->delete();

        return response()->json([
            'message' => '价格配置删除成功',
        ]);
    }

    public function getByProduct(Product $product): JsonResponse
    {
        $allPrices = $product->getAllCustomerGroupPrices();
        $rawPrices = $product->customerGroupPrices()->with('customerGroup')->get();

        return response()->json([
            'data' => [
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'base_price' => $product->base_price,
                    'formatted_base_price' => number_format($product->base_price, 2, '.', ''),
                ],
                'prices' => $allPrices,
                'raw_prices' => $rawPrices,
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

        return response()->json([
            'message' => '价格配置批量更新成功',
            'data' => $product->fresh()->load('customerGroupPrices.customerGroup'),
        ]);
    }
}
