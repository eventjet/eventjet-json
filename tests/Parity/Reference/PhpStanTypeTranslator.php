<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Parity\Reference;

use Eventjet\Json\Internal\PhpDocTupleEntry;
use Eventjet\Json\Internal\PhpDocType;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ConstTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IntersectionTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;

use function array_map;
use function array_values;
use function count;
use function in_array;

/**
 * Translates phpdoc-parser types into the decoder's {@see PhpDocType}. Syntax that the decoder does not support, such as
 * `?T`, `T[]`, callables, conditional types, object shapes, open shapes, and variance, translates to null, and so does
 * nesting beyond 64 levels, where unions and parentheses do not add a level.
 *
 * @internal
 */
final class PhpStanTypeTranslator
{
    private function __construct(
        private PhpStanConstants $constants,
    ) {}

    public static function translate(TypeNode $type): PhpStanType
    {
        $translator = new self(new PhpStanConstants());
        $translated = $translator->type($type, depth: 0);
        return $translated === null
            ? PhpStanType::none()
            : new PhpStanType($translated, $translator->constants->numbers());
    }

    private function type(TypeNode $type, int $depth): PhpDocType|null
    {
        if ($depth >= 64) {
            return null;
        }
        return match (true) {
            $type instanceof IdentifierTypeNode => new PhpDocType($type->name),
            $type instanceof UnionTypeNode => $this->compound('|', $type->types, $depth),
            $type instanceof IntersectionTypeNode => $this->compound('&', $type->types, $depth),
            $type instanceof GenericTypeNode => $this->generic($type, $depth),
            $type instanceof ArrayShapeNode => $this->shape($type, $depth),
            $type instanceof ConstTypeNode => new PhpDocType($this->constants->name($type->constExpr)),
            default => null,
        };
    }

    /** @param array<TypeNode> $members */
    private function compound(string $operator, array $members, int $depth): PhpDocType|null
    {
        $types = array_map(fn(TypeNode $member): PhpDocType|null => $this->type(
            $member,
            $depth,
        ), array_values($members));
        if (in_array(null, $types, strict: true)) {
            return null;
        }
        /** @var list<PhpDocType> $types */
        return new PhpDocType($operator, $types);
    }

    private function generic(GenericTypeNode $generic, int $depth): PhpDocType|null
    {
        $range = $generic->type->name === 'int' && count($generic->genericTypes) === 2;
        $arguments = [];
        foreach ($generic->genericTypes as $index => $argument) {
            $variance = $generic->variances[$index] ?? GenericTypeNode::VARIANCE_INVARIANT;
            $type = match (true) {
                $variance !== GenericTypeNode::VARIANCE_INVARIANT => null,
                $range && $argument instanceof ConstTypeNode && ($depth + 1) < 64
                    => new PhpDocType($this->constants->bound($argument->constExpr)),
                default => $this->type($argument, $depth + 1),
            };
            if ($type === null) {
                return null;
            }
            $arguments[] = $type;
        }
        return new PhpDocType($generic->type->name, $arguments);
    }

    private function shape(ArrayShapeNode $shape, int $depth): PhpDocType|null
    {
        if (!$shape->sealed) {
            return null;
        }
        $entries = [];
        foreach ($shape->items as $item) {
            $type = $this->type($item->valueType, $depth + 1);
            if ($type === null) {
                return null;
            }
            $key = $this->constants->key($item->keyName);
            $entries[] = new PhpDocTupleEntry($type, $key === null ? null : new PhpDocType($key), $item->optional);
        }
        return new PhpDocType($shape->kind . '{}', entries: $entries);
    }
}
