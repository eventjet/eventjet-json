<?php

declare(strict_types=1);

namespace Eventjet\Json\Schema;

use BackedEnum;
use Eventjet\Json\Schema\Attribute\Example;
use Eventjet\Json\Schema\Attribute\Format;
use Eventjet\Json\Schema\Attribute\OneOf;
use Eventjet\Json\Schema\Attribute\Pattern;
use Eventjet\Json\Schema\Attribute\UniqueItems;
use Eventjet\Json\Schema\Exception\UnsupportedTypeException;
use Eventjet\Json\UseStatementResolver;
use JsonSerializable;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode;
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
use function explode;
use function in_array;
use function is_a;
use function ltrim;
use function sprintf;
use function strtolower;
use function trim;

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
        $oneOf = $reflection->getAttributes(OneOf::class);
        if ($oneOf !== []) {
            return $this->generateOneOf($reflection, $oneOf[0]->newInstance());
        }
        if ($reflection->isEnum()) {
            return $this->generateEnum($className);
        }
        if (is_a($className, JsonSerializable::class, true)) {
            return $this->generateJsonSerializable($reflection);
        }
        return $this->generateFromProperties($reflection);
    }

    /**
     * @param ReflectionClass<object> $reflection
     */
    private function generateOneOf(ReflectionClass $reflection, OneOf $oneOf): Schema
    {
        $refs = [];
        foreach ($oneOf->variants as $variant) {
            $refs[] = $this->generate($variant);
        }
        return $this->applyClassMetadata(Schema::anyOf($refs), $reflection);
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
        /** @var ReflectionClass<object> $classReflection */
        $classReflection = new ReflectionClass($className);
        return $this->applyClassMetadata(Schema::enum($values), $classReflection);
    }

    /**
     * @param ReflectionClass<object> $reflection
     */
    private function generateJsonSerializable(ReflectionClass $reflection): Schema
    {
        $method = $reflection->getMethod('jsonSerialize');
        $typeNode = $this->getReturnTypeNode($method, $reflection->getName());
        if ($typeNode !== null) {
            return $this->applyClassMetadata($this->typeNodeConverter->convert($typeNode), $reflection);
        }
        $returnType = $method->getReturnType();
        if ($returnType instanceof ReflectionNamedType) {
            return $this->applyClassMetadata(
                $this->reflectionTypeToSchema($returnType, $reflection->getName()),
                $reflection,
            );
        }
        return $this->applyClassMetadata(Schema::mixed(), $reflection);
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
        $paramDescriptions = $this->getConstructorParamDescriptions($reflection);
        foreach ($properties as $property) {
            if ($property->isStatic()) {
                continue;
            }
            $name = $property->getName();
            $propertySchema = $this->resolvePropertyType($property, $reflection, $constructorDocParams);
            $propertySchema = $this->applyPropertyMetadata($propertySchema, $property);
            $paramDescription = $paramDescriptions[$name] ?? '';
            if ($paramDescription !== '' && !$this->propertyDocblockHasText($property)) {
                $propertySchema = $propertySchema->withDescription($paramDescription);
            }
            $schemaProperties[$name] = $propertySchema;
            $required[] = $name;
        }
        return $this->applyClassMetadata(Schema::object($schemaProperties, $required), $reflection);
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

    /**
     * @param ReflectionClass<object> $reflection
     * @return array<string, string>
     */
    private function getConstructorParamDescriptions(ReflectionClass $reflection): array
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
            $description = trim($paramTag->description);
            if ($description === '') {
                continue;
            }
            $name = ltrim($paramTag->parameterName, '$');
            $result[$name] = $description;
        }
        return $result;
    }

    private function propertyDocblockHasText(ReflectionProperty $property): bool
    {
        $docComment = $property->getDocComment();
        if ($docComment === false) {
            return false;
        }
        $phpDoc = $this->parseDocblock($docComment);
        $text = '';
        foreach ($phpDoc->children as $child) {
            if ($child instanceof PhpDocTagNode) {
                break;
            }
            if ($child instanceof PhpDocTextNode) {
                $text .= $child->text;
            }
        }
        return trim($text) !== '';
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

    /**
     * @param ReflectionClass<object> $reflection
     */
    private function applyClassMetadata(Schema $schema, ReflectionClass $reflection): Schema
    {
        $docComment = $reflection->getDocComment();
        if ($docComment !== false) {
            $schema = $this->applyDocblockText($schema, $docComment);
        }
        $format = $this->extractFormat($reflection);
        if ($format !== null) {
            $schema = $schema->withFormat($format);
        }
        $pattern = $this->extractPattern($reflection);
        if ($pattern !== null) {
            $schema = $schema->withPattern($pattern);
        }
        $examples = $this->extractExamplesFromAttributes($reflection);
        if ($examples !== null) {
            $schema = $schema->withExamples($examples);
        }
        if ($this->extractUniqueItems($reflection)) {
            $schema = $schema->withUniqueItems(true);
        }
        return $schema;
    }

    private function applyPropertyMetadata(Schema $schema, ReflectionProperty $property): Schema
    {
        $docComment = $property->getDocComment();
        if ($docComment !== false) {
            $schema = $this->applyDocblockText($schema, $docComment);
        }
        $format = $this->extractFormat($property);
        if ($format !== null) {
            $schema = $schema->withFormat($format);
        }
        $pattern = $this->extractPattern($property);
        if ($pattern !== null) {
            $schema = $schema->withPattern($pattern);
        }
        $examples = $this->extractExamplesFromAttributes($property);
        if ($examples !== null) {
            $schema = $schema->withExamples($examples);
        }
        if ($this->extractUniqueItems($property)) {
            $schema = $schema->withUniqueItems(true);
        }
        return $schema;
    }

    private function applyDocblockText(Schema $schema, string $docComment): Schema
    {
        $phpDoc = $this->parseDocblock($docComment);
        $text = '';
        foreach ($phpDoc->children as $child) {
            if ($child instanceof PhpDocTagNode) {
                break;
            }
            if ($child instanceof PhpDocTextNode) {
                $text .= $child->text;
            }
        }
        $text = trim($text);
        if ($text === '') {
            return $schema;
        }
        $parts = explode("\n\n", $text, 2);
        $schema = $schema->withTitle($parts[0]);
        if (isset($parts[1])) {
            $description = trim($parts[1]);
            if ($description !== '') {
                $schema = $schema->withDescription($description);
            }
        }
        return $schema;
    }

    /**
     * @param ReflectionClass<object>|ReflectionProperty $reflection
     * @return list<mixed>|null
     */
    private function extractExamplesFromAttributes(ReflectionClass|ReflectionProperty $reflection): array|null
    {
        $attributes = $reflection->getAttributes(Example::class);
        if ($attributes === []) {
            return null;
        }
        $examples = [];
        foreach ($attributes as $attribute) {
            /** @psalm-suppress MixedAssignment */
            $examples[] = $attribute->newInstance()->value;
        }
        return $examples;
    }

    /**
     * @param ReflectionClass<object>|ReflectionProperty $reflection
     */
    private function extractFormat(ReflectionClass|ReflectionProperty $reflection): string|null
    {
        $attributes = $reflection->getAttributes(Format::class);
        if ($attributes === []) {
            return null;
        }
        return $attributes[0]->newInstance()->format;
    }

    /**
     * @param ReflectionClass<object>|ReflectionProperty $reflection
     */
    private function extractPattern(ReflectionClass|ReflectionProperty $reflection): string|null
    {
        $attributes = $reflection->getAttributes(Pattern::class);
        if ($attributes === []) {
            return null;
        }
        return $attributes[0]->newInstance()->pattern;
    }

    /**
     * @param ReflectionClass<object>|ReflectionProperty $reflection
     */
    private function extractUniqueItems(ReflectionClass|ReflectionProperty $reflection): bool
    {
        return $reflection->getAttributes(UniqueItems::class) !== [];
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
