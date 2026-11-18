<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Closure;
use Eventjet\Json\JsonType;
use PHPUnit\Framework\Assert;

use function is_object;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * A JSON document, the type it decodes to, and what the result must be.
 *
 * The JSON and the type are closures, so that the test encodes values and builds types. Code that runs in a data
 * provider does not count as covered.
 *
 * @internal
 */
final readonly class DecodeSuccess
{
    /**
     * @param Closure(): string $json
     * @param Closure(): (class-string|JsonType<list<mixed>|object>) $type
     * @param Closure(mixed, string): void $verify Checks the decoded value against the JSON it was decoded from.
     * @param (Closure(): void)|null $prepare Defines what the type needs after a first decode failed without it.
     */
    private function __construct(
        public Closure $json,
        public Closure $type,
        public Closure $verify,
        public Closure|null $prepare = null,
    ) {}

    public static function roundTrip(object $original): self
    {
        return new self(
            /** @throws \JsonException */
            static fn(): string => json_encode($original, JSON_THROW_ON_ERROR),
            static fn(): string => $original::class,
            self::equalsLosslessly(static fn(): object => $original),
        );
    }

    /** @param Closure(): JsonType<list<mixed>|object> $type */
    public static function rootRoundTrip(mixed $original, Closure $type): self
    {
        return new self(
            /** @throws \JsonException */
            static fn(): string => json_encode($original, JSON_THROW_ON_ERROR),
            $type,
            self::equalsLosslessly(static fn(): mixed => $original),
        );
    }

    public static function document(string $json, object $expected): self
    {
        return new self(
            static fn(): string => $json,
            static fn(): string => $expected::class,
            self::equalsLosslessly(static fn(): object => $expected),
        );
    }

    /**
     * @param class-string $class
     * @param callable(object): bool $check
     */
    public static function satisfying(string $json, string $class, callable $check): self
    {
        return new self(
            static fn(): string => $json,
            static fn(): string => $class,
            /** @throws \JsonException */
            static function (mixed $decoded, string $json) use ($check): void {
                Assert::assertTrue(is_object($decoded) && $check($decoded));
                Assert::assertJsonStringEqualsJsonString($json, json_encode($decoded, JSON_THROW_ON_ERROR));
            },
        );
    }

    public static function lossy(string $json, object $expected): self
    {
        return new self(
            static fn(): string => $json,
            static fn(): string => $expected::class,
            static function (mixed $decoded) use ($expected): void {
                Assert::assertEquals($expected, $decoded);
            },
        );
    }

    /**
     * Decodes the document once without what the type needs, then again after defining it.
     *
     * @param Closure(): (class-string|JsonType<list<mixed>|object>) $type
     * @param Closure(): mixed $expected Builds the expected value once the type is defined.
     * @param Closure(): void $define
     */
    public static function afterDefining(string $json, Closure $type, Closure $expected, Closure $define): self
    {
        return new self(static fn(): string => $json, $type, self::equalsLosslessly($expected), $define);
    }

    /**
     * @param Closure(): mixed $expected
     * @return Closure(mixed, string): void
     */
    private static function equalsLosslessly(Closure $expected): Closure
    {
        return (
            /** @throws \JsonException */
            static function (mixed $decoded, string $json) use ($expected): void {
                Assert::assertEquals($expected(), $decoded);
                Assert::assertJsonStringEqualsJsonString($json, json_encode($decoded, JSON_THROW_ON_ERROR));
            }
        );
    }
}
