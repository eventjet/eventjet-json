<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

use stdClass;

use function array_pop;
use function is_array;

/**
 * A compiled schema.
 *
 * @internal
 */
final class Node
{
    /** @var list<Keyword> */
    public array $keywords = [];

    public function __construct(
        public readonly Resource $resource,
        public readonly string $absoluteLocation,
        /** The value of a Boolean schema, or null for a schema object. */
        public readonly bool|null $constant = null,
    ) {}

    public function evaluate(
        mixed $value,
        Run $run,
        string $instanceLocation,
        string $keywordLocation,
        Annotations|null $parent,
    ): bool {
        if ($this->constant === false) {
            return $run->fail(
                $instanceLocation,
                $keywordLocation,
                $this->absoluteLocation,
                'No value is allowed here.',
            );
        }
        $annotations = $value instanceof stdClass || is_array($value) ? new Annotations() : null;
        $pushed = false;
        $outer = null;
        if ($run->innermost !== $this->resource) {
            $outer = $run->innermost;
            $run->scope[] = $this->resource;
            $run->innermost = $this->resource;
            $pushed = true;
        }
        $valid = true;
        foreach ($this->keywords as $keyword) {
            $passed = $keyword->check($value, $run, $instanceLocation, $keywordLocation, $annotations);
            if ($passed) {
                continue;
            }
            $valid = false;
        }
        if ($pushed) {
            array_pop($run->scope);
            $run->innermost = $outer;
        }
        if ($valid && $parent !== null && $annotations !== null) {
            $parent->merge($annotations);
        }
        return $valid;
    }
}
