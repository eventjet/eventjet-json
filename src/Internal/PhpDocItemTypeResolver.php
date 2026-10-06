<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function array_unique;
use function array_values;
use function class_exists;
use function count;
use function in_array;
use function interface_exists;
use function preg_match;

/** @internal */
final class PhpDocItemTypeResolver
{
    /**
     * @return 'bool'|'float'|'int'|'string'|class-string|CollectionUnionType|null
     * @throws ReflectionException
     */
    public static function resolve(
        ReflectionParameter|ReflectionProperty $field,
        PhpDocType $type,
    ): string|CollectionUnionType|null {
        if ($type->name === '|') {
            $members = [];
            foreach ($type->arguments as $member) {
                $resolved = in_array($member->name, ['null', 'true', 'false'], strict: true)
                && $member->arguments === []
                    ? $member->name
                    : self::named($field, $member);
                if ($resolved === null) {
                    return null;
                }
                $members[] = class_exists($resolved) || interface_exists($resolved)
                    ? new ReflectionClass($resolved)->getName()
                    : $resolved;
            }
            return new CollectionUnionType(array_values(array_unique($members)));
        }

        return self::named($field, $type);
    }

    /** @return 'bool'|'float'|'int'|'string'|class-string|null */
    private static function named(ReflectionParameter|ReflectionProperty $field, PhpDocType $type): string|null
    {
        $arguments = $type->arguments;

        if ($arguments === []) {
            return FieldTypeNameResolver::resolvePhpDoc($field, $type->name);
        }

        if ($type->name !== 'int' || count($arguments) !== 2) {
            return null;
        }

        foreach ($arguments as $index => $bound) {
            $endpoint = $index === 0 ? 'min' : 'max';
            $valid = preg_match('/\A(?:' . $endpoint . '|-?[0-9]+)\z/', $bound->name) === 1;

            if ($bound->arguments !== [] || !$valid) {
                return null;
            }
        }

        return 'int';
    }
}
