<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json;

use Eventjet\Json\Internal\PhpType;
use Eventjet\Json\Json;
use Eventjet\Json\JsonError;
use Eventjet\Json\Schema\Type;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use stdClass;

use function str_repeat;

final class ReviewRegressionTest extends TestCase
{
    public function testLongStringsAndEscapesRoundTrip(): void
    {
        $value = str_repeat('a', 100_000) . '"\\' . "\n";
        $json = Json::encode((object)['value' => $value, 'following' => 42]);
        $decoded = Json::decode($json, stdClass::class);
        self::assertSame($value, $decoded->value);
        self::assertSame($json, Json::encode($decoded));
    }

    public function testWhitespaceAndEscapedMemberNamesRoundTrip(): void
    {
        $json = <<<'JSON'
              {
                "values" : [ -0 , true , "a\"b" , { "\u006b" : null } , [ ] ]
              }
            JSON;
        $decoded = Json::decode($json, stdClass::class);
        self::assertSame('{"values":[-0,true,"a\\"b",{"k":null},[]]}', Json::encode($decoded));
    }

    public function testDocumentedTypesRefineNativeUnionsAndScalars(): void
    {
        $object = new class {
            /** @var int */
            public int|float $number = 0;
            /** @var true */
            public bool $enabled = true;
            /** @var list<int> | null Optional values. */
            public array|null $values = null;
            /** @var stdClass */
            public object $details;

            public function __construct()
            {
                $this->details = new stdClass();
            }
        };
        Json::decode('{"number":42,"enabled":true,"values":[1],"details":{}}', $object);
        self::assertSame(42, $object->number);
        self::assertSame([1], $object->values);
        self::assertSame('{"number":42,"enabled":true,"values":[1],"details":{}}', Json::encode($object));
        Json::decode('{"values":null}', $object);
        self::assertSame('{"values":null}', Json::encode($object));
    }

    public function testSingleLineConstructorDocKeepsItsCollectionType(): void
    {
        $object = new class ([]) {
            /** @param list<int> $values Values to keep. */
            public function __construct(public array $values)
            {
            }
        };
        $decoded = Json::decode('{"values":[42]}', $object::class);
        self::assertSame([42], $decoded->values);
    }

    public function testNestedCollectionUnionRetainsDecodedShape(): void
    {
        $object = new class {
            /** @var list<list<int>>|list<array<string, string>> */
            public array $values = [];
        };
        Json::decode('{"values":[{}]}', $object);
        self::assertSame('{"values":[{}]}', Json::encode($object));
        $object->values = [['new']];
        self::assertSame('{"values":[{"0":"new"}]}', Json::encode($object));
        Json::decode('{"values":[[]]}', $object);
        self::assertSame('{"values":[[]]}', Json::encode($object));
        $object->values = [[42]];
        self::assertSame('{"values":[[42]]}', Json::encode($object));
    }

    public function testPropertyDocDescriptionIsNotPartOfTheType(): void
    {
        $object = new class {
            /** @var int Current count. */
            public int $value = 0;
            /** @var array<string, list<int>> Lists by name. */
            public array $groups = [];
        };
        Json::decode('{"value":42,"groups":{"first":[1]}}', $object);
        self::assertSame(42, $object->value);
        self::assertSame(['first' => [1]], $object->groups);
    }

    public function testConflictingPropertyDocIsRejectedBeforeAssignment(): void
    {
        $object = new class {
            public int $value = 0;
        };
        $property = new class ($object, 'value') extends ReflectionProperty {
            /** @psalm-external-mutation-free */
            public function getDocComment(): string
            {
                return '/** @var string */';
            }
        };
        $this->expectException(JsonError::class);
        $this->expectExceptionMessage('incompatible with native type "int"');
        PhpType::property($property);
    }

    public function testReadonlyFailureDoesNotChangeEarlierFieldsOrMetadata(): void
    {
        $object = new class {
            public string $first = 'before';
            public readonly string $last;

            public function __construct()
            {
                $this->last = 'before';
            }
        };
        Json::decode('{"first":"before","unknown":1}', $object);
        try {
            Json::decode('{"first":"after","last":"after"}', $object);
            self::fail('Initialized readonly properties cannot be populated');
        } catch (JsonError $error) {
            self::assertStringContainsString('readonly', $error->getMessage());
        }
        self::assertSame('before', $object->first);
        self::assertSame('{"first":"before","unknown":1}', Json::encode($object));
    }

    public function testNestedConstructorFailureDoesNotChangeEarlierFields(): void
    {
        $object = new class (new FailingReviewValue('before')) {
            public string $first = 'before';

            public function __construct(public FailingReviewValue $last)
            {
            }
        };
        try {
            Json::decode('{"first":"after","last":{"value":"fail"}}', $object);
            self::fail('The nested constructor must fail');
        } catch (RuntimeException $error) {
            self::assertSame('Constructor failed', $error->getMessage());
        }
        self::assertSame('before', $object->first);
        self::assertSame('before', $object->last->value);
    }

    public function testEnumUnionAcceptsOtherBackingTypesInEitherOrder(): void
    {
        self::assertSame(42, Json::decode('42', Type::class . '|int'));
        self::assertSame(42, Json::decode('42', 'int|' . Type::class));
        self::assertSame('other', Json::decode('"other"', ReviewIntegerEnum::class . '|string'));
        self::assertSame('other', Json::decode('"other"', 'string|' . ReviewIntegerEnum::class));
        self::assertSame(Type::String, Json::decode('"string"', Type::class . '|int'));
        self::assertSame(ReviewIntegerEnum::One, Json::decode('1', ReviewIntegerEnum::class . '|string'));
    }
}

final class FailingReviewValue
{
    public function __construct(public string $value)
    {
        if ($value === 'fail') {
            throw new RuntimeException('Constructor failed');
        }
    }
}

enum ReviewIntegerEnum: int
{
    case One = 1;
}
