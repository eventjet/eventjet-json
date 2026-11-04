<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Schema;

/** @internal */
interface Keyword
{
    /**
     * @param string $instanceLocation A JSON Pointer to the value, if the run collects failures.
     * @param string $keywordLocation The keyword location of the schema that holds this keyword.
     * @param Annotations|null $annotations What the schema evaluated of the value so far, if the run tracks it.
     */
    public function check(
        mixed $value,
        Run $run,
        string $instanceLocation,
        string $keywordLocation,
        Annotations|null $annotations,
    ): bool;

    /** @return list<Node> The subschemas that apply to the same value, for finding reference loops. */
    public function inPlace(): array;
}
