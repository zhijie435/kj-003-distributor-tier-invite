<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvitationCodeRequest;
use App\Models\CustomerGroup;
use App\Models\InvitationCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvitationCodeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = InvitationCode::with(['customerGroup' => fn ($q) => $q->withTrashed()]);

        if ($request->has('customer_group_id')) {
            $query->forGroup($request->input('customer_group_id'));
        }

        if ($request->has('active') && $request->boolean('active')) {
            $query->active();
        }

        if ($request->has('is_valid') && $request->boolean('is_valid')) {
            $query->active()->notExpired();
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('include_inactive') && $request->boolean('include_inactive')) {
            $query->withTrashed();
        }

        $invitationCodes = $query->orderByDesc('created_at')->paginate($request->input('per_page', 15));

        $items = collect($invitationCodes->items())->map(function ($code) {
            return $this->formatInvitationCode($code);
        });

        return response()->json([
            'data' => $items,
            'pagination' => [
                'total' => $invitationCodes->total(),
                'per_page' => $invitationCodes->perPage(),
                'current_page' => $invitationCodes->currentPage(),
                'last_page' => $invitationCodes->lastPage(),
            ],
        ]);
    }

    public function store(InvitationCodeRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (empty($data['code'])) {
            unset($data['code']);
        }

        $invitationCode = InvitationCode::create($data);
        $invitationCode->load(['customerGroup' => fn ($q) => $q->withTrashed()]);

        return response()->json([
            'message' => '邀请码创建成功',
            'data' => $this->formatInvitationCode($invitationCode),
        ], 201);
    }

    public function show(InvitationCode $invitationCode): JsonResponse
    {
        $invitationCode->load([
            'customerGroup' => fn ($q) => $q->withTrashed(),
            'usages.user',
        ]);

        return response()->json([
            'data' => array_merge($this->formatInvitationCode($invitationCode), [
                'usages' => $invitationCode->usages->map(function ($usage) {
                    return [
                        'id' => $usage->id,
                        'user' => $usage->user ? [
                            'id' => $usage->user->id,
                            'name' => $usage->user->name,
                            'email' => $usage->user->email,
                        ] : null,
                        'created_at' => $usage->created_at,
                    ];
                }),
            ]),
        ]);
    }

    public function update(InvitationCodeRequest $request, InvitationCode $invitationCode): JsonResponse
    {
        $invitationCode->update($request->validated());
        $invitationCode->fresh()->load(['customerGroup' => fn ($q) => $q->withTrashed()]);

        return response()->json([
            'message' => '邀请码更新成功',
            'data' => $this->formatInvitationCode($invitationCode),
        ]);
    }

    public function destroy(InvitationCode $invitationCode): JsonResponse
    {
        $invitationCode->delete();

        return response()->json([
            'message' => '邀请码删除成功',
        ]);
    }

    public function toggleActive(InvitationCode $invitationCode): JsonResponse
    {
        $invitationCode->update([
            'is_active' => ! $invitationCode->is_active,
        ]);

        $invitationCode->load(['customerGroup' => fn ($q) => $q->withTrashed()]);

        return response()->json([
            'message' => '邀请码状态更新成功',
            'data' => $this->formatInvitationCode($invitationCode->fresh()),
        ]);
    }

    public function redeem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string',
        ]);

        $invitationCode = InvitationCode::where('code', strtoupper($validated['code']))->first();

        if (! $invitationCode) {
            return response()->json([
                'message' => '邀请码不存在',
            ], 404);
        }

        if (! $invitationCode->is_valid) {
            $reason = '邀请码无效';
            if ($invitationCode->is_expired) {
                $reason = '邀请码已过期';
            } elseif ($invitationCode->is_used_up) {
                $reason = '邀请码已用完';
            } elseif (! $invitationCode->is_active) {
                $reason = '邀请码已禁用';
            }

            return response()->json([
                'message' => $reason,
            ], 422);
        }

        $user = $request->user();

        if (! $user) {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
            ]);
            $user = \App\Models\User::find($validated['user_id']);
        }

        if ($invitationCode->usages()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'message' => '您已使用过该邀请码',
            ], 422);
        }

        $result = $invitationCode->apply($user);

        if (! $result) {
            return response()->json([
                'message' => '邀请码使用失败',
            ], 422);
        }

        $invitationCode->load(['customerGroup' => fn ($q) => $q->withTrashed()]);

        return response()->json([
            'message' => '邀请码使用成功',
            'data' => [
                'invitation_code' => $this->formatInvitationCode($invitationCode),
                'customer_group' => [
                    'id' => $invitationCode->customerGroup->id,
                    'name' => $invitationCode->customerGroup->name,
                    'code' => $invitationCode->customerGroup->code,
                ],
            ],
        ]);
    }

    public function batchGenerate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_group_id' => 'required|exists:customer_groups,id',
            'count' => 'required|integer|min:1|max:100',
            'description' => 'nullable|string|max:255',
            'max_uses' => 'integer|min:0',
            'expires_at' => 'nullable|date|after:now',
            'code_length' => 'integer|min:4|max:20',
        ]);

        $count = $validated['count'];
        $codeLength = $validated['code_length'] ?? 8;
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = InvitationCode::create([
                'customer_group_id' => $validated['customer_group_id'],
                'description' => $validated['description'] ?? null,
                'max_uses' => $validated['max_uses'] ?? 0,
                'expires_at' => $validated['expires_at'] ?? null,
                'code_length' => $codeLength,
            ]);
        }

        $codes = collect($codes)->map(fn ($code) => $this->formatInvitationCode($code->load(['customerGroup' => fn ($q) => $q->withTrashed()])));

        return response()->json([
            'message' => "成功生成 {$count} 个邀请码",
            'data' => $codes,
        ], 201);
    }

    public function getByCustomerGroup(Request $request, CustomerGroup $customerGroup): JsonResponse
    {
        $query = InvitationCode::forGroup($customerGroup->id)
            ->with(['customerGroup' => fn ($q) => $q->withTrashed()]);

        if ($request->has('active') && $request->boolean('active')) {
            $query->active();
        }

        if ($request->has('include_inactive') && $request->boolean('include_inactive')) {
            $query->withTrashed();
        }

        $codes = $query->orderByDesc('created_at')->get();

        return response()->json([
            'data' => $codes->map(fn ($code) => $this->formatInvitationCode($code)),
        ]);
    }

    private function formatInvitationCode(InvitationCode $code): array
    {
        return [
            'id' => $code->id,
            'code' => $code->code,
            'customer_group_id' => $code->customer_group_id,
            'customer_group' => $code->customerGroup ? [
                'id' => $code->customerGroup->id,
                'name' => $code->customerGroup->name,
                'code' => $code->customerGroup->code,
                'is_active' => $code->customerGroup->is_active,
                'deleted_at' => $code->customerGroup->deleted_at,
            ] : null,
            'description' => $code->description,
            'max_uses' => $code->max_uses,
            'used_count' => $code->used_count,
            'remaining_uses' => $code->remaining_uses,
            'uses_display' => $code->uses_display,
            'expires_at' => $code->expires_at,
            'is_active' => $code->is_active,
            'is_valid' => $code->is_valid,
            'is_expired' => $code->is_expired,
            'is_used_up' => $code->is_used_up,
            'deleted_at' => $code->deleted_at,
            'created_at' => $code->created_at,
            'updated_at' => $code->updated_at,
        ];
    }
}
