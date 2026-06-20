<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class InvitationCodeException extends Exception
{
    public const CODE_NOT_FOUND = 'CODE_NOT_FOUND';
    public const CODE_EXPIRED = 'CODE_EXPIRED';
    public const CODE_USED_UP = 'CODE_USED_UP';
    public const CODE_INACTIVE = 'CODE_INACTIVE';
    public const CODE_INVALID = 'CODE_INVALID';
    public const CODE_ALREADY_USED = 'CODE_ALREADY_USED';
    public const CODE_APPLY_FAILED = 'CODE_APPLY_FAILED';
    public const CUSTOMER_GROUP_NOT_FOUND = 'CUSTOMER_GROUP_NOT_FOUND';
    public const UNAUTHORIZED = 'UNAUTHORIZED';
    public const VALIDATION_ERROR = 'VALIDATION_ERROR';

    protected string $errorCode;

    protected int $statusCode;

    protected array $context;

    public function __construct(
        string $message,
        string $errorCode = self::CODE_INVALID,
        int $statusCode = 422,
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->errorCode = $errorCode;
        $this->statusCode = $statusCode;
        $this->context = $context;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function render(): JsonResponse
    {
        $response = [
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
        ];

        if (! empty($this->context)) {
            $response['context'] = $this->context;
        }

        if (config('app.debug') && $this->getPrevious()) {
            $response['debug'] = [
                'file' => $this->getPrevious()->getFile(),
                'line' => $this->getPrevious()->getLine(),
                'trace' => collect($this->getPrevious()->getTrace())
                    ->take(10)
                    ->map(fn ($trace) => [
                        'file' => $trace['file'] ?? null,
                        'line' => $trace['line'] ?? null,
                        'function' => $trace['function'] ?? null,
                    ])
                    ->toArray(),
            ];
        }

        return response()->json($response, $this->statusCode);
    }

    public static function notFound(): self
    {
        return new self(
            '邀请码不存在',
            self::CODE_NOT_FOUND,
            404
        );
    }

    public static function expired(): self
    {
        return new self(
            '邀请码已过期',
            self::CODE_EXPIRED,
            422
        );
    }

    public static function usedUp(): self
    {
        return new self(
            '邀请码已用完',
            self::CODE_USED_UP,
            422
        );
    }

    public static function inactive(): self
    {
        return new self(
            '邀请码已禁用',
            self::CODE_INACTIVE,
            422
        );
    }

    public static function invalid(): self
    {
        return new self(
            '邀请码无效',
            self::CODE_INVALID,
            422
        );
    }

    public static function alreadyUsed(): self
    {
        return new self(
            '您已使用过该邀请码',
            self::CODE_ALREADY_USED,
            422
        );
    }

    public static function applyFailed(string $reason = ''): self
    {
        return new self(
            $reason ?: '邀请码使用失败',
            self::CODE_APPLY_FAILED,
            422
        );
    }

    public static function customerGroupNotFound(): self
    {
        return new self(
            '关联的客户分组不存在',
            self::CUSTOMER_GROUP_NOT_FOUND,
            422
        );
    }

    public static function unauthorized(string $action = '操作'): self
    {
        return new self(
            "无权执行该{$action}",
            self::UNAUTHORIZED,
            403
        );
    }
}
