<?php

declare(strict_types=1);

namespace Eventjet\Json\Benchmark;

use Closure;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Json;
use JsonException;
use Override;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\ExpectationFailedException;
use RuntimeException;

use function is_object;
use function json_encode;
use function strlen;

use const JSON_THROW_ON_ERROR;

/** @internal */
final readonly class DocumentWorkload implements RequestWorkload
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

    /** @throws DecodeError */
    #[Override]
    public function run(): object
    {
        $decoded = Json::decode($this->json, $this->class);
        if ($decoded instanceof DecodeError) {
            throw $decoded;
        }
        return $decoded;
    }

    /**
     * @throws ExpectationFailedException
     * @throws JsonException
     * @throws RuntimeException
     */
    #[Override]
    public function verify(mixed $result): void
    {
        if (!is_object($result)) {
            throw new RuntimeException('The document did not decode to an object.');
        }
        $isHydrated = ($this->isHydrated)($result);
        if (!$isHydrated) {
            throw new RuntimeException('The document was not fully hydrated.');
        }
        $encoded = json_encode($result, JSON_THROW_ON_ERROR);
        Assert::assertJsonStringEqualsJsonString($this->json, $encoded);
    }

    #[Override]
    public function inputBytes(): int
    {
        return strlen($this->json);
    }
}
