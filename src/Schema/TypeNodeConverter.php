<?php

declare(strict_types=1);

namespace Eventjet\Json\Schema;

use BackedEnum;
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
use PHPStan\PhpDocParser\Ast\Type\ObjectShapeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;

use function array_map;
use function array_values;
use function count;
use function in_array;
use function intval;
use function is_a;
use function sort;
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
        if ($node instanceof ObjectShapeNode) {
            return $this->convertObjectShapeNode($node);
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
            'string', 'literal-string', 'callable-string' => Schema::string(),
            'non-empty-string' => Schema::string()->withMinLength(1),
            'non-falsy-string', 'truthy-string' => Schema::string()->withPattern('^(?!0$).+'),
            'lowercase-string' => Schema::string()->withPattern('^[^A-Z]*$'),
            'numeric-string' => Schema::string()->withPattern('^-?(?:0|[1-9]\d*)(?:\.\d+)?(?:[eE][+-]?\d+)?$'),
            'class-string' => Schema::string(),
            'int', 'integer' => Schema::integer(),
            'positive-int' => Schema::integer()->withMinimum(1),
            'negative-int' => Schema::integer()->withExclusiveMaximum(0),
            'non-negative-int' => Schema::integer()->withMinimum(0),
            'non-positive-int' => Schema::integer()->withMaximum(0),
            'non-zero-int' => Schema::anyOf([
                Schema::integer()->withMinimum(1),
                Schema::integer()->withMaximum(-1),
            ]),
            'float', 'double' => Schema::number(),
            'number' => Schema::number(),
            'bool', 'boolean' => Schema::boolean(),
            'true' => Schema::const(true),
            'false' => Schema::const(false),
            'null' => Schema::null(),
            'mixed' => Schema::mixed(),
            'array' => Schema::mixed(),
            'list' => Schema::array(Schema::mixed()),
            'iterable' => Schema::array(Schema::mixed()),
            'object' => Schema::map(Schema::mixed()),
            'scalar' => Schema::anyOf([Schema::string(), Schema::number(), Schema::boolean()]),
            'numeric' => Schema::anyOf([
                Schema::number(),
                Schema::string()->withPattern('^-?(?:0|[1-9]\d*)(?:\.\d+)?(?:[eE][+-]?\d+)?$'),
            ]),
            'array-key' => Schema::anyOf([Schema::string(), Schema::integer()]),
            'never', 'never-return', 'never-returns', 'no-return' => Schema::never(),
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
            'iterable' => $this->convertGenericIterable($node),
            'int' => $this->convertIntRange($node),
            'int-mask' => $this->convertIntMask($node),
            'value-of' => $this->convertValueOf($node),
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

    private function convertGenericIterable(GenericTypeNode $node): Schema
    {
        if (count($node->genericTypes) === 1) {
            return Schema::array($this->convert($node->genericTypes[0]));
        }
        return Schema::map($this->convert($node->genericTypes[1]));
    }

    private function convertIntMask(GenericTypeNode $node): Schema
    {
        $bits = [];
        foreach ($node->genericTypes as $type) {
            $value = $this->extractIntBound($type);
            if ($value !== null) {
                $bits[] = $value;
            }
        }
        $combinations = [0];
        foreach ($bits as $bit) {
            $newCombinations = [];
            foreach ($combinations as $existing) {
                $newCombinations[] = $existing | $bit;
            }
            foreach ($newCombinations as $c) {
                if (!in_array($c, $combinations, true)) {
                    $combinations[] = $c;
                }
            }
        }
        sort($combinations);
        return Schema::enum($combinations);
    }

    private function convertValueOf(GenericTypeNode $node): Schema
    {
        $innerType = $node->genericTypes[0];
        if (!$innerType instanceof IdentifierTypeNode) {
            throw new UnsupportedTypeException(sprintf('value-of only supports identifier types, got: %s', $innerType));
        }
        /** @var class-string $className */
        $className = $innerType->name;
        if (!is_a($className, BackedEnum::class, true)) {
            throw new UnsupportedTypeException(
                sprintf('value-of only supports BackedEnum, got: %s', $innerType->name),
            );
        }
        return $this->convertClassReference($className);
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
        if ($this->isTupleShape($node)) {
            return $this->convertTupleShape($node);
        }
        return $this->convertObjectShape($node);
    }

    private function isTupleShape(ArrayShapeNode $node): bool
    {
        if ($node->kind === ArrayShapeNode::KIND_LIST || $node->kind === ArrayShapeNode::KIND_NON_EMPTY_LIST) {
            return true;
        }
        foreach ($node->items as $index => $item) {
            if ($item->keyName === null) {
                continue;
            }
            if ($item->keyName instanceof ConstExprIntegerNode && intval($item->keyName->value) === $index) {
                continue;
            }
            return false;
        }
        return true;
    }

    private function convertTupleShape(ArrayShapeNode $node): Schema
    {
        $prefixItems = [];
        $minItems = 0;
        $position = 0;
        foreach ($node->items as $item) {
            $prefixItems[] = $this->convert($item->valueType);
            $position++;
            if (!$item->optional) {
                $minItems = $position;
            }
        }
        if (
            $minItems < 1
            && ($node->kind === ArrayShapeNode::KIND_NON_EMPTY_LIST
                || $node->kind === ArrayShapeNode::KIND_NON_EMPTY_ARRAY)
        ) {
            $minItems = 1;
        }
        $schema = Schema::tuple($prefixItems);
        if (!$node->sealed && $node->unsealedType !== null) {
            $schema = $schema->withItems($this->convert($node->unsealedType->valueType));
        } elseif (!$node->sealed) {
            $schema = $schema->withItems(null);
        }
        if ($minItems > 0) {
            $schema = $schema->withMinItems($minItems);
        }
        return $schema;
    }

    private function convertObjectShape(ArrayShapeNode $node): Schema
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
        if (!$node->sealed && $node->unsealedType !== null) {
            return Schema::object($properties, $required, $this->convert($node->unsealedType->valueType));
        }
        if (!$node->sealed) {
            return Schema::object($properties, $required, true);
        }
        $schema = Schema::object($properties, $required);
        if ($node->kind === ArrayShapeNode::KIND_NON_EMPTY_ARRAY) {
            $schema = $schema->withMinProperties(1);
        }
        return $schema;
    }

    private function convertObjectShapeNode(ObjectShapeNode $node): Schema
    {
        $properties = [];
        $required = [];
        foreach ($node->items as $item) {
            $key = (string)$item->keyName;
            $properties[$key] = $this->convert($item->valueType);
            if (!$item->optional) {
                $required[] = $key;
            }
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
