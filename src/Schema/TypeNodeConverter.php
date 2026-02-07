<?php

declare(strict_types=1);

namespace Eventjet\Json\Schema;

use Eventjet\Json\Schema\Exception\UnsupportedTypeException;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprFalseNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprFloatNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprIntegerNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprNullNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprTrueNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ConstTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IntersectionTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;

use function array_map;
use function array_values;
use function count;
use function intval;
use function sprintf;
use function strtolower;

final class TypeNodeConverter
{
    public function __construct(
        private readonly SchemaRegistry $registry,
        private readonly ClassSchemaGenerator $classGenerator,
    ) {
    }

    public function convert(TypeNode $node): Schema
    {
        if ($node instanceof IdentifierTypeNode) {
            return $this->convertIdentifier($node);
        }
        if ($node instanceof GenericTypeNode) {
            return $this->convertGeneric($node);
        }
        if ($node instanceof UnionTypeNode) {
            return $this->convertUnion($node);
        }
        if ($node instanceof NullableTypeNode) {
            return Schema::anyOf([$this->convert($node->type), Schema::null()]);
        }
        if ($node instanceof ArrayTypeNode) {
            return Schema::array($this->convert($node->type));
        }
        if ($node instanceof ArrayShapeNode) {
            return $this->convertArrayShape($node);
        }
        if ($node instanceof ConstTypeNode) {
            return $this->convertConst($node);
        }
        if ($node instanceof IntersectionTypeNode) {
            throw new UnsupportedTypeException(sprintf('Intersection types are not supported: %s', $node));
        }
        throw new UnsupportedTypeException(sprintf('Unsupported type node: %s', $node));
    }

    private function convertIdentifier(IdentifierTypeNode $node): Schema
    {
        return match (strtolower($node->name)) {
            'string' => Schema::string(),
            'non-empty-string' => Schema::string()->withMinLength(1),
            'numeric-string' => Schema::string()->withPattern('^-?(?:0|[1-9]\d*)(?:\.\d+)?(?:[eE][+-]?\d+)?$'),
            'class-string' => Schema::string(),
            'int', 'integer' => Schema::integer(),
            'positive-int' => Schema::integer()->withMinimum(1),
            'negative-int' => Schema::integer()->withExclusiveMaximum(0),
            'non-negative-int' => Schema::integer()->withMinimum(0),
            'non-positive-int' => Schema::integer()->withMaximum(0),
            'float', 'double' => Schema::number(),
            'bool', 'boolean' => Schema::boolean(),
            'true' => Schema::const(true),
            'false' => Schema::const(false),
            'null' => Schema::null(),
            'mixed' => Schema::mixed(),
            'never' => Schema::never(),
            default => $this->convertClassReference($node->name),
        };
    }

    private function convertClassReference(string $className): Schema
    {
        if ($this->registry->isInProgress($className)) {
            return Schema::ref($this->registry->refPath($className));
        }
        if ($this->registry->has($className)) {
            return Schema::ref($this->registry->refPath($className));
        }
        /** @var class-string $className */
        return $this->classGenerator->generate($className);
    }

    private function convertGeneric(GenericTypeNode $node): Schema
    {
        $baseName = strtolower($node->type->name);
        return match ($baseName) {
            'list' => Schema::array($this->convert($node->genericTypes[0])),
            'non-empty-list' => Schema::array($this->convert($node->genericTypes[0]))->withMinItems(1),
            'array' => $this->convertGenericArray($node),
            'non-empty-array' => $this->convertGenericArray($node)->withMinProperties(1),
            'int' => $this->convertIntRange($node),
            'class-string' => Schema::string(),
            default => throw new UnsupportedTypeException(sprintf('Unsupported generic type: %s', $node)),
        };
    }

    private function convertGenericArray(GenericTypeNode $node): Schema
    {
        if (count($node->genericTypes) === 1) {
            return Schema::array($this->convert($node->genericTypes[0]));
        }
        return Schema::map($this->convert($node->genericTypes[1]));
    }

    private function convertIntRange(GenericTypeNode $node): Schema
    {
        $schema = Schema::integer();
        if (count($node->genericTypes) >= 1) {
            $min = $this->extractIntBound($node->genericTypes[0]);
            if ($min !== null) {
                $schema = $schema->withMinimum($min);
            }
        }
        if (count($node->genericTypes) >= 2) {
            $max = $this->extractIntBound($node->genericTypes[1]);
            if ($max !== null) {
                $schema = $schema->withMaximum($max);
            }
        }
        return $schema;
    }

    private function extractIntBound(TypeNode $node): int|null
    {
        if ($node instanceof IdentifierTypeNode && strtolower($node->name) === 'min') {
            return null;
        }
        if ($node instanceof IdentifierTypeNode && strtolower($node->name) === 'max') {
            return null;
        }
        if ($node instanceof ConstTypeNode && $node->constExpr instanceof ConstExprIntegerNode) {
            return intval($node->constExpr->value);
        }
        if ($node instanceof IdentifierTypeNode) {
            return intval($node->name);
        }
        return null;
    }

    private function convertUnion(UnionTypeNode $node): Schema
    {
        $constValues = $this->extractConstValues($node);
        if ($constValues !== null) {
            return Schema::enum($constValues);
        }
        $schemas = array_values(array_map(fn(TypeNode $type): Schema => $this->convert($type), $node->types));
        return Schema::anyOf($schemas);
    }

    /**
     * @return list<mixed>|null
     */
    private function extractConstValues(UnionTypeNode $node): array|null
    {
        $values = [];
        foreach ($node->types as $type) {
            if ($type instanceof ConstTypeNode) {
                $value = $this->constExprToValue($type);
                if ($value === null) {
                    return null;
                }
                /** @psalm-suppress MixedAssignment - const values are intentionally mixed */
                $values[] = $value[0];
                continue;
            }
            return null;
        }
        return $values;
    }

    /**
     * @return array{0: mixed}|null
     */
    private function constExprToValue(ConstTypeNode $node): array|null
    {
        $expr = $node->constExpr;
        if ($expr instanceof ConstExprIntegerNode) {
            return [intval($expr->value)];
        }
        if ($expr instanceof ConstExprFloatNode) {
            return [(float)$expr->value];
        }
        if ($expr instanceof ConstExprStringNode) {
            return [$expr->value];
        }
        if ($expr instanceof ConstExprTrueNode) {
            return [true];
        }
        if ($expr instanceof ConstExprFalseNode) {
            return [false];
        }
        if ($expr instanceof ConstExprNullNode) {
            return [null];
        }
        return null;
    }

    private function convertArrayShape(ArrayShapeNode $node): Schema
    {
        $properties = [];
        $required = [];
        foreach ($node->items as $item) {
            $key = $item->keyName !== null ? (string)$item->keyName : (string)count($properties);
            $properties[$key] = $this->convert($item->valueType);
            if (!$item->optional) {
                $required[] = $key;
            }
        }
        if (!$node->sealed) {
            return Schema::object($properties, $required, true);
        }
        return Schema::object($properties, $required);
    }

    private function convertConst(ConstTypeNode $node): Schema
    {
        $expr = $node->constExpr;
        if ($expr instanceof ConstExprIntegerNode) {
            return Schema::const(intval($expr->value));
        }
        if ($expr instanceof ConstExprFloatNode) {
            return Schema::const((float)$expr->value);
        }
        if ($expr instanceof ConstExprStringNode) {
            return Schema::const($expr->value);
        }
        if ($expr instanceof ConstExprTrueNode) {
            return Schema::const(true);
        }
        if ($expr instanceof ConstExprFalseNode) {
            return Schema::const(false);
        }
        if ($expr instanceof ConstExprNullNode) {
            return Schema::const(null);
        }
        throw new UnsupportedTypeException(sprintf('Unsupported const expression: %s', $node));
    }
}
