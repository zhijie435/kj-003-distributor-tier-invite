<?php

namespace App\Http\Controllers;

use App\Exceptions\InvitationCodeException;
use App\Http\Requests\InvitationCodeRequest;
use App\Models\CustomerGroup;
use App\Models\InvitationCode;
use App\Services\InvitationCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class InvitationCodeController extends Controller
{
    public function __construct(
        protected InvitationCodeService $service
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->service->authorize('viewAny', InvitationCode::class);

        $filters = $request->only([
            'customer_group_id', 'status', 'active', 'is_valid',
            'search', 'include_trashed', 'only_trashed',
        ]);

        if ($request->has('include_inactive') && $request->boolean('include_inactive')) {
            $filters['include_trashed'] = true;
        }

        $perPage = (int) $request->input('per_page', 15);
        $page = (int) $request->input('page', 1);

        $paginator = $this->service->paginate($filters, $perPage, $page);

        $items = collect($paginator->items())->map(function ($code) {
            return $this->service->formatInvitationCode($code);
        });

        return response()->json([
            'data' => $items,
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(InvitationCodeRequest $request): JsonResponse
    {
        try {
            $invitationCode = $this->service->create(
                $request->validatedData()
            );

            return response()->json([
                'message' => '邀请码创建成功',
                'data' => $this->service->formatInvitationCode($invitationCode),
            ], 201);
        } catch (InvitationCodeException $e) {
            return $e->render();
        }
    }

    public function show(Request $request, InvitationCode $invitationCode): JsonResponse
    {
        $this->service->authorize('view', $invitationCode);

        $invitationCode->load([
            'customerGroup' => fn ($q) => $q->withTrashed(),
            'usages.user',
        ]);

        return response()->json([
            'data' => array_merge(
                $this->service->formatInvitationCode($invitationCode),
                ['usages' => $this->service->formatUsages($invitationCode)]
            ),
        ]);
    }

    public function update(InvitationCodeRequest $request, InvitationCode $invitationCode): JsonResponse
    {
        try {
            $invitationCode = $this->service->update(
                $invitationCode,
                $request->validatedData()
            );

            return response()->json([
                'message' => '邀请码更新成功',
                'data' => $this->service->formatInvitationCode($invitationCode),
            ]);
        } catch (InvitationCodeException $e) {
            return $e->render();
        }
    }

    public function destroy(InvitationCode $invitationCode): JsonResponse
    {
        $this->service->authorize('delete', $invitationCode);

        try {
            $this->service->delete($invitationCode);

            return response()->json([
                'message' => '邀请码删除成功',
            ]);
        } catch (InvitationCodeException $e) {
            return $e->render();
        }
    }

    public function toggleActive(InvitationCode $invitationCode): JsonResponse
    {
        $this->service->authorize('toggleActive', $invitationCode);

        try {
            $invitationCode = $this->service->toggleActive($invitationCode);

            return response()->json([
                'message' => '邀请码状态更新成功',
                'data' => $this->service->formatInvitationCode($invitationCode),
            ]);
        } catch (InvitationCodeException $e) {
            return $e->render();
        }
    }

    public function redeem(Request $request): JsonResponse
    {
        $this->service->authorize('redeem', InvitationCode::class);

        try {
            $validated = $request->validate([
                'code' => 'required|string',
                'user_id' => 'nullable|exists:users,id',
            ]);

            $user = $request->user();
            if (! $user && ! empty($validated['user_id'])) {
                $user = \App\Models\User::find($validated['user_id']);
            }

            if (! $user) {
                throw InvitationCodeException::unauthorized('使用邀请码');
            }

            $result = $this->service->redeem($validated['code'], $user);

            return response()->json([
                'message' => '邀请码使用成功',
                'data' => $result,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => '数据验证失败',
                'error_code' => InvitationCodeException::VALIDATION_ERROR,
                'errors' => $e->errors(),
            ], 422);
        } catch (InvitationCodeException $e) {
            return $e->render();
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'message' => '邀请码使用失败',
                'error_code' => InvitationCodeException::CODE_APPLY_FAILED,
            ], 500);
        }
    }

    public function batchGenerate(Request $request): JsonResponse
    {
        $this->service->authorize('batchGenerate', InvitationCode::class);

        try {
            $validated = $request->validate([
                'customer_group_id' => 'required|exists:customer_groups,id,deleted_at,NULL',
                'count' => 'required|integer|min:1|max:100',
                'description' => 'nullable|string|max:255',
                'max_uses' => 'nullable|integer|min:0|max:1000000',
                'expires_at' => 'nullable|date|after:now',
                'code_length' => 'nullable|integer|min:4|max:20',
            ], [
                'customer_group_id.required' => '请选择客户分组',
                'customer_group_id.exists' => '客户分组不存在或已删除',
                'count.required' => '请输入生成数量',
                'count.min' => '生成数量至少为 1',
                'count.max' => '单次最多生成 100 个邀请码',
                'code_length.min' => '邀请码长度不能少于 4 个字符',
                'code_length.max' => '邀请码长度不能超过 20 个字符',
                'expires_at.after' => '过期时间必须晚于当前时间',
            ]);

            $codes = $this->service->batchGenerate($validated);
            $count = $codes->count();

            $formattedCodes = $codes->map(fn ($code) => $this->service->formatInvitationCode($code));

            return response()->json([
                'message' => "成功生成 {$count} 个邀请码",
                'data' => $formattedCodes,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => '数据验证失败',
                'error_code' => InvitationCodeException::VALIDATION_ERROR,
                'errors' => $e->errors(),
            ], 422);
        } catch (InvitationCodeException $e) {
            return $e->render();
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'message' => '批量生成邀请码失败',
                'error_code' => InvitationCodeException::CODE_APPLY_FAILED,
            ], 500);
        }
    }

    public function getByCustomerGroup(Request $request, CustomerGroup $customerGroup): JsonResponse
    {
        $this->service->authorize('viewAnyByCustomerGroup', InvitationCode::class);

        $filters = $request->only(['status', 'active', 'include_trashed', 'only_trashed']);

        if ($request->has('include_inactive') && $request->boolean('include_inactive')) {
            $filters['include_trashed'] = true;
        }

        $codes = $this->service->getByCustomerGroup($customerGroup, $filters);

        $formattedCodes = $codes->map(fn ($code) => $this->service->formatInvitationCode($code));

        return response()->json([
            'data' => $formattedCodes,
        ]);
    }

    public function validate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'code' => 'required|string',
            ]);

            $result = $this->service->validateCode($validated['code']);

            return response()->json([
                'data' => $result,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => '数据验证失败',
                'error_code' => InvitationCodeException::VALIDATION_ERROR,
                'errors' => $e->errors(),
            ], 422);
        } catch (InvitationCodeException $e) {
            return $e->render();
        }
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $this->service->authorize('restore', InvitationCode::class);

        try {
            $invitationCode = $this->service->restore($id);

            return response()->json([
                'message' => '邀请码恢复成功',
                'data' => $this->service->formatInvitationCode($invitationCode),
            ]);
        } catch (InvitationCodeException $e) {
            return $e->render();
        }
    }
}
