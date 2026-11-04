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
     * @param list<string> $members The JSON names of the fields of the first class.
     * @param non-empty-list<array{ReflectionClass<object>, array<array-key, ReflectionParameter|ReflectionProperty>}> $classes
     *     Each class with its fields, keyed by JSON name.
     */
    private function __construct(
        private array $members,
        private array $classes,
    ) {}

    /**
     * @param non-empty-list<ReflectionClass<object>> $classes
     * @throws DecodeError
     */
    public static function of(array $classes): self
    {
        $members = null;
        $fields = [];
        foreach ($classes as $class) {
            $names = [];
            $byName = [];
            foreach (ClassField::uncompiled($class, TargetClass::fieldNames($class)) as [$json, $field]) {
                $names[] = $json;
                $byName[$json] = $field;
            }
            $members ??= $names;
            $fields[] = [$class, $byName];
        }
        return new self($members, $fields);
    }

    /**
     * Returns each member that can be the discriminator with its values and their classes.
     *
     * @return list<array{string, non-empty-list<array{int|string, class-string}>}>
     * @throws DecodeError
     * @throws ReflectionException
     */
    public function all(): array
    {
        $candidates = [];
        foreach ($this->members as $member) {
            $tags = $this->tags($member);
            if ($tags !== null) {
                $candidates[] = [$member, $tags];
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
        foreach ($this->classes as [$class, $fields]) {
            $field = $fields[$member] ?? null;
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
