<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\JsonType;
use JsonException;
use Override;
use ReflectionException;

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
     * @throws JsonException
     * @throws ReflectionException
     */
    #[Override]
    public function decodeValue(mixed $value, string $path = ''): object
    {
        /** @var (T&object)|DecodeError */
        return CollectionItemValueConverter::convert($this->class, $path, $this->class, $value);
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
