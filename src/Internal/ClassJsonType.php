<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\JsonType;
use Override;
use ReflectionException;
use stdClass;
use UnitEnum;

use function enum_exists;

/**
 * @internal
 * @template-covariant T of object
 * @extends JsonType<T>
 */
final readonly class ClassJsonType extends JsonType
{
    /** @param class-string<T> $class */
    public function __construct(
        private string $class,
    ) {}

    /**
     * @return T|DecodeError
     * @throws ReflectionException
     */
    #[Override]
    public function decodeValue(mixed $value, string $path = ''): object
    {
        $class = $this->class;

        if (enum_exists($class)) {
            /** @var (T&UnitEnum)|DecodeError */
            return BackedEnumValueConverter::convertValue($class, $path, $class, $value);
        }

        return $value instanceof stdClass
            ? ObjectHydrator::hydrate($this->class, $value, $path)
            : DecodeError::fieldTypeMismatch($this->class, $path, $this->class, $value);
    }

    /** @return class-string<T> */
    #[Override]
    public function collectionItem(): string
    {
        return $this->class;
    }

    /** @return class-string<T> */
    #[Override]
    public function itemClass(): string
    {
        return $this->class;
    }
}
