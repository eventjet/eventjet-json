<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionNamedType;

use function array_key_exists;
use function enum_exists;

/** @internal */
final readonly class ConstructorValueValidator
{
    public string $typeName;
    public bool $nullable;

    private function __construct(
        private ReflectionNamedType $type,
        public string $expected,
    ) {
        $this->typeName = $type->getName();
        $this->nullable = $type->allowsNull();
    }

    /** @param array<array-key, mixed> $values */
    public static function forParameter(ConstructorParameter $parameter, array $values): self|null
    {
        $type = $parameter->type;
        if (!$type instanceof ReflectionNamedType) {
            return null;
        }
        if (enum_exists(
            $parameter->typeName,
            autoload: array_key_exists($parameter->name, $values) && !$parameter->builtin,
        )) {
            return null;
        }
        $expected = FieldTypeNameResolver::expected($type, $parameter->typeName);
        return new self($type, $expected);
    }

    /** @param class-string $class */
    public function validate(string $class, string $name, mixed $value, string $path): DecodeError|null
    {
        $matches = ValueTypeMatcher::matches($value, $this->type);
        if ($matches) {
            return null;
        }
        return DecodeError::fieldTypeMismatch($class, FieldPath::field($path, $name), $this->expected, $value);
    }
}
