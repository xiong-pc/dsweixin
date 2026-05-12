<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\BusinessException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for BusinessException value object.
 */
class BusinessExceptionTest extends TestCase
{
    #[Test]
    public function default_status_code_is_400(): void
    {
        $ex = new BusinessException('api.error');

        $this->assertSame(400, $ex->getStatusCode());
        $this->assertSame('api.error', $ex->getMessage());
    }

    #[Test]
    public function accepts_custom_status_code(): void
    {
        $ex = new BusinessException('api.forbidden', 403);

        $this->assertSame(403, $ex->getStatusCode());
        $this->assertSame('api.forbidden', $ex->getMessage());
    }

    #[Test]
    public function is_a_runtime_exception(): void
    {
        $ex = new BusinessException('any');

        $this->assertInstanceOf(RuntimeException::class, $ex);
    }

    #[Test]
    public function preserves_raw_message_for_language_pack_resolution(): void
    {
        // BusinessException message 既可以是语言包 key（'api.menu_has_children'），
        // 也可以是直接的中文字符串。getMessage 应原样返回。
        $ex1 = new BusinessException('api.menu_has_children');
        $ex2 = new BusinessException('菜单存在子项，无法删除');

        $this->assertSame('api.menu_has_children', $ex1->getMessage());
        $this->assertSame('菜单存在子项，无法删除', $ex2->getMessage());
    }
}
