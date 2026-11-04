<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use ArrayObject;
use Eventjet\Json\JsonValue;
use Eventjet\Json\Schema\Draft4;
use Eventjet\Json\Schema\Type;
use InvalidArgumentException;
use Random\Randomizer;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use RuntimeException;

use function implode;
use function sort;
use function sprintf;

/**
 * Generates schema objects with random keywords from a seeded Randomizer. It reads the keywords of a draft from its
 * constructor, so every keyword that a draft declares gets values.
 *
 * @internal
 */
final class SchemaInputs
{
    private const int MAX_DEPTH = 3;

    /**
     * Returns a schema that leaves out each keyword at random, and more often the deeper it is nested.
     *
     * @template T of object
     * @param class-string<T> $draft
     * @return T
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function schema(Randomizer $random, string $draft, int $depth = 0): object
    {
        return self::create($random, $draft, $depth, (8 * $depth) + 1);
    }

    /**
     * Returns a schema that has every keyword. The schemas nested in it leave out keywords at random.
     *
     * @template T of object
     * @param class-string<T> $draft
     * @return T
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function withEveryKeyword(Randomizer $random, string $draft): object
    {
        return self::create($random, $draft, 0, 0);
    }

    /**
     * A nested schema, or from draft 6 on, sometimes a boolean schema. At the maximum depth, it is a boolean schema
     * from draft 6 on, and an empty schema in draft 4.
     *
     * @param class-string $draft
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws RuntimeException
     */
    public static function subschema(Randomizer $random, string $draft, int $depth): object|bool
    {
        if ($depth >= self::MAX_DEPTH) {
            return $draft === Draft4::class ? new Draft4() : SchemaValues::bool($random);
        }
        if ($draft === Draft4::class) {
            return self::schema($random, $draft, $depth + 1);
        }
        $boolean = $random->getInt(0, 3) === 0;
        return $boolean ? SchemaValues::bool($random) : self::schema($random, $draft, $depth + 1);
    }

    /**
     * Each keyword is left out unless a random number from 0 to $omission is 0.
     *
     * @template T of object
     * @param class-string<T> $draft
     * @return T
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws RuntimeException
     */
    private static function create(Randomizer $random, string $draft, int $depth, int $omission): object
    {
        $class = new ReflectionClass($draft);
        $keywords = [];
        foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
            $omitted = $random->getInt(0, $omission) !== 0;
            if ($omitted) {
                continue;
            }
            $keywords[$parameter->getName()] = self::keyword($random, $draft, $parameter, $depth);
        }
        return $class->newInstance(...$keywords);
    }

    /**
     * @param class-string $draft
     * @return object|bool|int|float|string|array<array-key, mixed>
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws RuntimeException
     */
    private static function keyword(
        Randomizer $random,
        string $draft,
        ReflectionParameter $parameter,
        int $depth,
    ): object|bool|int|float|string|array {
        $name = $parameter->getName();
        $types = self::types($parameter);
        return match ($types) {
            'string' => SchemaValues::string($random),
            'bool' => SchemaValues::bool($random),
            'float|int' => SchemaValues::number($random),
            JsonValue::class => new JsonValue(JsonValueInputs::value($random, 0)),
            Type::class . '|array' => SchemaValues::type($random),
            'self' => self::subschema($random, $draft, $depth),
            'bool|self' => self::booleanOrSubschema($random, $draft, $depth),
            'array|bool|self', 'array|self' => self::subschemaOrItems($random, $draft, $name, $depth),
            'array' => SchemaValues::arrayValue($random, $draft, $name, $depth),
            ArrayObject::class => new ArrayObject(SchemaValues::members($random, $draft, $name, $depth)),
            default => throw new RuntimeException(sprintf('No values for keyword %s of %s.', $name, $draft)),
        };
    }

    /**
     * Draft 4 has no boolean schemas, but its `additionalItems` and `additionalProperties` accept a boolean.
     *
     * @param class-string $draft
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws RuntimeException
     */
    private static function booleanOrSubschema(Randomizer $random, string $draft, int $depth): object|bool
    {
        if ($draft !== Draft4::class) {
            return self::subschema($random, $draft, $depth);
        }
        $useBoolean = SchemaValues::bool($random);
        return $useBoolean ? SchemaValues::bool($random) : self::subschema($random, $draft, $depth);
    }

    /**
     * @param class-string $draft
     * @return object|bool|list<mixed>
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws RuntimeException
     */
    private static function subschemaOrItems(
        Randomizer $random,
        string $draft,
        string $keyword,
        int $depth,
    ): object|bool|array {
        $useSubschema = SchemaValues::bool($random);
        return $useSubschema
            ? self::subschema($random, $draft, $depth)
            : SchemaValues::items($random, $draft, $keyword, $depth);
    }

    /**
     * The parameter's types without null, sorted and joined with `|`. The declaring class reads as `self`, because
     * PHP 8.5 reports `self` under the resolved class name.
     */
    private static function types(ReflectionParameter $parameter): string
    {
        $type = $parameter->getType();
        $self = $parameter->getDeclaringClass()?->getName();
        $names = [];
        $members = $type instanceof ReflectionUnionType ? $type->getTypes() : [$type];
        foreach ($members as $member) {
            if (!$member instanceof ReflectionNamedType || $member->getName() === 'null') {
                continue;
            }
            $names[] = $member->getName() === $self ? 'self' : $member->getName();
        }
        sort($names);
        return implode('|', $names);
    }
}
