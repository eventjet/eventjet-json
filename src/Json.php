<?php

declare(strict_types=1);

namespace Eventjet\Json;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use stdClass;
use Throwable;

use function assert;
use function json_decode;
use function preg_match;
use function property_exists;
use function sprintf;

use const JSON_THROW_ON_ERROR;

final class Json
{
    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public static function decode(string $json, string $class): object
    {
        $data = json_decode($json, associative: false, flags: JSON_THROW_ON_ERROR);
        if (!$data instanceof stdClass) {
            throw JsonError::decodeFailed('Expected JSON object at the root.');
        }
        $arguments = self::buildConstructorArguments($data, $class);
        try {
            /** @psalm-suppress MixedMethodCall */
            return new $class(...$arguments);
        } catch (Throwable $e) {
            $result = preg_match(
                '/Argument #\d+ \(\$(?<name>.+)\) must be of type (?<expected>.+), (?<got>.+) given, called in/',
                $e->getMessage(),
                $matches,
            );
            if ($result !== 1) {
                throw $e;
            }
            throw JsonError::decodeFailed(sprintf(
                'Field "%s" expected to be of type %s, got %s.',
                $matches['name'],
                $matches['expected'],
                $matches['got'],
            ));
        }
    }

    /**
     * @param class-string $class
     * @return array<string, mixed>
     */
    private static function buildConstructorArguments(stdClass $data, string $class): array
    {
        $constructor = (new ReflectionClass($class))->getConstructor();
        if ($constructor === null) {
            throw JsonError::decodeFailed(sprintf('Class %s does not have a constructor.', $class));
        }
        $args = [];
        foreach ($constructor->getParameters() as $parameter) {
            if (!property_exists($data, $parameter->name)) {
                if ($parameter->isOptional()) {
                    continue;
                }
                throw JsonError::decodeFailed(
                    sprintf('Missing required property "%s" in JSON data for class %s.', $parameter->getName(), $class),
                );
            }
            /** @psalm-suppress MixedAssignment */
            $value = self::buildConstructorArgument($data, $parameter);
            /** @psalm-suppress MixedAssignment */
            $args[$parameter->name] = $value;
        }
        return $args;
    }

    private static function buildConstructorArgument(stdClass $data, ReflectionParameter $parameter): mixed
    {
        /** @var mixed $value */
        $value = $data->{$parameter->name};
        if ($value instanceof stdClass) {
            $parameterType = $parameter->getType();
            if ($parameterType instanceof ReflectionNamedType) {
                if ($parameterType->getName() === 'mixed') {
                    $class = $parameter->getDeclaringClass();
                    assert($class !== null);
                    throw JsonError::decodeFailed(sprintf(
                        'To populate a property with an object, it must have a specific class type instead of mixed (property "%s" in class %s).',
                        $parameter->name,
                        $class->getName(),
                    ));
                }
                throw JsonError::decodeFailed(sprintf(
                    'Can\'t populate property "%s" of type %s with JSON object.',
                    $parameter->name,
                    $parameterType->getName(),
                ));
            }
        }
        return $value;
    }
}
