<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use BackedEnum;
use Closure;
use Eventjet\Json\DecodeError;
use Override;
use ReflectionEnum;
use ReflectionException;
use UnitEnum;

use function assert;
use function get_debug_type;
use function in_array;
use function is_int;
use function is_string;
use function sprintf;

/** @internal */
final readonly class EnumNode implements Node
{
    /** @var Closure(int|string): (BackedEnum|null) */
    private Closure $find;

    /**
     * @param class-string $class
     * @param class-string<BackedEnum> $enum
     * @param 'int'|'string' $backingType
     */
    public function __construct(
        private string $class,
        public string $enum,
        public string $backingType,
        private bool $nullable,
    ) {
        /** @var callable(int|string): (BackedEnum|null) $tryFrom */
        $tryFrom = [$enum, 'tryFrom'];
        /** @mago-var Closure(int|string): (BackedEnum|null) $find */
        $find = Closure::fromCallable($tryFrom);
        $this->find = $find;
    }

    /**
     * Returns the node for a backed enum, or null if the enum has no backing type.
     *
     * @param class-string $class
     * @throws ReflectionException
     */
    public static function of(string $class, string $enum, bool $nullable): self|null
    {
        /** @var class-string<UnitEnum> $enum */
        $backingType = new ReflectionEnum($enum)->getBackingType();
        if ($backingType === null) {
            return null;
        }
        $backingType = (string) $backingType;
        assert($backingType === 'int' || $backingType === 'string', description: 'Enums are backed by int or string.');
        /** @var class-string<BackedEnum> $enum */
        return new self($class, $enum, $backingType, $nullable);
    }

    /**
     * Rejects the enum as a union member if JSON cannot tell its values apart from the values of other members.
     *
     * @param class-string $class
     * @param list<string> $valueTypes The JSON value types of the other union members.
     */
    public function conflict(string $class, string $path, array $valueTypes): DecodeError|null
    {
        $ambiguous = match (true) {
            $this->backingType === 'int' && in_array('float', $valueTypes, strict: true)
                => ' together with float. Whole-valued floats encode as JSON integers, so JSON cannot distinguish an enum case from a float value.',
            in_array($this->backingType, $valueTypes, strict: true) => sprintf(
                ' together with its backing type %s. JSON cannot distinguish an enum case from the scalar value.',
                $this->backingType,
            ),
            default => null,
        };
        return $ambiguous === null
            ? null
            : DecodeError::nonInstantiableField($class, $path, 'backed enum', $this->enum, $ambiguous);
    }

    #[Override]
    public function decode(mixed $value, string $path): BackedEnum|DecodeError|null
    {
        if ($value === null && $this->nullable) {
            return null;
        }
        $matches = $this->backingType === 'int' ? is_int($value) : is_string($value);
        if (!$matches) {
            return DecodeError::nonInstantiableField(
                $this->class,
                $path,
                'backed enum',
                $this->enum,
                sprintf(', which expects a %s backing value; %s given.', $this->backingType, get_debug_type($value)),
            );
        }
        /** @var int|string $value */
        return ($this->find)($value) ?? EnumUnionNode::unknownCase($this->class, $path, [$this->enum], $value);
    }

    #[Override]
    public function valueTypes(): array
    {
        return [];
    }

    #[Override]
    public function dependencies(): array
    {
        return [];
    }

    #[Override]
    public function __toString(): string
    {
        return $this->enum;
    }
}
