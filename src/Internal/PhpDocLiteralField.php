<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function array_any;
use function count;
use function is_string;
use function preg_match;

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
        $hasLiteral =
            count($names) === 1 && $names[0] === 'null'
            || array_any(
                $names,
                static fn(string $name): bool => preg_match('/\A(?:[\x27"0-9.+-]|true\z|false\z)|::/', $name) === 1,
            );
        return $hasLiteral ? $names : null;
    }
}
