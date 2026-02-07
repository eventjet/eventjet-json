<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json\Schema;

use Eventjet\Json\Schema\ClassSchemaGenerator;
use Eventjet\Json\Schema\Exception\UnsupportedTypeException;
use Eventjet\Json\Schema\Schema;
use Eventjet\Json\Schema\SchemaRegistry;
use Eventjet\Json\Schema\TypeNodeConverter;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprArrayNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprFalseNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprFloatNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprIntegerNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprNullNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprTrueNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstFetchNode;
use PHPStan\PhpDocParser\Ast\Type\CallableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ConstTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
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

    private function createConverter(): TypeNodeConverter
    {
        $registry = new SchemaRegistry();
        $classGenerator = new ClassSchemaGenerator($registry);
        $converter = new TypeNodeConverter($registry, $classGenerator);
        $classGenerator->setTypeNodeConverter($converter);
        return $converter;
    }
}
