<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use BackedEnum;
use Eventjet\Json\DecodeError;
use Override;

use function assert;
use function count;
use function implode;
use function is_int;
use function is_string;
use function sort;
use function sprintf;
use function var_export;

/**
 * Finds the case of one of several backed enums that share a backing type. Union rules ensure the values are unique.
 *
 * @internal
 */
final readonly class EnumUnionNode implements Node
{
    /** @var array<array-key, BackedEnum> */
    private array $cases;

    /**
     * @param class-string $class
     * @param non-empty-list<class-string<BackedEnum>> $enums
     */
    public function __construct(
        private string $class,
        private array $enums,
    ) {
        $cases = [];
        foreach ($enums as $enum) {
            /** @var callable(): list<BackedEnum> $enumCases */
            $enumCases = [$enum, 'cases'];
            foreach ($enumCases() as $case) {
                $cases[$case->value] = $case;
            }
        }
        $this->cases = $cases;
    }

    /**
     * @param class-string $class
     * @param non-empty-list<class-string<BackedEnum>> $enums
     */
    public static function unknownCase(string $class, string $path, array $enums, mixed $value): DecodeError
    {
        sort($enums);
        return DecodeError::nonInstantiableField(
            $class,
            $path,
            count($enums) === 1 ? 'backed enum' : 'backed enum union',
            implode('|', $enums),
            sprintf(', which has no case with backing value %s.', var_export($value, return: true)),
        );
    }

    #[Override]
    public function decode(mixed $value, string $path): BackedEnum|DecodeError
    {
        assert(is_int($value) || is_string($value), description: 'Unions select this node by the type of the value.');
        return $this->cases[$value] ?? self::unknownCase($this->class, $path, $this->enums, $value);
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
}
