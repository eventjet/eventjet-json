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
    /**
     * @param class-string $class
     * @param non-empty-list<class-string<BackedEnum>> $enums
     */
    public function __construct(
        private string $class,
        private array $enums,
    ) {}

    #[Override]
    public function decode(mixed $value, string $path): BackedEnum|DecodeError
    {
        assert(is_int($value) || is_string($value), description: 'Unions select enums by their backing type.');
        foreach ($this->enums as $enum) {
            /** @var callable(int|string): (BackedEnum|null) $tryFrom */
            $tryFrom = [$enum, 'tryFrom'];
            $case = $tryFrom($value);
            if ($case !== null) {
                return $case;
            }
        }
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

    #[Override]
    public function dependencies(): array
    {
        return [];
    }
}
