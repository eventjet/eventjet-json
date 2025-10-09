<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json;

use Eventjet\Json\JsonError;
use PHPUnit\Framework\TestCase;

final class JsonErrorTest extends TestCase
{
    public function testCode(): void
    {
        $error = JsonError::decodeFailed('');

        self::assertSame(0, $error->getCode());
    }
}
