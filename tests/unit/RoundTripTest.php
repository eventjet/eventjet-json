<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json;

use Eventjet\Json\Field;
use Eventjet\Json\Json;
use Eventjet\Json\JsonError;
use Eventjet\Json\Schema\Draft202012;
use PHPUnit\Framework\TestCase;
use stdClass;

final class RoundTripTest extends TestCase
{
    public function testUnknownFieldsAndOmittedDefaultsSurvive(): void
    {
        $object = new class {
            public string|null $name = null;
        };
        Json::decode('{"extra":{"0":{},"list":[],"large":123456789012345678901}}', $object);
        self::assertSame('{"extra":{"0":{},"list":[],"large":123456789012345678901}}', Json::encode($object));
        $object->name = 'New';
        self::assertSame('{"name":"New","extra":{"0":{},"list":[],"large":123456789012345678901}}', Json::encode($object));
    }

    public function testExplicitNullIsNotOmitted(): void
    {
        $object = new class {
            public string|null $name = null;
        };
        Json::decode('{"name":null}', $object);
        self::assertSame('{"name":null}', Json::encode($object));
    }

    public function testUnsettingKnownFieldDoesNotRestoreItAsUnknown(): void
    {
        $object = new class {
            public string|null $title = null;
        };
        Json::decode('{"title":"Old","x-example":null}', $object);
        unset($object->title);
        self::assertSame('{"x-example":null}', Json::encode($object));
    }

    public function testChangedNumbersAreEncodedFromTheirNewValue(): void
    {
        $object = new class {
            public int|float|null $minimum = null;
            public int|float|null $maximum = null;
        };
        Json::decode('{"minimum":1e2,"maximum":1.0}', $object);
        $object->minimum = 101;
        $object->maximum = 2.5;
        self::assertJsonStringEqualsJsonString('{"minimum":101,"maximum":2.5}', Json::encode($object));
    }

    public function testConstructorFieldNamesApplyInBothDirections(): void
    {
        $object = new class ('') {
            public function __construct(#[Field('$value')] public string $value)
            {
            }
        };
        $decoded = Json::decode('{"$value":"hello"}', $object::class);
        self::assertSame('hello', $decoded->value);
        self::assertSame('{"$value":"hello"}', Json::encode($decoded));
    }

    public function testMapsStayObjectsEvenWithSequentialNumericKeys(): void
    {
        $object = new class {
            /** @var array<array-key, string> */
            public array $values = [];
        };
        Json::decode('{"values":{"0":"a","1":"b"}}', $object);
        self::assertSame(['a', 'b'], $object->values);
        self::assertSame('{"values":{"0":"a","1":"b"}}', Json::encode($object));
    }

    public function testNestedConstructedMapsUseTheirDeclaredShape(): void
    {
        $object = new class {
            /** @var array<string, array<string, string>> */
            public array $values = ['empty' => []];
        };
        self::assertSame('{"values":{"empty":{}}}', Json::encode($object));
    }

    public function testMapRejectsJsonArray(): void
    {
        $object = new class {
            /** @var array<string, string> */
            public array $values = [];
        };
        $this->expectException(JsonError::class);
        Json::decode('{"values":[]}', $object);
    }

    public function testMixedValuesPreserveStringsAndContainers(): void
    {
        $object = new class {
            public mixed $value = null;
        };
        $json = <<<'JSON'
            {"value":{"0":["quote: \", slash: \\","\u00e4",{},[],null,true,false]}}
            JSON;
        Json::decode($json, $object);
        self::assertInstanceOf(stdClass::class, $object->value);
        self::assertJsonStringEqualsJsonString($json, Json::encode($object));
    }

    public function testNewNestedSchemaInOrdinaryDto(): void
    {
        $object = new class (new Draft202012()) {
            public function __construct(public Draft202012 $schema)
            {
            }
        };
        $decoded = Json::decode('{"schema":{"items":false,"properties":{},"default":null}}', $object::class);
        self::assertFalse($decoded->schema->items);
        self::assertSame('{"schema":{"default":null,"items":false,"properties":{}}}', Json::encode($decoded));
    }

    public function testReadonlyInstanceCannotBeRepopulated(): void
    {
        $object = new readonly class ('before') {
            public function __construct(public string $value)
            {
            }
        };
        $this->expectException(JsonError::class);
        Json::decode('{"value":"after"}', $object);
    }

    public function testCyclesFailWithJsonError(): void
    {
        $object = new stdClass();
        $object->cycle = $object;
        $this->expectException(JsonError::class);
        Json::encode($object);
    }

    public function testOmittedMapDefaultRemainsAbsent(): void
    {
        $object = new class {
            /** @var array<string, string> */
            public array $values = [];
        };
        Json::decode('{}', $object);
        self::assertSame('{}', Json::encode($object));
        $object->values['new'] = 'value';
        self::assertSame('{"values":{"new":"value"}}', Json::encode($object));
    }

    public function testStdClassCanBeDecodedAndEdited(): void
    {
        $object = Json::decode('{"name":"before","0":{},"big":1e1000}', stdClass::class);
        self::assertSame('before', $object->name);
        $object->name = 'after';
        unset($object->big);
        self::assertSame('{"name":"after","0":{}}', Json::encode($object));
    }
}
