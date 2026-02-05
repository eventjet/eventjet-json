<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json;

use Eventjet\Json\Json;
use Eventjet\Json\UseStatementResolver;
use Eventjet\Test\Unit\Json\Fixtures\SimpleClass;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UseStatementResolver::class)]
final class UseStatementResolverTest extends TestCase
{
    public function testReturnsFullyQualifiedNameUnchanged(): void
    {
        $resolver = new UseStatementResolver();
        $result = $resolver->resolve('Foo\\Bar\\Baz', SimpleClass::class);

        self::assertSame('Foo\\Bar\\Baz', $result);
    }

    public function testResolvesImportedClass(): void
    {
        $resolver = new UseStatementResolver();
        $result = $resolver->resolve('ReflectionClass', Json::class);

        self::assertSame('ReflectionClass', $result);
    }

    public function testFallsBackToSameNamespace(): void
    {
        $resolver = new UseStatementResolver();
        $result = $resolver->resolve('NotImported', SimpleClass::class);

        self::assertSame('Eventjet\\Test\\Unit\\Json\\Fixtures\\NotImported', $result);
    }

    public function testCachesResults(): void
    {
        $resolver = new UseStatementResolver();

        $result1 = $resolver->resolve('NotImported', SimpleClass::class);
        $result2 = $resolver->resolve('NotImported', SimpleClass::class);

        self::assertSame($result1, $result2);
    }

    #[Override]
    protected function setUp(): void
    {
        UseStatementResolver::clearCache();
    }
}
