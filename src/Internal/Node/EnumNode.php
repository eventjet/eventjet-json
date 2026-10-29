<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use BackedEnum;
use Closure;
use Eventjet\Json\Internal\Error\InvalidInput;
use Eventjet\Json\Internal\Error\InvalidValue;
use Eventjet\Json\Internal\TargetClass;
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
     * Returns the node for a backed enum. {@see TargetClass::validateEnum()} rejects non-backed enums first.
     *
     * @throws ReflectionException
     */
    public static function of(string $enum): self
    {
        /** @var class-string<UnitEnum> $enum */
        $backingType = new ReflectionEnum($enum)->getBackingType();
        assert($backingType !== null, description: 'TargetClass rejects non-backed enums.');
        $backingType = (string) $backingType;
        assert($backingType === 'int' || $backingType === 'string', description: 'Enums are backed by int or string.');
        /** @var class-string<BackedEnum> $enum */
        return new self([$enum], $backingType, null);
    }

    /**
     * Combines the enums of a union that share a backing type into one node that finds cases in a table. Union rules
     * ensure that their backing values do not overlap.
     *
     * @param non-empty-list<class-string<BackedEnum>> $enums
     */
    public static function union(string $backingType, array $enums): self
    {
        assert($backingType === 'int' || $backingType === 'string', description: 'Enums claim their backing type.');
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

    /**
     * Decodes the root value of a document into a case, or returns the error, which names no field. The return type is
     * object, like that of a class decoder, so that the caller can name the enum it asked for. The root names a single
     * enum, so the node finds each case when its value arrives.
     */
    public function decodeRoot(mixed $value): object
    {
        $matches = $this->backingType === 'int' ? is_int($value) : is_string($value);
        if (!$matches) {
            return InvalidInput::enumRootValue($this->enums[0], $this->backingType, $value);
        }
        /** @var int|string $value */
        return ($this->find)($value) ?? InvalidValue::unknownRootEnumCase($this->enums[0], $value);
    }

    /** An enum takes every value of its backing type, so that it reports the values that are not its cases. */
    #[Override]
    public function claims(): array
    {
        $values = [];
        foreach (self::cases($this->enums) as $case) {
            $values[var_export($case->value, return: true)] = [$case->value, $case];
        }
        return [$this->backingType => ['others' => 'rejects', 'enums' => $this->enums, 'values' => $values]];
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
