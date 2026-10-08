<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;
use stdClass;

use function array_any;
use function class_exists;
use function enum_exists;
use function get_debug_type;
use function is_int;
use function is_string;

/** @internal */
final class PhpDocLiteralField
{
    /**
     * @return list<string>|null
     * @throws ReflectionException
     */
    public static function resolve(ReflectionParameter|ReflectionProperty $field): array|null
    {
        $type = PhpDocFieldType::resolve($field);
        if ($type === null) {
            return null;
        }
        $members = $type->name === '|' ? $type->arguments : [$type];
        $names = [];
        foreach ($members as $member) {
            $resolved = PhpDocItemTypeResolver::resolve($field, $member);
            if ($resolved instanceof CollectionUnionType) {
                $names = [...$names, ...$resolved->names()];
                continue;
            }
            if (!is_string($resolved)) {
                return null;
            }
            $names[] = $resolved;
        }
        $hasLiteral = array_any(
            $names,
            static fn(string $name): bool => $name === 'null' || PhpDocLiteral::value($name) !== null,
        );
        return $hasLiteral ? $names : null;
    }

    /** @param list<string> $names */
    public static function matches(array $names, mixed $value): bool
    {
        foreach ($names as $name) {
            $matches = PhpDocLiteral::matches($name, $value);
            if ($matches || $name === get_debug_type($value)) {
                return true;
            }
            if ($name === 'float' && is_int($value) || enum_exists($name)) {
                return true;
            }
            if ($value instanceof stdClass && class_exists($name)) {
                return true;
            }
        }
        return false;
    }
}
