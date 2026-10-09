<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

use function array_any;
use function count;
use function enum_exists;
use function in_array;
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
        $native = $field->getType();
        $ordinaryClass =
            $native instanceof ReflectionNamedType && !$native->isBuiltin() && !enum_exists($native->getName());
        if ($ordinaryClass || !ScalarPhpDocPresence::has($field)) {
            return null;
        }
        $type = PhpDocFieldType::resolve($field);
        if ($type === null) {
            return null;
        }
        if (FieldTypeNameResolver::primitivePhpDoc($type->name) !== null) {
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
        $hasLiteral =
            count($names) === 1 && $names[0] === 'null'
            || array_any(
                $names,
                static fn(string $name): bool => (
                    in_array($name, ['true', 'false'], strict: true) || PhpDocType::literalSyntax($name)
                ),
            );
        return $hasLiteral ? $names : null;
    }
}
