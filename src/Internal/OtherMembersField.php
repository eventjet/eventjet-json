<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Error\InvalidOtherMembers;
use Eventjet\Json\Internal\Error\InvalidValue;
use Eventjet\Json\JsonValue;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;

use function array_diff_key;
use function array_pop;

/**
 * The constructor parameter or the public property that collects the members that no declared field claims, each as a
 * JsonValue. Kept apart from ClassField, so that classes without #[OtherMembers] do not load it.
 *
 * @internal
 */
final readonly class OtherMembersField
{
    /**
     * @param string $name The name of the constructor parameter or the property.
     * @param ReflectionProperty|null $property The property that decoding assigns after construction, or null for a
     *     constructor argument.
     */
    private function __construct(
        public string $name,
        public ReflectionProperty|null $property,
    ) {}

    /**
     * Takes the field that collects the other members out of the constructor parameters and the public instance
     * properties, and compiles it: the constructor parameter of that name if there is one, or else the property.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param string $others The property that collects the other members.
     * @param array<ReflectionParameter> $parameters The constructor parameters.
     * @param array<string, ReflectionProperty> $properties The public instance properties, keyed by name.
     * @return array{self, array<ReflectionParameter>, array<string, ReflectionProperty>}
     * @throws DecodeError
     * @throws ReflectionException
     */
    public static function extract(ReflectionClass $class, string $others, array $parameters, array $properties): array
    {
        unset($properties[$others]);
        foreach ($parameters as $index => $parameter) {
            if ($parameter->getName() !== $others) {
                continue;
            }
            unset($parameters[$index]);
            return [self::field($parameter, null), $parameters, $properties];
        }
        $property = $class->getProperty($others);
        return [self::field($property, $property), $parameters, $properties];
    }

    /**
     * Returns the field that collects the other members, or throws if its type is not `array<array-key, JsonValue>`.
     *
     * @param ReflectionParameter|ReflectionProperty $field The constructor parameter or the property that declares the
     *     type.
     * @param ReflectionProperty|null $property The property that decoding assigns after construction, or null for a
     *     constructor argument.
     * @throws DecodeError
     * @throws ReflectionException
     */
    private static function field(
        ReflectionParameter|ReflectionProperty $field,
        ReflectionProperty|null $property,
    ): self {
        $name = $field->getName();
        $type = $field->getType();
        $declared = PhpDocFieldType::resolve($field);
        $resolved = $declared === null ? null : new PhpDocTypeResolver($field)->resolve($declared);
        $arguments = $resolved?->name === 'array' ? $resolved->arguments : [];
        $value = array_pop($arguments);
        $key = array_pop($arguments);
        $collects =
            !($field instanceof ReflectionParameter && $field->isVariadic())
            && $type instanceof ReflectionNamedType
            && $type->getName() === 'array'
            && !$type->allowsNull()
            && $arguments === []
            && $value?->isPlainName(JsonValue::class) === true
            && ($key === null || $key->mapKeys(ArrayObject::class) === 'array-key');
        return $collects ? new self($name, $property) : throw InvalidOtherMembers::type($name);
    }

    /**
     * Returns the members whose names are not declared, each as a JsonValue.
     *
     * @param array<array-key, mixed> $values The members of the JSON object.
     * @param array<array-key, true> $declared The JSON names of the declared fields, as keys.
     * @return array<array-key, JsonValue>
     * @throws DecodeError
     */
    public function collect(array $values, array $declared, string $path): array
    {
        $collected = [];
        /** @var mixed $value */
        foreach (array_diff_key($values, $declared) as $key => $value) {
            try {
                $collected[$key] = new JsonValue($value);
            } catch (InvalidArgumentException $error) {
                throw InvalidValue::jsonValue(FieldPath::field($path, (string) $key), $error);
            }
        }
        return $collected;
    }
}
