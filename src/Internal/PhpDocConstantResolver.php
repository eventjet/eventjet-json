<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function constant;
use function defined;
use function explode;
use function in_array;
use function str_contains;
use function strtolower;

/** @internal */
final class PhpDocConstantResolver
{
    /** @throws ReflectionException */
    public static function itemType(
        ReflectionParameter|ReflectionProperty $field,
        string $name,
    ): string|CollectionUnionType|null {
        $literal = in_array($name, ['null', 'true', 'false'], strict: true) || PhpDocLiteral::value($name) !== null;
        if ($literal) {
            return $name;
        }
        $constants = self::resolve($field, $name);
        return $constants === null ? null : new CollectionUnionType($constants);
    }

    /**
     * @return list<string>|null
     * @throws ReflectionException
     */
    private static function resolve(ReflectionParameter|ReflectionProperty $field, string $name): array|null
    {
        /** @var ReflectionClass<object> $declaring */
        $declaring = $field->getDeclaringClass();
        if (!str_contains($name, '::')) {
            $imports = PhpDocImports::load($declaring, PhpDocConstantImportStatement::class);
            $qualified = $imports[$name] ?? PhpDocClassNameResolver::resolve($declaring, $name);
            $constant = defined($qualified) ? $qualified : $name;
            /** @mago-expect analysis:unhandled-thrown-type Global constants are initialized and checked with defined() before lookup. */
            $value = defined($constant) ? PhpDocConstantValues::name(constant($constant)) : null;
            return $value === null ? null : [$value];
        }
        /** @var array{string, string} $parts */
        $parts = explode('::', $name);
        $owner = $parts[0];
        $member = $parts[1];
        $parent = $declaring->getParentClass();
        $class = match (strtolower($owner)) {
            'self', 'static' => $declaring->getName(),
            'parent' => $parent === false ? 'parent' : $parent->getName(),
            default => PhpDocClassNameResolver::resolve($declaring, $owner),
        };
        if (strtolower($member) === 'class') {
            $value = PhpDocConstantValues::name($class);
            return $value === null ? null : [$value];
        }
        return PhpDocConstantValues::resolve($class, $member);
    }
}
