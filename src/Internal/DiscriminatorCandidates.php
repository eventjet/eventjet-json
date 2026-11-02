<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function array_key_exists;
use function var_export;

/**
 * Checks which members can be the discriminator of several classes: members that every class declares with literals
 * whose JSON values no other class declares.
 *
 * @internal
 */
final readonly class DiscriminatorCandidates
{
    /**
     * @param non-empty-list<ReflectionClass<object>> $classes
     * @param list<array<array-key, ReflectionParameter|ReflectionProperty>> $fields The fields of each class that may
     *     be its discriminator, keyed by JSON name.
     */
    public function __construct(
        private array $classes,
        private array $fields,
    ) {}

    /**
     * Returns the values of each member that can be the discriminator, with their classes.
     *
     * @param list<string> $members
     * @return array<array-key, non-empty-list<array{int|string, class-string}>> Keyed by member, which PHP turns into
     *     an integer if it looks like one.
     * @throws DecodeError
     * @throws ReflectionException
     */
    public function all(array $members): array
    {
        $candidates = [];
        foreach ($members as $member) {
            $tags = $this->tags($member);
            if ($tags !== null) {
                $candidates[$member] = $tags;
            }
        }
        return $candidates;
    }

    /**
     * Returns each value of a member with its class, or null if the member cannot be the discriminator.
     *
     * @return non-empty-list<array{int|string, class-string}>|null
     * @throws DecodeError
     * @throws ReflectionException
     */
    public function tags(string $member): array|null
    {
        $owners = [];
        $tags = [];
        foreach ($this->classes as $index => $class) {
            $field = $this->fields[$index][$member] ?? null;
            $values = $field === null ? null : DiscriminatorValues::of($class, $field);
            if ($values === null) {
                return null;
            }
            $name = $class->getName();
            foreach ($values as $value) {
                $key = var_export($value, return: true);
                if (($owners[$key] ?? $name) !== $name) {
                    return null;
                }
                if (!array_key_exists($key, $owners)) {
                    $tags[] = [$value, $name];
                }
                $owners[$key] = $name;
            }
        }
        return $tags === [] ? null : $tags;
    }
}
