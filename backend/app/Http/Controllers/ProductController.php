<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::query();

        if ($request->has('active') && $request->boolean('active')) {
            $query->active();
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('with_prices') && $request->boolean('with_prices')) {
            $customerGroupId = $request->input('customer_group_id');
            $query->withPrices($customerGroupId);
        }

        $products = $query->ordered()->paginate($request->input('per_page', 15));

        $items = collect($products->items())->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'description' => $product->description,
                'base_price' => $product->base_price,
                'formatted_base_price' => number_format($product->base_price, 2, '.', ''),
                'is_active' => $product->is_active,
                'sort_order' => $product->sort_order,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
                'customer_group_prices' => $product->relationLoaded('customerGroupPrices')
                    ? $product->customerGroupPrices
                    : null,
            ];
        });

        return response()->json([
            'data' => $items,
            'pagination' => [
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ],
        ]);
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return response()->json([
            'message' => '产品创建成功',
            'data' => $product,
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load('customerGroupPrices.customerGroup');

        return response()->json([
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'description' => $product->description,
                'base_price' => $product->base_price,
                'formatted_base_price' => number_format($product->base_price, 2, '.', ''),
                'is_active' => $product->is_active,
                'sort_order' => $product->sort_order,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
                'customer_group_prices' => $product->getAllCustomerGroupPrices(),
                'raw_prices' => $product->customerGroupPrices,
            ],
        ]);
    }

    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        $product->update($request->validated());

        return response()->json([
            'message' => '产品更新成功',
            'data' => $product->fresh(),
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json([
            'message' => '产品删除成功',
        ]);
    }

    public function toggleActive(Product $product): JsonResponse
    {
        $product->update([
            'is_active' => ! $product->is_active,
        ]);

        return response()->json([
            'message' => '产品状态更新成功',
            'data' => $product->fresh(),
        ]);
    }

    public function all(Request $request): JsonResponse
    {
        $query = Product::active()->ordered();

        if ($request->has('with_prices') && $request->boolean('with_prices')) {
            $customerGroupId = $request->input('customer_group_id');
            $query->withPrices($customerGroupId);
        }

        $products = $query->get();

        $items = $products->map(function ($product) {
            $data = [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'base_price' => $product->base_price,
                'formatted_base_price' => number_format($product->base_price, 2, '.', ''),
            ];

            if ($product->relationLoaded('customerGroupPrices')) {
                $data['customer_group_prices'] = $product->customerGroupPrices;
            }

            return $data;
        });

        return response()->json([
            'data' => $items,
        ]);
    }
}
