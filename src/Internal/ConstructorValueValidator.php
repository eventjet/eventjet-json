<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionNamedType;

use function enum_exists;

/** @internal */
final readonly class ConstructorValueValidator
{
    private function __construct(
        public string $typeName,
        public bool $nullable,
        public string $expected,
    ) {}

    public static function forParameter(ConstructorParameter $parameter): self|null
    {
        $type = $parameter->type;
        if (!$type instanceof ReflectionNamedType) {
            return null;
        }
        if (!$parameter->builtin && enum_exists($parameter->typeName)) {
            return null;
        }
        return new self(
            $type->getName(),
            $type->allowsNull(),
            $type->allowsNull() ? FieldTypeNameResolver::withNull($parameter->typeName) : $parameter->typeName,
        );
    }
}
