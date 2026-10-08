<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use stdClass;

use function implode;
use function var_export;

/** @internal */
final class ScalarHydratorCompiler
{
    /**
     * @param class-string $class
     * @return (Closure(stdClass): (object|null))|null
     * @throws ReflectionException
     * @psalm-suppress UnusedParam The reflected class feeds generated source, whose use Psalm cannot trace through eval.
     */
    public static function compile(string $class): Closure|null
    {
        $reflection = new ReflectionClass($class);
        if ($reflection->isAnonymous()) {
            return null;
        }
        $checks = [];
        $arguments = [];
        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            $type = $parameter->getType();
            if (!$type instanceof ReflectionNamedType || !$type->isBuiltin()) {
                return null;
            }
            $name = var_export($parameter->getName(), return: true);
            $value = '$object->{' . $name . '}';
            $check = match ($type->getName()) {
                'string' => 'is_string(' . $value . ')',
                'int' => 'is_int(' . $value . ')',
                'bool' => 'is_bool(' . $value . ')',
                'float' => '(is_float(' . $value . ') || is_int(' . $value . '))',
                'true' => $value . ' === true',
                'false' => $value . ' === false',
                'null' => $value . ' === null',
                default => null,
            };
            if ($check === null) {
                return null;
            }
            if ($type->allowsNull()) {
                $check = '(' . $value . ' === null || ' . $check . ')';
            }
            $checks[] = '(property_exists($object, ' . $name . ') && (' . $check . '))';
            $arguments[] = $value;
        }
        $condition = $checks === [] ? 'true' : implode(' && ', $checks);
        // Only reflected PHP declarations enter the source. JSON values are passed to the resulting closure.
        // Use the canonical name so aliases and leading namespace separators cannot change the generated syntax.
        $source =
            'declare(strict_types=1); return static function(\\stdClass $object): object|null {'
            . ' if (!('
            . $condition
            . ')) return null; return new \\'
            . $reflection->getName()
            . '('
            . implode(',', $arguments)
            . '); };';
        /**
         * @var Closure(stdClass): (object|null)
         * @mago-expect lint:no-eval Only reflected declarations are compiled; input values remain closure arguments.
         */
        return eval($source);
    }
}
