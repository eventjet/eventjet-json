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
use function count;
use function get_debug_type;
use function implode;
use function in_array;
use function is_int;
use function is_string;
use function sort;
use function sprintf;
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
     * @param class-string $class
     * @param non-empty-list<class-string<BackedEnum>> $enums
     * @param 'int'|'string' $backingType
     * @param array<array-key, BackedEnum>|null $cases The cases by backing value, or null to find each case when a value
     *     arrives, which keeps backing values that refer to other classes unloaded until then.
     */
    private function __construct(
        private string $class,
        public array $enums,
        public string $backingType,
        private array|null $cases,
    ) {
        /** @var callable(int|string): (BackedEnum|null) $tryFrom */
        $tryFrom = [$enums[0], 'tryFrom'];
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
    public static function of(string $class, string $enum): self|null
    {
        /** @var class-string<UnitEnum> $enum */
        $backingType = new ReflectionEnum($enum)->getBackingType();
        if ($backingType === null) {
            return null;
        }
        $backingType = (string) $backingType;
        assert($backingType === 'int' || $backingType === 'string', description: 'Enums are backed by int or string.');
        /** @var class-string<BackedEnum> $enum */
        return new self($class, [$enum], $backingType, null);
    }

    /**
     * Combines the enums of a union that share a backing type into one node that finds cases in a table, rejecting
     * enums whose values JSON cannot tell apart from each other or from other members.
     *
     * @param class-string $class
     * @param 'int'|'string' $backingType
     * @param non-empty-list<class-string<BackedEnum>> $enums
     * @param list<string> $valueTypes The JSON value types of the other union members.
     */
    public static function union(
        string $class,
        string $path,
        string $backingType,
        array $enums,
        array $valueTypes,
    ): self|DecodeError {
        $conflict = self::conflict($class, $path, $backingType, $enums[0], $valueTypes);
        if ($conflict !== null) {
            return $conflict;
        }
        $sorted = $enums;
        sort($sorted);
        $cases = [];
        foreach ($sorted as $enum) {
            /** @var callable(): list<BackedEnum> $enumCases */
            $enumCases = [$enum, 'cases'];
            foreach ($enumCases() as $case) {
                $owner = $cases[$case->value] ?? null;
                if ($owner !== null) {
                    return DecodeError::nonInstantiableField(
                        $class,
                        $path,
                        'multiple backed enums',
                        $owner::class . ' and ' . $enum,
                        sprintf(' with overlapping backing value %s. JSON cannot identify which enum case to instantiate.', var_export(
                            $case->value,
                            return: true,
                        )),
                    );
                }
                $cases[$case->value] = $case;
            }
        }
        return new self($class, $enums, $backingType, $cases);
    }

    #[Override]
    public function decode(mixed $value, string $path): BackedEnum|DecodeError
    {
        $matches = $this->backingType === 'int' ? is_int($value) : is_string($value);
        if (!$matches) {
            return DecodeError::nonInstantiableField(
                $this->class,
                $path,
                'backed enum',
                $this->enums[0],
                sprintf(', which expects a %s backing value; %s given.', $this->backingType, get_debug_type($value)),
            );
        }
        /** @var int|string $value */
        $case = $this->cases === null ? ($this->find)($value) : $this->cases[$value] ?? null;
        return $case ?? $this->unknownCase($path, $value);
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
        return implode('|', $this->enums);
    }

    /**
     * Rejects an enum as a union member if JSON cannot tell its values apart from the values of other members.
     *
     * @param class-string $class
     * @param list<string> $valueTypes
     */
    private static function conflict(
        string $class,
        string $path,
        string $backingType,
        string $enum,
        array $valueTypes,
    ): DecodeError|null {
        $ambiguous = match (true) {
            $backingType === 'int' && in_array('float', $valueTypes, strict: true)
                => ' together with float. Whole-valued floats encode as JSON integers, so JSON cannot distinguish an enum case from a float value.',
            in_array($backingType, $valueTypes, strict: true) => sprintf(
                ' together with its backing type %s. JSON cannot distinguish an enum case from the scalar value.',
                $backingType,
            ),
            default => null,
        };
        return $ambiguous === null
            ? null
            : DecodeError::nonInstantiableField($class, $path, 'backed enum', $enum, $ambiguous);
    }

    private function unknownCase(string $path, int|string $value): DecodeError
    {
        $enums = $this->enums;
        sort($enums);
        return DecodeError::nonInstantiableField(
            $this->class,
            $path,
            count($enums) === 1 ? 'backed enum' : 'backed enum union',
            implode('|', $enums),
            sprintf(', which has no case with backing value %s.', var_export($value, return: true)),
        );
    }
}
