<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use JsonException;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

/** @internal */
final readonly class LiteralFieldValueConverter
{
    private FieldValueConverter $converter;

    /**
     * @param list<string> $literals
     * @throws ReflectionException
     */
    public function __construct(
        ReflectionParameter|ReflectionProperty $field,
        ListType|MapType|TupleType|CollectionUnionType|null $collection,
        private array $literals,
    ) {
        $this->converter = new FieldValueConverter($field, $collection);
    }

    /**
     * @param class-string $class
     * @param array<array-key, mixed>|bool|float|int|object|string|null $value
     * @return array<array-key, mixed>|bool|float|int|object|string|null
     * @throws JsonException
     * @throws ReflectionException
     */
    public function convert(string $class, mixed $value, string $path): array|bool|float|int|object|string|null
    {
        $value = PhpDocLiteralScalarConverter::convert($class, $path, $this->literals, $value);
        return $value instanceof DecodeError ? $value : $this->converter->convert($class, $value, $path);
    }
}
