<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use BackedEnum;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\EnumNode;
use Eventjet\Json\Internal\Node\Node;
use Eventjet\Json\Internal\Node\NullOnlyNode;
use JsonSerializable;
use ReflectionEnum;
use ReflectionException;
use ReflectionNamedType;
use UnitEnum;

use function assert;
use function enum_exists;
use function in_array;
use function is_a;

/** @internal */
final class EnumCompiler
{
    /**
     * Returns the node for a backed enum, or null if the enum has no backing type.
     *
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function node(string $class, string $enum, bool $nullable): EnumNode|null
    {
        /** @var class-string<UnitEnum> $enum */
        $backingType = new ReflectionEnum($enum)->getBackingType();
        if ($backingType === null) {
            return null;
        }
        $backingType = (string) $backingType;
        assert($backingType === 'int' || $backingType === 'string', description: 'Enums are backed by int or string.');
        /** @var class-string<BackedEnum> $enum */
        return new EnumNode($class, $enum, $backingType, $nullable);
    }

    /**
     * Compiles a field declared as an enum. A nullable field of a non-backed enum can only hold null.
     *
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function field(
        string $class,
        string $field,
        string $enum,
        ReflectionNamedType $type,
    ): Node|DecodeError {
        $nullable = $type->allowsNull();
        $node = self::node($class, $enum, $nullable);
        if ($node !== null) {
            return $node;
        }
        return $nullable && !is_a($enum, JsonSerializable::class, allow_string: true)
            ? new NullOnlyNode($class, (string) $type)
            : DecodeError::nonBackedEnum($class, $enum, $field);
    }

    /**
     * Whether values of a type have no JSON representation. Unions skip such members if others remain.
     *
     * @throws ReflectionException
     */
    public static function isNonEncodable(string $type): bool
    {
        if (in_array($type, ['resource', 'open-resource', 'closed-resource'], strict: true)) {
            return true;
        }
        if (!enum_exists($type)) {
            return false;
        }
        $enum = new ReflectionEnum($type);
        return !$enum->isBacked() && !$enum->implementsInterface(JsonSerializable::class);
    }

    /**
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function nonEncodable(string $class, string $type, string $path): DecodeError
    {
        if (enum_exists($type)) {
            return DecodeError::nonBackedEnum($class, $type, $path);
        }
        return DecodeError::nonInstantiableField(
            $class,
            $path,
            'non-JSON-encodable type',
            $type,
            ', which has no JSON representation.',
        );
    }
}
