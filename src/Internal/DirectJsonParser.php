<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use JsonSerializable;
use ReflectionClass;
use ReflectionNamedType;

/**
 * @internal Compile the small schemas that benefit from direct construction.
 * @mago-expect lint:cyclomatic-complexity Explicit eligibility guards preserve compatibility for unsupported declarations.
 */
final class DirectJsonParser
{
    public const string WS = '[\\x20\\x09\\x0a\\x0d]*+';

    /**
     * @param class-string $class
     * @throws \ReflectionException
     * @throws \JsonException
     */
    public static function compile(string $class): DirectScalarPlan|DirectListPlan|false
    {
        $reflection = new ReflectionClass($class);
        $parameters = $reflection->getConstructor()?->getParameters() ?? [];
        $first = $parameters[0] ?? null;
        if ($first !== null && count($parameters) === 1 && (string) $first->getType() === 'array') {
            $rootError = RootTypeValidator::validate($reflection);
            $properties = PublicProperties::resolve($reflection);
            if (
                $rootError !== null
                || $reflection->implementsInterface(JsonSerializable::class)
                || $properties !== []
            ) {
                return false;
            }
            $parameter = new ConstructorParameter($first, $reflection);
            $type = $parameter->resolveType($class);
            if (!$type instanceof ListType || !is_string($type->itemType) || !class_exists($type->itemType)) {
                return false;
            }
            $item = self::scalar($type->itemType);
            if (!$item instanceof DirectScalarPlan) {
                return false;
            }
            return new DirectListPlan($reflection->getName(), $parameter->name, $item, $type->nonEmpty);
        }
        return self::scalar($class);
    }

    /**
     * @param class-string $class
     * @throws \ReflectionException
     * @throws \JsonException
     */
    private static function scalar(string $class): DirectScalarPlan|false
    {
        $reflection = new ReflectionClass($class);
        $rootError = RootTypeValidator::validate($reflection);
        $properties = PublicProperties::resolve($reflection);
        if ($rootError !== null || $reflection->implementsInterface(JsonSerializable::class) || $properties !== []) {
            return false;
        }
        $parameters = $reflection->getConstructor()?->getParameters() ?? [];
        if ($parameters === []) {
            return false;
        }
        $members = [];
        $types = [];
        foreach ($parameters as $parameter) {
            $meta = new ConstructorParameter($parameter, $reflection);
            $resolved = $meta->resolveType($class);
            if ($resolved !== false || !$meta->type instanceof ReflectionNamedType) {
                return false;
            }
            $definition = match ($meta->type->getName()) {
                // Escapes and non-ASCII strings use the compatibility decoder.
                'string' => ['string', '"[\\x20-\\x21\\x23-\\x5b\\x5d-\\x7f]*+"'],
                // Nineteen-digit integers use native overflow/type handling.
                'int' => ['int', '-?(?:0|[1-9][0-9]{0,17})'],
                'float' => ['float', '-?(?:0|[1-9][0-9]*+)(?:\\.[0-9]++)?(?:[eE][+-]?[0-9]++)?'],
                'bool' => ['bool', '(?:true|false)'],
                'true' => ['true', 'true'],
                'false' => ['false', 'false'],
                'null' => ['null', 'null'],
                default => null,
            };
            if ($definition === null) {
                return false;
            }
            [$kind, $token] = $definition;
            if ($parameter->allowsNull()) {
                $token = '(?:' . $token . '|null)';
            }
            $members[] =
                preg_quote(json_encode($meta->name, JSON_THROW_ON_ERROR), delimiter: '~')
                . self::WS
                . ':'
                . self::WS
                . '('
                . $token
                . ')';
            $types[] = $kind;
        }
        return new DirectScalarPlan(
            $reflection->getName(),
            '\\{' . self::WS . implode(self::WS . ',' . self::WS, $members) . self::WS . '\\}',
            $types,
        );
    }
}
