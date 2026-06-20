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

        $products = $query->ordered()->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => $products->items(),
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
        return response()->json([
            'data' => $product->load('customerGroupPrices.customerGroup'),
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

    public function all(): JsonResponse
    {
        $products = Product::active()->ordered()->get(['id', 'name', 'sku', 'base_price']);

        return response()->json([
            'data' => $products,
        ]);
    }
}
