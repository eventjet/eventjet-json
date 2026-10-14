<?php

declare(strict_types=1);

namespace Eventjet\Json\Internal;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\Node\Node;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionUnionType;

use function implode;
use function is_array;
use function is_string;

/**
 * Compiles a field whose native type combines a collection with other types, such as `?array` or `array|string`.
 *
 * @internal
 */
final readonly class CollectionUnionCompiler
{
    private PhpDocItemCompiler $items;

    public function __construct(
        private TypeCompiler $types,
        private ReflectionParameter|ReflectionProperty $field,
    ) {
        $this->items = new PhpDocItemCompiler($field);
    }

    /**
     * The PHPDoc union declares the collection items and must name the same types as the native declaration.
     *
     * @throws ReflectionException
     */
    public function compile(ReflectionNamedType|ReflectionUnionType $native): Node|DecodeError
    {
        $doc = PhpDocFieldType::resolve($this->field);
        $path = $this->field->getName();
        $members = $doc?->name === '|' ? $this->items->unions()->members($doc->arguments, $path) : null;
        if (!is_array($members)) {
            return $members ?? PhpDocFieldType::unrecognized($this->field);
        }
        $types = $native instanceof ReflectionUnionType ? $native->getTypes() : [$native];
        $natives = new CollectionUnionNatives($this->field, $types);
        $matches = $natives->match($members);
        $union = new UnionBuilder($path);
        $declared = $matches ? $natives->named($members) : $natives->members();
        $error = NativeUnionCompiler::addMembers($this->types, $this->field, $declared, $union);
        if ($error !== null || !$matches) {
            return $error ?? PhpDocFieldType::unrecognized($this->field);
        }
        foreach ($members as $member) {
            $nativeMember = is_string($member) && $member !== 'null' && !PhpDocType::literalSyntax($member);
            if ($nativeMember) {
                continue;
            }
            $union->add($member instanceof Node ? $member : $this->items->literal($member));
        }
        return $union->build(implode('|', $members));
    }
}
