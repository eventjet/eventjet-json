<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Closure;

/** @internal Compiled scalar-object matchers and constructors. */
final readonly class DirectScalarPlan
{
    /**
     * @mago-expect lint:excessive-parameter-list Immutable compiled data record; each matcher and closure has a distinct type and role.
     * @param non-empty-string $unorderedPattern
     * @param non-empty-string $orderedPattern
     * @param non-empty-string $windowPattern
     * @param non-empty-string $fusedPattern
     * @param non-empty-string|false $projectionPattern
     * @param non-empty-string|false $asciiProjectionPattern
     * @param Closure(array<array-key, string|null>, bool): (object|false) $factory
     * @param Closure(array<array-key, string|null>, bool): (object|false) $orderedFactory
     * @param Closure(array<array-key, array<array-key, string|null>>, array<array-key, mixed>, int, int): bool $columns
     */
    public function __construct(
        public string $unorderedPattern,
        public string $orderedPattern,
        public string $windowPattern,
        public string $fusedPattern,
        public string|false $projectionPattern,
        public string|false $asciiProjectionPattern,
        public Closure $factory,
        public Closure $orderedFactory,
        public Closure $columns,
    ) {}
}
