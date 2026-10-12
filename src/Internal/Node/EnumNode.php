<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use BackedEnum;
use Closure;
use Eventjet\Json\DecodeError;
use Override;

use function get_debug_type;
use function sprintf;

/** @internal */
final readonly class EnumNode implements ItemNode
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
