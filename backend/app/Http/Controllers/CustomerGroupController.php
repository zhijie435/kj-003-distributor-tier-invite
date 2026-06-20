<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerGroupRequest;
use App\Models\CustomerGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerGroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CustomerGroup::query();

        if ($request->has('active') && $request->boolean('active')) {
            $query->active();
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $customerGroups = $query->ordered()->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => $customerGroups->items(),
            'pagination' => [
                'total' => $customerGroups->total(),
                'per_page' => $customerGroups->perPage(),
                'current_page' => $customerGroups->currentPage(),
                'last_page' => $customerGroups->lastPage(),
            ],
        ]);
    }

    public function store(CustomerGroupRequest $request): JsonResponse
    {
        $customerGroup = CustomerGroup::create($request->validated());

        return response()->json([
            'message' => '客户分组创建成功',
            'data' => $customerGroup,
        ], 201);
    }

    public function show(CustomerGroup $customerGroup): JsonResponse
    {
        return response()->json([
            'data' => $customerGroup->load('models'),
        ]);
    }

    public function update(CustomerGroupRequest $request, CustomerGroup $customerGroup): JsonResponse
    {
        $customerGroup->update($request->validated());

        return response()->json([
            'message' => '客户分组更新成功',
            'data' => $customerGroup,
        ]);
    }

    public function destroy(CustomerGroup $customerGroup): JsonResponse
    {
        $customerGroup->delete();

        return response()->json([
            'message' => '客户分组删除成功',
        ]);
    }

    public function toggleActive(CustomerGroup $customerGroup): JsonResponse
    {
        $customerGroup->update([
            'is_active' => ! $customerGroup->is_active,
        ]);

        return response()->json([
            'message' => '客户分组状态更新成功',
            'data' => $customerGroup,
        ]);
    }

    public function all(): JsonResponse
    {
        $cacheStore = config('customer_groups.cache.store') != 'default' ? config('customer_groups.cache.store') : null;
        $cacheKey = config('customer_groups.cache.key');
        $expiration = config('customer_groups.cache.expiration_time');

        $customerGroups = app('cache')->store($cacheStore)->remember($cacheKey, $expiration, function () {
            return CustomerGroup::active()->ordered()->get(['id', 'name', 'code', 'description', 'settings']);
        });

        return response()->json([
            'data' => $customerGroups,
        ]);
    }

    public function attachUsers(Request $request, CustomerGroup $customerGroup): JsonResponse
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $customerGroup->models()->syncWithoutDetaching($validated['user_ids']);

        return response()->json([
            'message' => '用户添加成功',
            'data' => $customerGroup->load('models'),
        ]);
    }

    public function detachUsers(Request $request, CustomerGroup $customerGroup): JsonResponse
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $customerGroup->models()->detach($validated['user_ids']);

        return response()->json([
            'message' => '用户移除成功',
            'data' => $customerGroup->load('models'),
        ]);
    }
}
