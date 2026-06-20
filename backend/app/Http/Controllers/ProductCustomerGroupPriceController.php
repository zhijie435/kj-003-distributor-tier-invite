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

        return response()->json([
            'data' => $prices->items(),
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
        $prices = $product->customerGroupPrices()
            ->with('customerGroup')
            ->get();

        return response()->json([
            'data' => $prices,
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
