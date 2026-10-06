<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionParameter;
use ReflectionProperty;

use function count;
use function preg_match;

/** @internal */
final class PhpDocItemTypeResolver
{
    /** @return 'bool'|'float'|'int'|'string'|class-string|null */
    public static function resolve(ReflectionParameter|ReflectionProperty $field, PhpDocType $type): string|null
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
