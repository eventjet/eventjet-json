<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use BackedEnum;
use Eventjet\Json\DecodeError;
use ReflectionEnum;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use stdClass;

use function enum_exists;
use function get_debug_type;
use function is_int;
use function is_string;

/** @internal */
final readonly class EnumUnionLookup
{
    /** @var array<string, array<int|string, BackedEnum>> */
    private array $cases;
    /** @var array<string, non-empty-list<enum-string>> */
    private array $enums;

    /** @throws ReflectionException */
    public function __construct(ReflectionType|null $type)
    {
        $cases = [];
        $enums = [];
        foreach ($type instanceof ReflectionUnionType ? $type->getTypes() : [] as $member) {
            $enum = (string) $member;
            if (!enum_exists($enum)) {
                continue;
            }
            $reflection = new ReflectionEnum($enum);
            $backingType = $reflection->getBackingType();
            if ($backingType === null) {
                continue;
            }
            $enums[(string) $backingType][] = $enum;
            foreach ($reflection->getCases() as $case) {
                /** @var BackedEnum $value */
                $value = $case->getValue();
                $cases[get_debug_type($value->value)][$value->value] = $value;
            }
        }
        $this->cases = $cases;
        $this->enums = $enums;
    }

    /** @param class-string $class */
    private function convert(string $class, mixed $value, string $path): BackedEnum|DecodeError|null
    {
        $enums = $this->enums[get_debug_type($value)] ?? null;
        if ($enums === null) {
            return null;
        }
        return $this->find($value) ?? BackedEnumValueConverter::unknownUnionValue($class, $path, $enums, $value);
    }

    public function find(mixed $value): BackedEnum|null
    {
        if (!is_int($value) && !is_string($value)) {
            return null;
        }
        return $this->cases[get_debug_type($value)][$value] ?? null;
    }

    /**
     * @param class-string $class
     * @param array<array-key, mixed>|bool|float|int|object|string|null $value
     * @return array<array-key, mixed>|bool|float|int|object|string|null
     * @throws ReflectionException
     */
    public function convertField(
        string $class,
        ReflectionParameter|ReflectionProperty $field,
        ReflectionUnionType $type,
        mixed $value,
        string $path,
    ): array|bool|float|int|object|string|null {
        $converted = $this->convert($class, $value, $path);
        if ($converted !== null) {
            return $converted;
        }
        if ($value instanceof stdClass) {
            $converted = ConcreteClassUnionValueConverter::convert($class, $field, $type, $value, $path);
            if ($converted !== null) {
                return $converted;
            }
        }
        $matches = ValueTypeMatcher::matchesBuiltinUnion($value, $type);
        if (!$matches) {
            return DecodeError::fieldTypeMismatch($class, $path, (string) $type, $value);
        }
        return $value;
    }
}
