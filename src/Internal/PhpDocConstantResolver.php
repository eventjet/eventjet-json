<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Error;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function constant;
use function defined;
use function explode;
use function ltrim;
use function str_contains;
use function str_starts_with;
use function strtolower;

/** @internal */
final class PhpDocConstantResolver
{
    /**
     * @return list<string>|null
     * @throws ReflectionException
     */
    public static function resolve(ReflectionParameter|ReflectionProperty $field, string $name): array|null
    {
        /** @var ReflectionClass<object> $declaring */
        $declaring = $field->getDeclaringClass();
        if (!str_contains($name, '::')) {
            $imports = PhpDocImports::forClass($declaring, kind: PhpDocImportKind::Constant);
            $qualified = str_starts_with($name, '\\')
                ? ltrim($name, characters: '\\')
                : $imports[$name] ?? PhpDocClassNameResolver::resolve($declaring, $name);
            $constant = defined($qualified) ? $qualified : $name;
            try {
                return defined($constant) ? PhpDocConstantValues::names(constant($constant)) : null;

                /** @mago-expect analysis:avoid-catching-error Invalid PHPDoc constant references must not crash decoding. */
            } catch (Error) {
                return null;
            }
        }
        /** @var array{string, string} $parts */
        $parts = explode('::', $name, limit: 2);
        $owner = $parts[0];
        $member = $parts[1];
        $parent = $declaring->getParentClass();
        $class = match ($owner) {
            'self', 'static' => $declaring->getName(),
            'parent' => ($parent === false ? $declaring : $parent)->getName(),
            default => PhpDocClassNameResolver::resolve($declaring, $owner),
        };
        if (strtolower($member) === 'class') {
            return PhpDocConstantValues::names($class);
        }
        return PhpDocConstantValues::resolve($class, $member);
    }
}
