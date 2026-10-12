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

    #[Override]
    public function decode(mixed $value, string $path): BackedEnum|DecodeError|null
    {
        if ($value === null && $this->nullable) {
            return null;
        }
        $type = get_debug_type($value);
        if ($type !== $this->backingType) {
            return DecodeError::nonInstantiableField(
                $this->class,
                $path,
                'backed enum',
                $this->enum,
                sprintf(', which expects a %s backing value; %s given.', $this->backingType, $type),
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
