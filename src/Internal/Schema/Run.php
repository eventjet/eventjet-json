<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

use Eventjet\Json\Schema\ValidationFailure;

use function array_slice;
use function count;
use function is_int;

/**
 * The state of one validation: the failures found so far and the dynamic scope.
 *
 * @internal
 */
final class Run
{
    /** @var list<ValidationFailure> */
    public array $failures = [];

    /** @var list<Resource> */
    public array $scope = [];

    /** The last resource of the scope. */
    public Resource|null $innermost = null;

    public function __construct(
        /** Whether to list every failure. Without, evaluation stops at the first one. */
        public readonly bool $collect,
        public readonly bool $annotate,
        public readonly bool $dynamic,
    ) {}

    public function fail(
        string $instanceLocation,
        string $keywordLocation,
        string $absoluteLocation,
        string $message,
    ): false {
        if ($this->collect) {
            $this->failures[] = new ValidationFailure($instanceLocation, $keywordLocation, $absoluteLocation, $message);
        }
        return false;
    }

    /** Drops the failures found since the given count, for subschemas whose failure doesn't fail their parent. */
    public function rollBack(int $count): void
    {
        if ($this->collect && count($this->failures) > $count) {
            $this->failures = array_slice($this->failures, offset: 0, length: $count);
        }
    }

    /**
     * Appends reference tokens to a JSON Pointer, if the run collects failures. Without, locations are never read, so
     * this skips building them.
     */
    public function path(string $pointer, string|int ...$tokens): string
    {
        return $this->collect ? self::append($pointer, $tokens) : '';
    }

    public function mark(): int
    {
        return $this->collect ? count($this->failures) : 0;
    }

    /** @param array<array-key, string|int> $tokens */
    private static function append(string $pointer, array $tokens): string
    {
        foreach ($tokens as $token) {
            $pointer .= '/' . (is_int($token) ? $token : Uri::escapePointerToken($token));
        }
        return $pointer;
    }
}
