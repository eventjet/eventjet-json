<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\Internal\Node\MapNode;
use Eventjet\Json\Internal\Node\Node;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function in_array;
use function sort;

/**
 * Compares the members of a PHPDoc collection union with its native declaration.
 *
 * @internal
 */
final readonly class CollectionUnionNatives
{
    /** @param array<ReflectionType> $natives */
    public function __construct(
        private ReflectionParameter|ReflectionProperty $field,
        private array $natives,
    ) {}

    /**
     * Whether the PHPDoc names the same types as the native declaration. A literal names its native type.
     *
     * @param array<array-key, Node|PhpDocLiteral|string> $members
     * @throws ReflectionException
     */
    public function match(array $members): bool
    {
        if (in_array(ArrayObject::class, $members, strict: true)) {
            return false;
        }
        $natives = [];
        foreach ($this->natives as $native) {
            $name = $this->name($native);
            $natives[] = $name;
            if ($name !== 'null' && $native->allowsNull()) {
                $natives[] = 'null';
            }
        }
        $declared = array_unique(array_map(static fn(Node|PhpDocLiteral|string $member): string => match (true) {
            $member instanceof MapNode && $member->arrayObject => ArrayObject::class,
            $member instanceof Node => 'array',
            $member instanceof PhpDocLiteral => PhpDocLiteralNativeType::name($member, $natives),
            default => $member,
        }, $members));
        sort($declared);
        sort($natives);
        return $declared === $natives;
    }

    /**
     * Returns the native members other than collections, whose items the PHPDoc declares.
     *
     * @return list<ReflectionType>
     * @throws ReflectionException
     */
    public function members(): array
    {
        return array_values(array_filter(
            $this->natives,
            /** @throws ReflectionException */
            fn(ReflectionType $native): bool => !in_array(
                $this->name($native),
                ['array', ArrayObject::class],
                strict: true,
            ),
        ));
    }

    /**
     * Returns the native members that the PHPDoc names as types. Literals restrict their native type, so a native type
     * that only literals name gets no member of its own. Collections get none either, because the PHPDoc declares them.
     *
     * @param array<array-key, Node|PhpDocLiteral|string> $members
     * @return list<ReflectionType>
     * @throws ReflectionException
     */
    public function named(array $members): array
    {
        return array_values(array_filter(
            $this->natives,
            /** @throws ReflectionException */
            fn(ReflectionType $native): bool => in_array($this->name($native), $members, strict: true),
        ));
    }

    /**
     * Returns the canonical name of a native member, or an empty string for an intersection, which names no type.
     *
     * @throws ReflectionException
     */
    private function name(ReflectionType $native): string
    {
        return $native instanceof ReflectionNamedType
            ? PhpDocClassNameResolver::canonical(FieldTypeNameResolver::resolve($this->field, $native))
            : '';
    }
}
