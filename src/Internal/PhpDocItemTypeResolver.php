<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function count;
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

        $named = self::named($field, $type);
        if ($named !== null) {
            return $named;
        }
        $constants = $type->arguments === [] ? PhpDocConstantResolver::resolve($field, $type->name) : null;
        return $constants === null ? null : new CollectionUnionType($constants);
    }

    /** @return string|null */
    private static function named(ReflectionParameter|ReflectionProperty $field, PhpDocType $type): string|null
    {
        $arguments = $type->arguments;

        if ($arguments === []) {
            $literalSyntax = preg_match('/\A(?:[\x27"0-9.+-]|true\z|false\z|null\z)|::/', $type->name) === 1;
            $literal = $literalSyntax ? PhpDocLiteral::value($type->name) : null;
            if ($literal !== null || $type->name === 'null') {
                return $type->name;
            }
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
