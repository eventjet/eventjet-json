<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\MetadataCache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

#[CoversClass(MetadataCache::class)]
#[UsesClass(DecodeError::class)]
final class MetadataCacheTest extends TestCase
{
    public function testRepeatedLookupsLoadOnceIncludingEmptyMetadata(): void
    {
        foreach ([[], new stdClass()] as $metadata) {
            /** @var MetadataCache<array<never, never>|stdClass> $cache */
            $cache = new MetadataCache();
            $counter = new class {
                public int $calls = 0;
            };
            $load = /** @return array<never, never>|stdClass */ static function () use (
                $counter,
                $metadata,
            ): array|stdClass {
                ++$counter->calls;
                return $metadata;
            };

            for ($lookup = 0; $lookup < 3; ++$lookup) {
                static::assertSame($metadata, $cache->resolve('field', $load));
            }
            static::assertSame(1, $counter->calls);
        }
    }

    public function testKeysAndCacheInstancesRemainIndependent(): void
    {
        /** @var MetadataCache<int> $first */
        $first = new MetadataCache();
        /** @var MetadataCache<int> $second */
        $second = new MetadataCache();
        $counter = new class {
            public int $calls = 0;
        };
        $load = static fn(): int => ++$counter->calls;

        static::assertSame(1, $first->resolve('first', $load));
        static::assertSame(2, $first->resolve('second', $load));
        static::assertSame(3, $second->resolve('first', $load));
        static::assertSame(1, $first->resolve('first', $load));
        static::assertSame(2, $first->resolve('second', $load));
        static::assertSame(3, $second->resolve('first', $load));
        static::assertSame(3, $counter->calls);
    }

    public function testUnresolvedMetadataAndErrorsAreRetried(): void
    {
        foreach ([null, DecodeError::invalidJson('invalid')] as $failure) {
            /** @var MetadataCache<stdClass|DecodeError|null> $cache */
            $cache = new MetadataCache();
            $counter = new class {
                public int $calls = 0;
            };
            $metadata = new stdClass();
            $load = static function () use ($counter, $failure, $metadata): stdClass|DecodeError|null {
                ++$counter->calls;
                return $counter->calls === 1 ? $failure : $metadata;
            };

            static::assertSame($failure, $cache->resolve('field', $load));
            static::assertSame($metadata, $cache->resolve('field', $load));
            static::assertSame($metadata, $cache->resolve('field', $load));
            static::assertSame(2, $counter->calls);
        }
    }

    public function testLoaderExceptionsAreRetried(): void
    {
        /** @var MetadataCache<array<never, never>> $cache */
        $cache = new MetadataCache();
        $failure = new RuntimeException('metadata unavailable');
        try {
            $cache->resolve('field', /** @throws RuntimeException */ static fn(): never => throw $failure);
            static::fail('The loader exception must propagate.');
        } catch (RuntimeException $caught) {
            static::assertSame($failure, $caught);
        }

        static::assertSame([], $cache->resolve('field', /** @return array<never, never> */ static fn(): array => []));
    }
}
