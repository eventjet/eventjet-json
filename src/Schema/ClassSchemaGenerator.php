<?php

declare(strict_types=1);

namespace Eventjet\Json\Schema;

use BackedEnum;
use Eventjet\Json\Schema\Exception\UnsupportedTypeException;
use Eventjet\Json\UseStatementResolver;
use JsonSerializable;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeItemNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ObjectShapeItemNode;
use PHPStan\PhpDocParser\Ast\Type\ObjectShapeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use ReflectionClass;
use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;

use function array_key_exists;
use function array_map;
use function array_values;
use function assert;
use function in_array;
use function is_a;
use function ltrim;
use function sprintf;
use function strtolower;

final class ClassSchemaGenerator
{
    private readonly Lexer $lexer;
    private readonly PhpDocParser $phpDocParser;
    private readonly UseStatementResolver $useStatementResolver;

    /** @psalm-suppress PropertyNotSetInConstructor - Injected via setTypeNodeConverter() to break circular dependency */
    private TypeNodeConverter $typeNodeConverter;

    public function __construct(
        private readonly SchemaRegistry $registry,
    ) {
        $config = new ParserConfig([]);
        $this->lexer = new Lexer($config);
        $constExprParser = new ConstExprParser($config);
        $typeParser = new TypeParser($config, $constExprParser);
        $this->phpDocParser = new PhpDocParser($config, $typeParser, $constExprParser);
        $this->useStatementResolver = new UseStatementResolver();
    }

    /**
     * @internal
     */
    public function setTypeNodeConverter(TypeNodeConverter $converter): void
    {
        $this->typeNodeConverter = $converter;
    }

    /**
     * @param class-string $className
     */
    public function generate(string $className): Schema
    {
        assert(
            !$this->registry->isInProgress($className),
            'TypeNodeConverter checks isInProgress before calling generate(), so this should never be reached',
        );
        if ($this->registry->has($className)) {
            return Schema::ref($this->registry->refPath($className));
        }
        $this->registry->markInProgress($className);
        $schema = $this->doGenerate($className);
        $this->registry->register($className, $schema);
        return Schema::ref($this->registry->refPath($className));
    }

    /**
     * @param class-string $className
     */
    private function doGenerate(string $className): Schema
    {
        $reflection = new ReflectionClass($className);
        if ($reflection->isEnum()) {
            return $this->generateEnum($className);
        }
        if (is_a($className, JsonSerializable::class, true)) {
            return $this->generateJsonSerializable($reflection);
        }
        return $this->generateFromProperties($reflection);
    }

    /**
     * @param class-string $className
     */
    private function generateEnum(string $className): Schema
    {
        if (!is_a($className, BackedEnum::class, true)) {
            throw new UnsupportedTypeException(sprintf('Unit enums are not supported: %s', $className));
        }
        $reflection = new ReflectionEnum($className);
        $values = [];
        foreach ($reflection->getCases() as $case) {
            /** @var ReflectionEnumBackedCase $case */
            $values[] = $case->getBackingValue();
        }
        return Schema::enum($values);
    }

    /**
     * @param ReflectionClass<object> $reflection
     */
    private function generateJsonSerializable(ReflectionClass $reflection): Schema
    {
        $method = $reflection->getMethod('jsonSerialize');
        $typeNode = $this->getReturnTypeNode($method, $reflection->getName());
        if ($typeNode !== null) {
            return $this->typeNodeConverter->convert($typeNode);
        }
        $returnType = $method->getReturnType();
        if ($returnType instanceof ReflectionNamedType) {
            return $this->reflectionTypeToSchema($returnType, $reflection->getName());
        }
        return Schema::mixed();
    }

    /**
     * @param ReflectionClass<object> $reflection
     */
    private function generateFromProperties(ReflectionClass $reflection): Schema
    {
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        $schemaProperties = [];
        $required = [];
        $constructorDocParams = $this->getConstructorParamTypes($reflection);
        foreach ($properties as $property) {
            if ($property->isStatic()) {
                continue;
            }
            $name = $property->getName();
            $schemaProperties[$name] = $this->resolvePropertyType($property, $reflection, $constructorDocParams);
            $required[] = $name;
        }
        return Schema::object($schemaProperties, $required);
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @param array<string, TypeNode> $constructorDocParams
     */
    private function resolvePropertyType(
        ReflectionProperty $property,
        ReflectionClass $reflection,
        array $constructorDocParams,
    ): Schema {
        $varType = $this->getVarTypeNode($property, $reflection->getName());
        if ($varType !== null) {
            return $this->typeNodeConverter->convert($varType);
        }
        $paramName = $property->getName();
        if (array_key_exists($paramName, $constructorDocParams)) {
            return $this->typeNodeConverter->convert($constructorDocParams[$paramName]);
        }
        $nativeType = $property->getType();
        if ($nativeType instanceof ReflectionNamedType) {
            return $this->reflectionTypeToSchema($nativeType, $reflection->getName());
        }
        if ($nativeType instanceof ReflectionUnionType) {
            $schemas = [];
            foreach ($nativeType->getTypes() as $type) {
                if ($type instanceof ReflectionNamedType) {
                    $schemas[] = $this->reflectionTypeToSchema($type, $reflection->getName());
                }
            }
            return Schema::anyOf($schemas);
        }
        return Schema::mixed();
    }

    private function reflectionTypeToSchema(ReflectionNamedType $type, string $contextClass): Schema
    {
        $name = $type->getName();
        $schema = $this->resolveNamedType($name, $contextClass);
        if ($type->allowsNull() && $name !== 'null' && $name !== 'mixed') {
            return Schema::anyOf([$schema, Schema::null()]);
        }
        return $schema;
    }

    private function resolveNamedType(string $typeName, string $contextClass): Schema
    {
        return match ($typeName) {
            'string' => Schema::string(),
            'int' => Schema::integer(),
            'float' => Schema::number(),
            'bool' => Schema::boolean(),
            'null' => Schema::null(),
            'mixed' => Schema::mixed(),
            'array' => Schema::mixed(),
            default => $this->typeNodeConverter->convert(
                new IdentifierTypeNode($this->useStatementResolver->resolve($typeName, $contextClass)),
            ),
        };
    }

    private function getReturnTypeNode(ReflectionMethod $method, string $contextClass): TypeNode|null
    {
        $docComment = $method->getDocComment();
        if ($docComment === false) {
            return null;
        }
        $phpDoc = $this->parseDocblock($docComment);
        $returnTags = $phpDoc->getReturnTagValues();
        if ($returnTags === []) {
            return null;
        }
        $returnTag = array_values($returnTags)[0];
        return $this->resolveTypeNodeClassNames($returnTag->type, $contextClass);
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @return array<string, TypeNode>
     */
    private function getConstructorParamTypes(ReflectionClass $reflection): array
    {
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return [];
        }
        $docComment = $constructor->getDocComment();
        if ($docComment === false) {
            return [];
        }
        $phpDoc = $this->parseDocblock($docComment);
        $paramTags = $phpDoc->getParamTagValues();
        $result = [];
        foreach ($paramTags as $paramTag) {
            $name = ltrim($paramTag->parameterName, '$');
            $result[$name] = $this->resolveTypeNodeClassNames($paramTag->type, $reflection->getName());
        }
        return $result;
    }

    private function getVarTypeNode(ReflectionProperty $property, string $contextClass): TypeNode|null
    {
        $docComment = $property->getDocComment();
        if ($docComment === false) {
            return null;
        }
        $phpDoc = $this->parseDocblock($docComment);
        $varTags = $phpDoc->getVarTagValues();
        if ($varTags === []) {
            return null;
        }
        $varTag = array_values($varTags)[0];
        return $this->resolveTypeNodeClassNames($varTag->type, $contextClass);
    }

    private function parseDocblock(string $docComment): PhpDocNode
    {
        $tokens = new TokenIterator($this->lexer->tokenize($docComment));
        return $this->phpDocParser->parse($tokens);
    }

    private function resolveTypeNodeClassNames(TypeNode $node, string $contextClass): TypeNode
    {
        if ($node instanceof IdentifierTypeNode) {
            $lower = strtolower($node->name);
            if ($lower === 'self' || $lower === 'static') {
                return new IdentifierTypeNode($contextClass);
            }
            $builtins = [
                'string', 'int', 'integer', 'float', 'double', 'bool', 'boolean',
                'null', 'mixed', 'never', 'true', 'false',
                'class-string', 'non-empty-string', 'numeric-string',
                'positive-int', 'negative-int', 'non-negative-int', 'non-positive-int',
                'array', 'list', 'iterable', 'object', 'scalar', 'number', 'numeric',
                'array-key', 'non-zero-int',
                'literal-string', 'callable-string', 'lowercase-string',
                'non-falsy-string', 'truthy-string',
                'never-return', 'never-returns', 'no-return',
            ];
            if (in_array($lower, $builtins, true)) {
                return $node;
            }
            return new IdentifierTypeNode($this->useStatementResolver->resolve($node->name, $contextClass));
        }
        if ($node instanceof GenericTypeNode) {
            $resolvedTypes = array_map(
                fn(TypeNode $t): TypeNode => $this->resolveTypeNodeClassNames($t, $contextClass),
                $node->genericTypes,
            );
            return new GenericTypeNode($node->type, $resolvedTypes, $node->variances);
        }
        if ($node instanceof UnionTypeNode) {
            $resolvedTypes = array_map(
                fn(TypeNode $t): TypeNode => $this->resolveTypeNodeClassNames($t, $contextClass),
                $node->types,
            );
            return new UnionTypeNode($resolvedTypes);
        }
        if ($node instanceof NullableTypeNode) {
            return new NullableTypeNode(
                $this->resolveTypeNodeClassNames($node->type, $contextClass),
            );
        }
        if ($node instanceof ArrayTypeNode) {
            return new ArrayTypeNode(
                $this->resolveTypeNodeClassNames($node->type, $contextClass),
            );
        }
        if ($node instanceof ArrayShapeNode) {
            $resolvedItems = array_map(
                fn(ArrayShapeItemNode $item): ArrayShapeItemNode => new ArrayShapeItemNode(
                    $item->keyName,
                    $item->optional,
                    $this->resolveTypeNodeClassNames($item->valueType, $contextClass),
                ),
                $node->items,
            );
            if ($node->sealed) {
                return ArrayShapeNode::createSealed($resolvedItems, $node->kind);
            }
            return ArrayShapeNode::createUnsealed(
                $resolvedItems,
                $node->unsealedType,
                $node->kind,
            );
        }
        if ($node instanceof ObjectShapeNode) {
            $resolvedItems = array_map(
                fn(ObjectShapeItemNode $item): ObjectShapeItemNode => new ObjectShapeItemNode(
                    $item->keyName,
                    $item->optional,
                    $this->resolveTypeNodeClassNames($item->valueType, $contextClass),
                ),
                $node->items,
            );
            return new ObjectShapeNode($resolvedItems);
        }
        return $node;
    }
}
