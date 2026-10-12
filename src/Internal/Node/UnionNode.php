<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal\Node;

use Eventjet\Json\DecodeError;
use Override;

use function array_key_exists;
use function get_debug_type;

/**
 * Decodes each JSON value with the member registered for its type. Union rules ensure each type has at most one member.
 *
 * @internal
 */
final readonly class UnionNode implements Node
{
    /**
     * @param class-string $class
     * @param array<string, Node> $members Keyed by the get_debug_type() of the decoded JSON values they accept, or by
     *     true or false for booleans.
     * @param Node|null $fallback Accepts values of every other type, for members that PHP checks itself.
     */
    public function __construct(
        private string $class,
        private string $type,
        private array $members,
        private Node|null $fallback,
    ) {}

    /**
     * Indexes valid union members by the JSON value types they accept. A `callable` member accepts every other value,
     * because PHP checks callable arguments itself.
     *
     * @param class-string $class
     * @param list<Node> $members
     */
    public static function of(string $class, string $type, array $members): self
    {
        /** @var array<string, Node> $indexed */
        $indexed = [];
        $enums = [];
        foreach ($members as $member) {
            foreach ($member->valueTypes() as $valueType) {
                $indexed[$valueType] = $member;
            }
            if ($member instanceof EnumNode) {
                $enums[$member->backingType][] = $member->enum;
            }
        }
        foreach ($enums as $backingType => $names) {
            $indexed[$backingType] = new EnumUnionNode($class, $names);
        }
        if (array_key_exists('float', $indexed) && !array_key_exists('int', $indexed)) {
            $indexed['int'] = $indexed['float'];
        }
        return new self($class, $type, $indexed, $indexed['callable'] ?? null);
    }

    #[Override]
    public function decode(mixed $value, string $path): mixed
    {
        $valueType = match ($value) {
            true => 'true',
            false => 'false',
            default => get_debug_type($value),
        };
        $member = $this->members[$valueType] ?? $this->fallback;
        return $member === null
            ? DecodeError::fieldTypeMismatch($this->class, $path, $this->type, $value)
            : $member->decode($value, $path);
    }

    #[Override]
    public function valueTypes(): array
    {
        return [];
    }

    #[Override]
    public function dependencies(): array
    {
        $dependencies = [];
        foreach ($this->members as $member) {
            $dependencies = [...$dependencies, ...$member->dependencies()];
        }
        return $dependencies;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->type;
    }
}
