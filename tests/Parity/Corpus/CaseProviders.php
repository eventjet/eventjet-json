<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity\Corpus;

use ReflectionClass;
use ReflectionMethod;

use function class_exists;
use function is_iterable;
use function is_string;
use function preg_match;

/**
 * Runs the data providers of a case class, so that the fixture classes they create are declared.
 *
 * @internal
 */
final class CaseProviders
{
    /**
     * Calls every public static method without parameters, such as the data providers, and loads the classes named in
     * the values it returns.
     *
     * @throws \ReflectionException
     */
    public static function run(string $class): void
    {
        if (!class_exists($class)) {
            return;
        }
        foreach (new ReflectionClass($class)->getMethods(ReflectionMethod::IS_STATIC) as $method) {
            $provider = $method->isPublic() && $method->getNumberOfRequiredParameters() === 0;
            if ($provider && $method->getDeclaringClass()->getName() === $class) {
                self::loadClassesIn($method->invoke(null), depth: 3);
            }
        }
    }

    private static function loadClassesIn(mixed $value, int $depth): void
    {
        $name = is_string($value) && preg_match('~\A\\\\?+[A-Za-z_][\\\\A-Za-z0-9_]*+\z~', $value) === 1;
        if ($name && class_exists($value) || $depth === 0 || !is_iterable($value)) {
            return;
        }
        /** @var mixed $item */
        foreach ($value as $item) {
            self::loadClassesIn($item, $depth - 1);
        }
    }
}
