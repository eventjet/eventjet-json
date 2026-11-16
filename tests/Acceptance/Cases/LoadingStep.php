<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Closure;
use Eventjet\Json\DecodeError;
use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\JsonType;
use PHPUnit\Framework\Assert;

use function array_map;
use function class_exists;
use function in_array;
use function json_encode;
use function str_starts_with;

use const JSON_THROW_ON_ERROR;

/**
 * Decodes documents, then checks which classes the decodes loaded and which names they asked autoloaders for.
 *
 * @internal
 */
final readonly class LoadingStep
{
    /**
     * @param list<array{string, class-string|JsonType<list<mixed>|object>, Closure(mixed): void}> $decodes JSON, type, and a check
     *     of the result.
     * @param list<string> $unloaded Classes that the decodes must neither load nor ask autoloaders for.
     * @param list<string> $loaded Classes that must be loaded after the decodes.
     * @param bool $libraryOnly Whether the decodes may ask autoloaders for the library's own classes only.
     */
    public function __construct(
        public array $decodes,
        public array $unloaded = [],
        public array $loaded = [],
        public bool $libraryOnly = false,
    ) {}

    /** @param list<string> $requested The names that the decodes asked autoloaders for. */
    public function assertLoading(array $requested): void
    {
        foreach ($this->unloaded as $class) {
            Assert::assertFalse(class_exists($class, autoload: false), $class);
            Assert::assertFalse(in_array($class, $requested, strict: true), $class);
        }
        foreach ($this->loaded as $class) {
            Assert::assertTrue(class_exists($class, autoload: false), $class);
        }
        if (!$this->libraryOnly) {
            return;
        }
        foreach ($requested as $class) {
            Assert::assertTrue(
                str_starts_with($class, 'Eventjet\\Json\\') && !str_starts_with($class, 'Eventjet\\Json\\Test\\'),
                $class,
            );
        }
    }

    /**
     * @param list<object> $objects
     * @return list<array{string, class-string, Closure(mixed): void}>
     * @throws \JsonException
     */
    public static function roundTrips(array $objects): array
    {
        return array_map(
            /**
             * @return array{string, class-string, Closure(mixed): void}
             * @throws \JsonException
             */
            static fn(object $object): array => [
                json_encode($object, JSON_THROW_ON_ERROR),
                $object::class,
                self::equals($object),
            ],
            $objects,
        );
    }

    /** @return Closure(mixed): void */
    public static function equals(object $expected): Closure
    {
        return static function (mixed $decoded) use ($expected): void {
            Assert::assertEquals($expected, $decoded);
        };
    }

    /** @return Closure(mixed): void */
    public static function fails(DecodeErrorKind $kind): Closure
    {
        return static function (mixed $decoded) use ($kind): void {
            Assert::assertTrue($decoded instanceof DecodeError);
            Assert::assertSame($kind, $decoded->kind);
        };
    }
}
