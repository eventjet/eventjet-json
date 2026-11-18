<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Closure;
use Eventjet\Json\JsonType;

use function is_string;

/**
 * Cases for which validateType() returns something else than decode() suggests.
 *
 * @internal
 */
final class ValidationOverrides
{
    /**
     * Decodable types that name a class that is not loaded, by the class. decode() does not need the class for null,
     * but validation needs it for every value.
     */
    public const array DECODABLE = [
        'null satisfies a nullable parameter whose class is not loaded' => 'UnloadedConstructorClass',
        'null satisfies a nullable public property whose class is not loaded' => 'UnloadedPropertyClass',
    ];

    /**
     * Decode failures for which validateType() returns something else than the rule in
     * {@see DecodeFailureCases::validations()} predicts.
     *
     * @return array<string, Closure(class-string|JsonType<list<mixed>|object>): (string|null)>
     */
    public static function messages(): array
    {
        $none = static fn(): null => null;
        return [
            // decode() only needs the unloaded class for an object, but validation needs it for every value.
            'unloaded class parameter rejects null' => self::unknownClass('UnloadedRequiredClass'),
            'unloaded class property rejects null' => self::unknownClass('UnloadedRequiredClass'),
            'union with an unloaded class rejects an object' => self::unknownClass('UnloadedUnionClass'),
            'union with an unloaded class accepts an integer' => self::unknownClass('UnloadedUnionClass'),
            // PHP reports these only while it creates the object or reads the enum case.
            'readonly property set by the constructor cannot be assigned again' => $none,
            'enum case value that refers to a missing class' => $none,
            'nested enum case value that refers to a missing class' => $none,
            'root enum case value that refers to a missing class' => $none,
        ];
    }

    /** @param string|JsonType<list<mixed>|object> $type */
    public static function unknownClassMessage(string|JsonType $type, string $unknown): string
    {
        return (
            'Could not create '
            . (is_string($type) ? $type : '')
            . ' from the JSON object: Field value uses unknown class '
            . $unknown
            . ', which cannot be instantiated directly. JSON does not identify a concrete class to instantiate.'
        );
    }

    /** @return Closure(class-string|JsonType<list<mixed>|object>): string */
    private static function unknownClass(string $unknown): Closure
    {
        return static fn(string|JsonType $type): string => self::unknownClassMessage($type, $unknown);
    }
}
