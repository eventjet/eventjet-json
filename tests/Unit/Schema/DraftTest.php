<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit\Schema;

use Closure;
use Eventjet\Json\Internal\Wither;
use Eventjet\Json\Schema\Draft201909;
use Eventjet\Json\Schema\Draft202012;
use Eventjet\Json\Schema\Draft4;
use Eventjet\Json\Schema\Draft6;
use Eventjet\Json\Schema\Draft7;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;

#[CoversClass(Draft4::class)]
#[CoversClass(Draft6::class)]
#[CoversClass(Draft7::class)]
#[CoversClass(Draft201909::class)]
#[CoversClass(Draft202012::class)]
#[CoversClass(Wither::class)]
final class DraftTest extends TestCase
{
    /** @param array<array-key, mixed> $changes */
    #[DataProviderExternal(WithCases::class, 'changes')]
    public function testWithChangesOnlyTheGivenKeywords(Closure $with, array $changes, object $expected): void
    {
        static::assertEquals($expected, $with(...$changes));
    }
}
