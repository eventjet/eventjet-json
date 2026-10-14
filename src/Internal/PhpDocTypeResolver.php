<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;

use function array_any;
use function array_filter;
use function array_key_exists;
use function array_map;
use function array_merge;
use function array_values;
use function count;
use function strcasecmp;

/**
 * Resolves the names in the PHPDoc type of a field, so that it reads like a native type: scalar refinements become their
 * scalar type, class names their declared spelling, and constants their literal values.
 *
 * @internal
 */
final readonly class PhpDocTypeResolver
{
    private const array COLLECTIONS = [
        'list' => true,
        'non-empty-list' => true,
        'array' => true,
        'non-empty-array' => true,
        'non-empty-map' => true,
    ];

    /** Names that resolve to themselves. */
    private const array KEYWORDS = [
        'null' => true,
        'true' => true,
        'false' => true,
        'resource' => true,
        'open-resource' => true,
        'closed-resource' => true,
        'bool' => true,
        'float' => true,
        'int' => true,
        'string' => true,
    ];

    private const array SCALARS = ['bool' => true, 'float' => true, 'int' => true, 'string' => true];

    public function __construct(
        private ReflectionParameter|ReflectionProperty $field,
    ) {}

    /**
     * Returns null if the type refers to an unknown name or uses a form that cannot be decoded, such as an intersection.
     *
     * @throws ReflectionException
     */
    public function resolve(PhpDocType $type): PhpDocType|null
    {
        $integerRange = $type->name === 'int' && $type->isIntegerRange();
        return match (true) {
            $type->name === '|' => $this->union($type->arguments),
            $type->name === 'array{}' => $this->shape($type->entries),
            $integerRange => new PhpDocType('int'),
            $type->arguments !== [] => $this->generic($type),
            default => $this->name($type),
        };
    }

    /**
     * Returns the first member that looks like a literal but resolves to no value, such as `1e999`.
     *
     * @throws ReflectionException
     */
    public function unresolvedLiteral(PhpDocType $type): string|null
    {
        $unresolved = array_filter(
            $type->members(),
            /** @throws ReflectionException */
            fn(PhpDocType $member): bool => (
                PhpDocType::literalSyntax($member->name)
                && $this->resolve($member) === null
            ),
        );
        return $unresolved === [] ? null : array_values($unresolved)[0]->name;
    }

    /** Returns `ArrayObject` if the name of a generic type refers to it. */
    public function container(string $name): string|null
    {
        $container = FieldTypeNameResolver::resolvePhpDoc($this->field, $name) ?? $name;
        return strcasecmp($container, ArrayObject::class) === 0 ? ArrayObject::class : null;
    }

    /**
     * Resolves the members of a union. A constant that stands for several values adds a member for each.
     *
     * @param list<PhpDocType> $members
     * @throws ReflectionException
     */
    private function union(array $members): PhpDocType|null
    {
        $nested = array_any($members, static fn(PhpDocType $member): bool => $member->name === '|');
        $resolved = $nested ? null : $this->each($members);
        return $resolved === null
            ? null
            : new PhpDocType(
                '|',
                array_merge(...array_map(
                    /** @return list<PhpDocType> */
                    static fn(PhpDocType $member): array => $member->members(),
                    $resolved,
                )),
            );
    }

    /**
     * @param list<PhpDocTupleEntry> $entries
     * @throws ReflectionException
     */
    private function shape(array $entries): PhpDocType|null
    {
        $types = $this->each(array_map(static fn(PhpDocTupleEntry $entry): PhpDocType => $entry->type, $entries));
        return $types === null
            ? null
            : new PhpDocType('array{}', entries: array_map(
                static fn(PhpDocTupleEntry $entry, PhpDocType $type): PhpDocTupleEntry => new PhpDocTupleEntry(
                    $type,
                    $entry->key,
                    $entry->optional,
                ),
                $entries,
                $types,
            ));
    }

    /** @throws ReflectionException */
    private function generic(PhpDocType $type): PhpDocType|null
    {
        $name = array_key_exists($type->name, self::COLLECTIONS) ? $type->name : $this->container($type->name);
        $arguments = $this->each($type->arguments);
        return $name === null || $arguments === null ? null : new PhpDocType($name, $arguments);
    }

    /** @throws ReflectionException */
    private function name(PhpDocType $type): PhpDocType|null
    {
        $name = $type->name;
        $names = match (true) {
            array_key_exists($name, self::KEYWORDS) => [$name],
            PhpDocType::literalSyntax($name) => PhpDocConstantResolver::literals($this->field, $name),
            default => $this->named($name),
        };
        return match (true) {
            $names === null => null,
            count($names) === 1 => $names[0] === $name ? $type : new PhpDocType($names[0]),
            default => new PhpDocType('|', array_map(
                static fn(string $literal): PhpDocType => new PhpDocType($literal),
                $names,
            )),
        };
    }

    /**
     * Resolves a scalar or class name, or else a constant. Class names take their declared spelling, which can differ
     * in case from the PHPDoc reference.
     *
     * @return list<string>|null
     * @throws ReflectionException
     */
    private function named(string $name): array|null
    {
        $resolved = FieldTypeNameResolver::resolvePhpDoc($this->field, $name);
        return match (true) {
            $resolved === null => PhpDocConstantResolver::literals($this->field, $name),
            array_key_exists($resolved, self::SCALARS) => [$resolved],
            default => [PhpDocClassNameResolver::canonical($resolved)],
        };
    }

    /**
     * @param list<PhpDocType> $types
     * @return list<PhpDocType>|null
     * @throws ReflectionException
     */
    private function each(array $types): array|null
    {
        $resolved = [];
        foreach ($types as $type) {
            $type = $this->resolve($type);
            if ($type === null) {
                return null;
            }
            $resolved[] = $type;
        }
        return $resolved;
    }
}
