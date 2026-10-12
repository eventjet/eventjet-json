<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use BackedEnum;
use Eventjet\Json\DecodeError;
use Override;

use function get_debug_type;
use function sprintf;

/** @internal */
final readonly class EnumNode implements ItemNode
{
    private EnumUnionNode $cases;

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
        $this->cases = new EnumUnionNode($class, [$enum]);
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
        return $this->cases->decode($value, $path);
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
