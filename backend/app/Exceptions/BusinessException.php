<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * 业务逻辑异常
 *
 * Service 层抛出此异常，全局 handler 统一捕获并返回 JSON 响应。
 * message 传语言包 key（如 'api.menu_has_children'）或直接传中文字符串。
 */
class BusinessException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode = 400,
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
