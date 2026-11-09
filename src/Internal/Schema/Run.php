<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

use Eventjet\Json\Schema\ValidationFailure;

use function array_splice;
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

    public function fail(
        string $instanceLocation,
        string $keywordLocation,
        string $absoluteLocation,
        string $message,
    ): false {
        $this->failures[] = new ValidationFailure($instanceLocation, $keywordLocation, $absoluteLocation, $message);
        return false;
    }

    /** Drops the failures found since the given count, for subschemas whose failure doesn't fail their parent. */
    public function rollBack(int $count): void
    {
        array_splice($this->failures, $count);
    }

    /**
     * Returns whether the value matches a subschema whose failures never count, such as the one of `not`. Because they
     * are dropped, it builds no locations for them.
     */
    public function quietly(Node $schema, mixed $value, Annotations|null $annotations): bool
    {
        $mark = $this->mark();
        $matches = $schema->evaluate($value, $this, '', '', $annotations);
        $this->rollBack($mark);
        return $matches;
    }

    /** Appends reference tokens to a JSON Pointer. */
    public function path(string $pointer, string|int ...$tokens): string
    {
        foreach ($tokens as $token) {
            $pointer .= '/' . (is_int($token) ? $token : Uri::escapePointerToken($token));
        }
        return $pointer;
    }

    public function mark(): int
    {
        return count($this->failures);
    }
}
