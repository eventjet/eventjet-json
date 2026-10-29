<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use BackedEnum;
use Closure;
use Eventjet\Json\Internal\Error\InvalidValue;
use Override;
use ReflectionEnum;
use ReflectionException;
use UnitEnum;

use function assert;
use function implode;
use function is_int;
use function is_string;
use function var_export;

/**
 * Finds the case of a backed enum, or of one of several backed enums that share a backing type in a union.
 *
 * @internal
 */
final readonly class EnumNode implements Node
{
    /** @var Closure(int|string): (BackedEnum|null) */
    private Closure $find;

    /**
     * @param non-empty-list<class-string<BackedEnum>> $enums
     * @param 'int'|'string' $backingType
     * @param array<array-key, BackedEnum>|null $cases The cases by backing value, or null to find each case when a value
     *     arrives, which keeps backing values that refer to other classes unloaded until then.
     */
    private function __construct(
        public array $enums,
        public string $backingType,
        private array|null $cases,
    ) {
        /** @var callable(int|string): (BackedEnum|null) $tryFrom */
        $tryFrom = [$enums[0], 'tryFrom'];
        $this->find = $tryFrom(...);
    }

    /**
     * Returns the node for a backed enum, or null if the enum has no backing type.
     *
     * @throws ReflectionException
     */
    public static function of(string $enum): self|null
    {
        /** @var class-string<UnitEnum> $enum */
        $backingType = new ReflectionEnum($enum)->getBackingType();
        if ($backingType === null) {
            return null;
        }
        $backingType = (string) $backingType;
        assert($backingType === 'int' || $backingType === 'string', description: 'Enums are backed by int or string.');
        /** @var class-string<BackedEnum> $enum */
        return new self([$enum], $backingType, null);
    }

    /**
     * Combines the enums of a union that share a backing type into one node that finds cases in a table. Union rules
     * ensure that their backing values do not overlap.
     *
     * @param 'int'|'string' $backingType
     * @param non-empty-list<class-string<BackedEnum>> $enums
     */
    public static function union(string $backingType, array $enums): self
    {
        return new self($enums, $backingType, self::cases($enums));
    }

    #[Override]
    public function decode(mixed $value, string $path): BackedEnum
    {
        $matches = $this->backingType === 'int' ? is_int($value) : is_string($value);
        if (!$matches) {
            throw InvalidValue::enumBackingType($path, $this->enums[0], $this->backingType, $value);
        }
        /** @var int|string $value */
        $case = $this->cases === null ? ($this->find)($value) : $this->cases[$value] ?? null;
        return $case ?? throw InvalidValue::unknownEnumCase($path, $this->enums, $value);
    }

    /** An enum takes every value of its backing type, and rejects values that are not its cases. */
    #[Override]
    public function claims(): array
    {
        $values = [];
        foreach (self::cases($this->enums) as $case) {
            $values[var_export($case->value, return: true)] = [$case->value, $case];
        }
        return [$this->backingType => ['every' => true, 'values' => $values]];
    }

    #[Override]
    public function dependencies(): array
    {
        return [];
    }

    #[Override]
    public function __toString(): string
    {
        return implode('|', $this->enums);
    }

    /**
     * @param list<class-string<BackedEnum>> $enums
     * @return array<array-key, BackedEnum> The cases by backing value.
     */
    private static function cases(array $enums): array
    {
        $cases = [];
        foreach ($enums as $enum) {
            /** @var callable(): list<BackedEnum> $enumCases */
            $enumCases = [$enum, 'cases'];
            foreach ($enumCases() as $case) {
                $cases[$case->value] = $case;
            }
        }
        return $cases;
    }
}
