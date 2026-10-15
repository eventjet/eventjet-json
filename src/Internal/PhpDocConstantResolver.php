<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use BackedEnum;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function constant;
use function defined;
use function enum_exists;
use function explode;
use function in_array;
use function str_contains;
use function strtolower;

/** @internal */
final class PhpDocConstantResolver
{
    /**
     * Returns the literals, or `null`, that a PHPDoc name stands for: `true`, `false`, or the values of the constants it
     * refers to. Returns null if it is none of these. Callers handle the name `null` themselves.
     *
     * @return non-empty-list<PhpDocLiteral|'null'>|null
     * @throws ReflectionException
     */
    public static function literals(ReflectionParameter|ReflectionProperty $field, string $name): array|null
    {
        return (
            in_array($name, ['true', 'false'], strict: true)
                ? [new PhpDocLiteral($name === 'true', $name)]
                : self::resolve($field, $name)
        );
    }

    /** Whether a name stands for literals by its syntax: `true`, `false`, or a class constant such as `Foo::BAR`. */
    public static function namesLiteral(string $name): bool
    {
        return in_array($name, ['true', 'false'], strict: true) || str_contains($name, '::');
    }

    /**
     * @return non-empty-list<PhpDocLiteral|'null'>|null
     * @throws ReflectionException
     */
    private static function resolve(ReflectionParameter|ReflectionProperty $field, string $name): array|null
    {
        /** @var ReflectionClass<object> $declaring */
        $declaring = $field->getDeclaringClass();
        if (!str_contains($name, '::')) {
            $imports = PhpDocImports::forClass($declaring);
            $qualified = $imports['const:' . $name] ?? PhpDocClassNameResolver::resolve($declaring, $name);
            $constant = defined($qualified) ? $qualified : $name;
            /** @mago-expect analysis:unhandled-thrown-type Global constants are initialized and checked with defined() before lookup. */
            $value = defined($constant) ? PhpDocConstantValues::literal(constant($constant)) : null;
            return $value === null ? null : [$value];
        }
        /** @var array{string, string} $parts */
        $parts = explode('::', $name);
        $owner = $parts[0];
        $member = $parts[1];
        if (enum_exists($owner) && defined($name)) {
            /** @var mixed $value */
            $value = constant($name);
            // An enum case keeps its name as written.
            if ($value instanceof BackedEnum) {
                return [new PhpDocLiteral($value, $name)];
            }
        }
        $parent = $declaring->getParentClass();
        $class = match (strtolower($owner)) {
            'self', 'static' => $declaring->getName(),
            'parent' => $parent === false ? 'parent' : $parent->getName(),
            default => PhpDocClassNameResolver::resolve($declaring, $owner),
        };
        if (strtolower($member) === 'class') {
            $value = PhpDocConstantValues::literal($class);
            return $value === null ? null : [$value];
        }
        return PhpDocConstantValues::resolve($class, $member);
    }
}
