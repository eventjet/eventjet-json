<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function count;
use function in_array;
use function preg_match;

/** @internal */
final class PhpDocItemTypeResolver
{
    /**
     * @return string|CollectionUnionType|NestedCollectionType|null
     * @throws ReflectionException
     */
    public static function resolve(
        ReflectionParameter|ReflectionProperty $field,
        PhpDocType $type,
    ): string|CollectionUnionType|NestedCollectionType|null {
        $nested = NestedCollectionTypeResolver::resolve($field, $type);
        if ($nested !== null) {
            return $nested;
        }

        if ($type->name === '|') {
            return PhpDocUnionTypeResolver::resolve($field, $type->arguments);
        }

        return self::named($field, $type);
    }

    /** @throws ReflectionException */
    private static function named(
        ReflectionParameter|ReflectionProperty $field,
        PhpDocType $type,
    ): string|CollectionUnionType|null {
        $arguments = $type->arguments;

        if ($arguments === []) {
            $name = $type->name;
            if (in_array($name, ['null', 'true', 'false'], strict: true)) {
                return $name;
            }
            $resolved = PhpDocType::literalSyntax($name) ? null : FieldTypeNameResolver::resolvePhpDoc($field, $name);
            return $resolved ?? PhpDocLiteral::itemType($field, $name);
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
