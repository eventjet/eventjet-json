<?php

declare(strict_types=1);

namespace Eventjet\Json\Schema;

/**
 * One reason why a value doesn't match a schema, in the shape of an entry of the "basic" output format of JSON Schema.
 *
 * @api
 */
final readonly class ValidationFailure
{
    /** @internal */
    public function __construct(
        /** A JSON Pointer to the part of the value that failed, such as `/items/0`. */
        public string $instanceLocation,
        /** A JSON Pointer to the keyword that failed, along the path through references, such as `/$ref/type`. */
        public string $keywordLocation,
        /** The URI of the keyword that failed, in the schema resource that holds it. */
        public string $absoluteKeywordLocation,
        /** What failed, in English. */
        public string $message,
    ) {}

    public function __toString(): string
    {
        return (
            ($this->instanceLocation === '' ? '(root)' : $this->instanceLocation)
            . ': '
            . $this->message
            . ' ('
            . $this->keywordLocation
            . ')'
        );
    }
}
