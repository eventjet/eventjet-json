<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

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
     * @param array<array-key, string> $declared The names of the other public instance properties, keyed by JSON name.
     */
    private function __construct(
        public string $name,
        public ReflectionProperty|null $property,
        private array $declared,
    ) {}

    /**
     * Compiles the field that collects the other members: the constructor parameter of that name if there is one, or
     * else the property.
     *
     * @template T of object
     * @param ReflectionClass<T> $class
     * @param array{string, array<array-key, string>} $others The property that collects the other members, with the
     *     names of the other public instance properties keyed by JSON name.
     * @param array<ReflectionParameter> $parameters The constructor parameters.
     * @throws DecodeError
     * @throws ReflectionException
     */
    public static function of(ReflectionClass $class, array $others, array $parameters): self
    {
        [$name, $declared] = $others;
        foreach ($parameters as $parameter) {
            if ($parameter->getName() === $name) {
                return self::field($parameter, null, $declared);
            }
        }
        $property = $class->getProperty($name);
        return self::field($property, $property, $declared);
    }

    /**
     * Returns the field that collects the other members, or throws if its type is not `array<array-key, JsonValue>`.
     *
     * @param ReflectionParameter|ReflectionProperty $field The constructor parameter or the property that declares the
     *     type.
     * @param ReflectionProperty|null $property The property that decoding assigns after construction, or null for a
     *     constructor argument.
     * @param array<array-key, string> $declared The names of the other public instance properties, keyed by JSON name.
     * @throws DecodeError
     * @throws ReflectionException
     */
    private static function field(
        ReflectionParameter|ReflectionProperty $field,
        ReflectionProperty|null $property,
        array $declared,
    ): self {
        $name = $field->getName();
        $type = $field->getType();
        $declaredType = PhpDocFieldType::resolve($field);
        $resolved = $declaredType === null ? null : new PhpDocTypeResolver($field)->resolve($declaredType);
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
            && ($key === null || $key->isAnyKey());
        return $collects ? new self($name, $property, $declared) : throw InvalidOtherMembers::type($name);
    }

    /**
     * Returns the members whose names are not declared, each as a JsonValue.
     *
     * @param array<array-key, mixed> $values The members of the JSON object.
     * @return array<array-key, JsonValue>
     * @throws DecodeError
     */
    public function collect(array $values, string $path): array
    {
        $collected = [];
        /** @var mixed $value */
        foreach (array_diff_key($values, $this->declared) as $key => $value) {
            try {
                $collected[$key] = new JsonValue($value);
            } catch (InvalidArgumentException $error) {
                throw InvalidValue::jsonValue(FieldPath::field($path, (string) $key), $error);
            }
        }
        return $collected;
    }
}
