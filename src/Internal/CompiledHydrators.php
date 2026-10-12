<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Closure;
use ReflectionException;
use stdClass;

use function is_int;

/** @internal */
final class CompiledHydrators
{
    /** @var array<class-string, (Closure(stdClass): (object|null))|int|null> */
    private static array $hydrators = [];

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function hydrate(string $class, stdClass $object): object|null
    {
        $hydrator = self::$hydrators[$class] ?? 0;
        if (is_int($hydrator)) {
            // Short requests cannot amortize reflection and runtime compilation.
            if (++$hydrator < 128) {
                self::$hydrators[$class] = $hydrator;
                return null;
            }
            $hydrator = ScalarHydratorCompiler::compile($class);
            self::$hydrators[$class] = $hydrator;
        }
        return $hydrator === null ? null : $hydrator($object);
    }
}
