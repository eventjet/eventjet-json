<?php

declare(strict_types=1);

namespace Eventjet\Json\Benchmark;

use Closure;
use JsonException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\ExpectationFailedException;
use RuntimeException;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/** @internal */
final readonly class DocumentWorkload
{
    /**
     * @param class-string $class
     * @param Closure(object): bool $isHydrated
     */
    public function __construct(
        public string $json,
        public string $class,
        private Closure $isHydrated,
    ) {}

    /**
     * @throws ExpectationFailedException
     * @throws JsonException
     * @throws RuntimeException
     */
    public function verify(object $decoded): void
    {
        $isHydrated = ($this->isHydrated)($decoded);
        if (!$isHydrated) {
            throw new RuntimeException('The document was not fully hydrated.');
        }
        $encoded = json_encode($decoded, JSON_THROW_ON_ERROR);
        Assert::assertJsonStringEqualsJsonString($this->json, $encoded);
    }
}
