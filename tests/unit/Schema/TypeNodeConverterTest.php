<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Schema;

use Eventjet\Json\Schema\ClassSchemaGenerator;
use Eventjet\Json\Schema\Exception\UnsupportedTypeException;
use Eventjet\Json\Schema\Schema;
use Eventjet\Json\Schema\SchemaRegistry;
use Eventjet\Json\Schema\TypeNodeConverter;
use Eventjet\Test\Unit\Json\Fixtures\StringStatus;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprArrayNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprFalseNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprFloatNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprIntegerNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprNullNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprTrueNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstFetchNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeItemNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeUnsealedTypeNode;
use PHPStan\PhpDocParser\Ast\Type\CallableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ConstTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ObjectShapeItemNode;
use PHPStan\PhpDocParser\Ast\Type\ObjectShapeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(TypeNodeConverter::class)]
#[CoversClass(Schema::class)]
#[CoversClass(SchemaRegistry::class)]
#[CoversClass(ClassSchemaGenerator::class)]
final class TypeNodeConverterTest extends TestCase
{
    public function testConstTrue(): void
    {
        $converter = $this->createConverter();
        $schema = $converter->convert(new ConstTypeNode(new ConstExprTrueNode()));
        self::assertEquals(
            (object)['const' => true],
            json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testConstFalse(): void
    {
        $converter = $this->createConverter();
        $schema = $converter->convert(new ConstTypeNode(new ConstExprFalseNode()));
        self::assertEquals(
            (object)['const' => false],
            json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testConstNull(): void
    {
        $converter = $this->createConverter();
        $schema = $converter->convert(new ConstTypeNode(new ConstExprNullNode()));
        self::assertEquals(
            (object)['const' => null],
            json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testConstFloat(): void
    {
        $converter = $this->createConverter();
        $schema = $converter->convert(new ConstTypeNode(new ConstExprFloatNode('3.14')));
        self::assertEquals(
            (object)['const' => 3.14],
            json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testUnsupportedConstExpressionThrows(): void
    {
        $converter = $this->createConverter();
        $this->expectException(UnsupportedTypeException::class);
        $converter->convert(new ConstTypeNode(new ConstFetchNode('Foo', 'BAR')));
    }

    public function testUnsupportedTypeNodeThrows(): void
    {
        $converter = $this->createConverter();
        $this->expectException(UnsupportedTypeException::class);
        $converter->convert(new CallableTypeNode(
            new IdentifierTypeNode('callable'),
            [],
            new IdentifierTypeNode('void'),
            [],
        ));
    }

    public function testUnionOfAllConstTypes(): void
    {
        $converter = $this->createConverter();
        $schema = $converter->convert(new UnionTypeNode([
            new ConstTypeNode(new ConstExprIntegerNode('1')),
            new ConstTypeNode(new ConstExprFloatNode('3.14')),
            new ConstTypeNode(new ConstExprStringNode('hello', ConstExprStringNode::SINGLE_QUOTED)),
            new ConstTypeNode(new ConstExprTrueNode()),
            new ConstTypeNode(new ConstExprFalseNode()),
            new ConstTypeNode(new ConstExprNullNode()),
        ]));
        self::assertEquals(
            (object)['enum' => [1, 3.14, 'hello', true, false, null]],
            json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testUnionWithUnsupportedConstFallsToAnyOfAndThrows(): void
    {
        $converter = $this->createConverter();
        $this->expectException(UnsupportedTypeException::class);
        $converter->convert(new UnionTypeNode([
            new ConstTypeNode(new ConstFetchNode('Foo', 'BAR')),
            new IdentifierTypeNode('string'),
        ]));
    }

    public function testIntRangeWithIdentifierBounds(): void
    {
        $converter = $this->createConverter();
        $schema = $converter->convert(new GenericTypeNode(
            new IdentifierTypeNode('int'),
            [
                new IdentifierTypeNode('5'),
                new IdentifierTypeNode('10'),
            ],
            [],
        ));
        self::assertEquals(
            (object)['type' => 'integer', 'minimum' => 5, 'maximum' => 10],
            json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testIntRangeWithNonIntegerBoundReturnsNull(): void
    {
        $converter = $this->createConverter();
        $schema = $converter->convert(new GenericTypeNode(
            new IdentifierTypeNode('int'),
            [
                new ConstTypeNode(new ConstExprStringNode('oops', ConstExprStringNode::SINGLE_QUOTED)),
                new IdentifierTypeNode('max'),
            ],
            [],
        ));
        self::assertEquals(
            (object)['type' => 'integer'],
            json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testUnsupportedGenericTypeThrows(): void
    {
        $converter = $this->createConverter();
        $this->expectException(UnsupportedTypeException::class);
        $converter->convert(new GenericTypeNode(
            new IdentifierTypeNode('SomeUnknownGeneric'),
            [new IdentifierTypeNode('string')],
            [],
        ));
    }

    public function testConstExprArrayInUnionBreaksConstExtraction(): void
    {
        $converter = $this->createConverter();
        $this->expectException(UnsupportedTypeException::class);
        $converter->convert(new UnionTypeNode([
            new ConstTypeNode(new ConstExprArrayNode([])),
            new IdentifierTypeNode('string'),
        ]));
    }

    public function testTupleShapePositionalKeys(): void
    {
        $converter = $this->createConverter();
        $schema = $converter->convert(ArrayShapeNode::createSealed([
            new ArrayShapeItemNode(null, false, new IdentifierTypeNode('string')),
            new ArrayShapeItemNode(null, false, new IdentifierTypeNode('int')),
        ]));
        self::assertEquals(
            (object)[
                'type' => 'array',
                'prefixItems' => [
                    (object)['type' => 'string'],
                    (object)['type' => 'integer'],
                ],
                'items' => false,
                'minItems' => 2,
            ],
            json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testTupleShapeWithOptionalItem(): void
    {
        $converter = $this->createConverter();
        $schema = $converter->convert(ArrayShapeNode::createSealed([
            new ArrayShapeItemNode(
                new ConstExprIntegerNode('0'),
                false,
                new IdentifierTypeNode('string'),
            ),
            new ArrayShapeItemNode(
                new ConstExprIntegerNode('1'),
                true,
                new IdentifierTypeNode('int'),
            ),
        ]));
        self::assertEquals(
            (object)[
                'type' => 'array',
                'prefixItems' => [
                    (object)['type' => 'string'],
                    (object)['type' => 'integer'],
                ],
                'items' => false,
                'minItems' => 1,
            ],
            json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testListShapeIsTuple(): void
    {
        $converter = $this->createConverter();
        $schema = $converter->convert(ArrayShapeNode::createSealed(
            [
                new ArrayShapeItemNode(null, false, new IdentifierTypeNode('string')),
                new ArrayShapeItemNode(null, false, new IdentifierTypeNode('int')),
            ],
            ArrayShapeNode::KIND_LIST,
        ));
        self::assertEquals(
            (object)[
                'type' => 'array',
                'prefixItems' => [
                    (object)['type' => 'string'],
                    (object)['type' => 'integer'],
                ],
                'items' => false,
                'minItems' => 2,
            ],
            json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testNonEmptyListShapeMinItemsAtLeastOne(): void
    {
        $converter = $this->createConverter();
        $schema = $converter->convert(ArrayShapeNode::createSealed(
            [
                new ArrayShapeItemNode(null, true, new IdentifierTypeNode('string')),
            ],
            ArrayShapeNode::KIND_NON_EMPTY_LIST,
        ));
        self::assertEquals(
            (object)[
                'type' => 'array',
                'prefixItems' => [
                    (object)['type' => 'string'],
                ],
                'items' => false,
                'minItems' => 1,
            ],
            json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testUnsealedTupleWithType(): void
    {
        $converter = $this->createConverter();
        $schema = $converter->convert(ArrayShapeNode::createUnsealed(
            [
                new ArrayShapeItemNode(null, false, new IdentifierTypeNode('string')),
            ],
            new ArrayShapeUnsealedTypeNode(new IdentifierTypeNode('int'), null),
        ));
        self::assertEquals(
            (object)[
                'type' => 'array',
                'prefixItems' => [
                    (object)['type' => 'string'],
                ],
                'items' => (object)['type' => 'integer'],
                'minItems' => 1,
            ],
            json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testUnsealedTupleWithoutType(): void
    {
        $converter = $this->createConverter();
        $schema = $converter->convert(ArrayShapeNode::createUnsealed(
            [
                new ArrayShapeItemNode(null, false, new IdentifierTypeNode('string')),
            ],
            null,
        ));
        self::assertEquals(
            (object)[
                'type' => 'array',
                'prefixItems' => [
                    (object)['type' => 'string'],
                ],
                'minItems' => 1,
            ],
            json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testUnsealedObjectShapeWithType(): void
    {
        $converter = $this->createConverter();
        $schema = $converter->convert(ArrayShapeNode::createUnsealed(
            [
                new ArrayShapeItemNode(
                    new IdentifierTypeNode('name'),
                    false,
                    new IdentifierTypeNode('string'),
                ),
            ],
            new ArrayShapeUnsealedTypeNode(new IdentifierTypeNode('string'), null),
        ));
        self::assertEquals(
            (object)[
                'type' => 'object',
                'properties' => (object)['name' => (object)['type' => 'string']],
                'required' => ['name'],
                'additionalProperties' => (object)['type' => 'string'],
            ],
            json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testNonEmptyArrayObjectShapeMinProperties(): void
    {
        $converter = $this->createConverter();
        $schema = $converter->convert(ArrayShapeNode::createSealed(
            [
                new ArrayShapeItemNode(
                    new IdentifierTypeNode('name'),
                    true,
                    new IdentifierTypeNode('string'),
                ),
            ],
            ArrayShapeNode::KIND_NON_EMPTY_ARRAY,
        ));
        self::assertEquals(
            (object)[
                'type' => 'object',
                'properties' => (object)['name' => (object)['type' => 'string']],
                'additionalProperties' => false,
                'minProperties' => 1,
            ],
            json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testObjectShapeNode(): void
    {
        $converter = $this->createConverter();
        $schema = $converter->convert(new ObjectShapeNode([
            new ObjectShapeItemNode(
                new IdentifierTypeNode('name'),
                false,
                new IdentifierTypeNode('string'),
            ),
            new ObjectShapeItemNode(
                new IdentifierTypeNode('age'),
                true,
                new IdentifierTypeNode('int'),
            ),
        ]));
        self::assertEquals(
            (object)[
                'type' => 'object',
                'properties' => (object)[
                    'name' => (object)['type' => 'string'],
                    'age' => (object)['type' => 'integer'],
                ],
                'required' => ['name'],
                'additionalProperties' => false,
            ],
            json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testIntMask(): void
    {
        $converter = $this->createConverter();
        $schema = $converter->convert(new GenericTypeNode(
            new IdentifierTypeNode('int-mask'),
            [
                new ConstTypeNode(new ConstExprIntegerNode('1')),
                new ConstTypeNode(new ConstExprIntegerNode('2')),
                new ConstTypeNode(new ConstExprIntegerNode('4')),
            ],
            [],
        ));
        self::assertEquals(
            (object)['enum' => [0, 1, 2, 3, 4, 5, 6, 7]],
            json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testValueOfBackedEnum(): void
    {
        $converter = $this->createConverter();
        $schema = $converter->convert(new GenericTypeNode(
            new IdentifierTypeNode('value-of'),
            [new IdentifierTypeNode(StringStatus::class)],
            [],
        ));
        $decoded = json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        self::assertIsObject($decoded);
        self::assertObjectHasProperty('$ref', $decoded);
    }

    public function testValueOfNonEnumThrows(): void
    {
        $converter = $this->createConverter();
        $this->expectException(UnsupportedTypeException::class);
        $converter->convert(new GenericTypeNode(
            new IdentifierTypeNode('value-of'),
            [new IdentifierTypeNode('stdClass')],
            [],
        ));
    }

    public function testValueOfNonIdentifierThrows(): void
    {
        $converter = $this->createConverter();
        $this->expectException(UnsupportedTypeException::class);
        $converter->convert(new GenericTypeNode(
            new IdentifierTypeNode('value-of'),
            [new UnionTypeNode([new IdentifierTypeNode('string'), new IdentifierTypeNode('int')])],
            [],
        ));
    }

    public function testIterableOneParam(): void
    {
        $converter = $this->createConverter();
        $schema = $converter->convert(new GenericTypeNode(
            new IdentifierTypeNode('iterable'),
            [new IdentifierTypeNode('string')],
            [],
        ));
        self::assertEquals(
            (object)['type' => 'array', 'items' => (object)['type' => 'string']],
            json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testIterableTwoParams(): void
    {
        $converter = $this->createConverter();
        $schema = $converter->convert(new GenericTypeNode(
            new IdentifierTypeNode('iterable'),
            [new IdentifierTypeNode('string'), new IdentifierTypeNode('int')],
            [],
        ));
        self::assertEquals(
            (object)['type' => 'object', 'additionalProperties' => (object)['type' => 'integer']],
            json_decode(json_encode($schema, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR),
        );
    }

    private function createConverter(): TypeNodeConverter
    {
        $registry = new SchemaRegistry();
        $classGenerator = new ClassSchemaGenerator($registry);
        $converter = new TypeNodeConverter($registry, $classGenerator);
        $classGenerator->setTypeNodeConverter($converter);
        return $converter;
    }
}
