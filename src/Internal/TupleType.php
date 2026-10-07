<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Override;
use Stringable;

use function implode;
use function sprintf;

/** @internal */
final readonly class TupleType implements Stringable
{
    /** @param list<'bool'|'float'|'int'|'string'|class-string|CollectionUnionType> $types */
    public function __construct(
        public array $types,
        public int $required,
    ) {}

    #[Override]
    public function __toString(): string
    {
        $entries = [];
        foreach ($this->types as $index => $type) {
            $name = $type instanceof CollectionUnionType ? $type->__toString() : $type;
            $entries[] = sprintf('%d%s: %s', $index, $index >= $this->required ? '?' : '', $name);
        }
        return 'array{' . implode(', ', $entries) . '}';
    }
}
