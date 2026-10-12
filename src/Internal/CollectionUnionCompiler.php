<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use ArrayObject;
use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\ItemNode;
use Eventjet\Json\Internal\Node\MapNode;
use Eventjet\Json\Internal\Node\ScalarNode;
use Eventjet\Json\Internal\Node\UnionNode;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

use function array_map;
use function array_unique;
use function implode;
use function in_array;
use function is_array;
use function sort;

/**
 * Compiles a field whose native type combines a collection with other types, such as `?array` or `array|string`.
 *
 * @internal
 */
final readonly class CollectionUnionCompiler
{
    /** @var class-string */
    private string $class;
    private PhpDocUnionCompiler $unions;

    public function __construct(
        private TypeCompiler $types,
        private ReflectionParameter|ReflectionProperty $field,
    ) {
        $this->class = $types->class;
        $this->unions = new PhpDocItemCompiler($types, $field)->unions();
    }

    /**
     * The PHPDoc union declares the collection items and must name the same types as the native declaration.
     *
     * @throws ReflectionException
     */
    public function compile(ReflectionNamedType|ReflectionUnionType $native): UnionNode|DecodeError
    {
        $doc = PhpDocFieldType::resolve($this->field);
        $path = $this->field->getName();
        $members = $doc?->name === '|' ? $this->unions->members($doc->arguments, $path) : null;
        if (!is_array($members)) {
            return $members ?? PhpDocFieldType::unrecognized($this->class, $this->field);
        }
        $union = new UnionBuilder($this->class, $path);
        $natives = $native instanceof ReflectionUnionType ? $native->getTypes() : [$native];
        $error = NativeUnionCompiler::addMembers($this->types, $this->field, $natives, $union);
        if ($error !== null) {
            return $error;
        }
        $nativeNames = $this->nativeNames($natives);
        $declaresSameTypes = self::declaresSameTypes($members, $nativeNames);
        if (!$declaresSameTypes) {
            return PhpDocFieldType::unrecognized($this->class, $this->field);
        }
        foreach ($members as $member) {
            $node = match (true) {
                $member instanceof ItemNode => $member,
                $member === 'null' => new ScalarNode($this->class, 'null', nullable: true),
                default => null,
            };
            if ($node !== null) {
                $union->add($node);
            }
        }
        return $union->build(implode('|', $members));
    }

    /**
     * Returns the native type names, including null for a nullable type such as `?array`.
     *
     * @param array<ReflectionType> $natives
     * @return list<string>
     * @throws ReflectionException
     */
    private function nativeNames(array $natives): array
    {
        $names = [];
        foreach ($natives as $native) {
            /** @var ReflectionNamedType $native Intersections were rejected while adding members. */
            $name = PhpDocClassNameResolver::canonical(FieldTypeNameResolver::resolve($this->field, $native));
            $names[] = $name;
            if ($native->allowsNull() && $name !== 'null') {
                $names[] = 'null';
            }
        }
        return $names;
    }

    /**
     * @param array<string, ItemNode|string> $members
     * @param list<string> $nativeNames
     */
    private static function declaresSameTypes(array $members, array $nativeNames): bool
    {
        if (in_array(ArrayObject::class, $members, strict: true)) {
            return false;
        }
        $declared = array_unique(array_map(static fn(ItemNode|string $member): string => match (true) {
            $member instanceof MapNode && $member->arrayObject => ArrayObject::class,
            $member instanceof ItemNode => 'array',
            default => $member,
        }, $members));
        sort($declared);
        sort($nativeNames);
        return $declared === $nativeNames;
    }
}
