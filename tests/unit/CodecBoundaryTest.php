<?php

declare(strict_types=1);

namespace Eventjet\Test\Unit\Json;

use Eventjet\Json\Json;
use Eventjet\Json\JsonError;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use WeakReference;

use function fclose;
use function fopen;

use const NAN;

final class CodecBoundaryTest extends TestCase
{
    public function testScalarSourceDoesNotDiscardReplacementMapKeys(): void
    {
        $object = new class {
            public mixed $value = null;
        };
        Json::decode('{"value":null}', $object);
        $object->value = ['named' => 42];
        self::assertSame('{"value":{"named":42}}', Json::encode($object));
    }

    public function testArraySourceDoesNotDiscardAddedKeys(): void
    {
        $object = new class {
            /** @var array<mixed> */
            public array $value = [];
        };
        Json::decode('{"value":[1]}', $object);
        $object->value['named'] = 42;
        self::assertSame('{"value":{"0":1,"named":42}}', Json::encode($object));
    }

    public function testNestedScalarSourceDoesNotDiscardReplacementMapKeys(): void
    {
        $object = Json::decode('{"value":[false]}', stdClass::class);
        $object->value = [['named' => 42]];
        self::assertSame('{"value":[{"named":42}]}', Json::encode($object));
    }

    public function testObjectSourceDoesNotForceAReplacementListToBeAnObject(): void
    {
        $object = Json::decode('{"value":{"old":1}}', stdClass::class);
        $object->value = [42];
        self::assertSame('{"value":[42]}', Json::encode($object));
    }

    public function testPropertyPopulationUsesItsOwnNativeType(): void
    {
        $object = new class ('1') {
            public int $value;

            public function __construct(string $value)
            {
                $this->value = (int)$value;
            }
        };
        $constructed = Json::decode('{"value":"2"}', $object::class);
        self::assertSame(2, $constructed->value);
        Json::decode('{"value":3}', $object);
        try {
            Json::decode('{"value":"4"}', $object);
            self::fail('A string must not be coerced into an integer property');
        } catch (JsonError $error) {
            self::assertStringContainsString('Expected int', $error->getMessage());
        }
        self::assertSame(3, $object->value);
    }

    public function testAmbiguousUnionDoesNotCallConstructors(): void
    {
        $first = new class ('') {
            public static int $calls = 0;

            public function __construct(public string $name)
            {
                self::$calls++;
            }
        };
        $second = new class ('') {
            public static int $calls = 0;

            public function __construct(public string $name)
            {
                self::$calls++;
            }
        };
        $first::$calls = 0;
        $second::$calls = 0;
        try {
            Json::decode('{"name":"value"}', $first::class . '|' . $second::class);
            self::fail('Both alternatives accept the input');
        } catch (JsonError $error) {
            self::assertStringContainsString('Ambiguous union', $error->getMessage());
        }
        self::assertSame(0, $first::$calls);
        self::assertSame(0, $second::$calls);
    }

    public function testUniqueUnionCallsOnlyTheSelectedConstructor(): void
    {
        $first = new class ('') {
            public static int $calls = 0;

            public function __construct(public string $name)
            {
                self::$calls++;
            }
        };
        $second = new class (0) {
            public static int $calls = 0;

            public function __construct(public int $count)
            {
                self::$calls++;
            }
        };
        $first::$calls = 0;
        $second::$calls = 0;
        $decoded = Json::decode('{"name":"value"}', $first::class . '|' . $second::class);
        self::assertInstanceOf($first::class, $decoded);
        self::assertSame('value', $decoded->name);
        self::assertSame(1, $first::$calls);
        self::assertSame(0, $second::$calls);
    }

    public function testRejectedListAlternativeDoesNotConstructEarlierItems(): void
    {
        $item = new class ('') {
            public static int $calls = 0;

            public function __construct(public string $name)
            {
                self::$calls++;
            }
        };
        $item::$calls = 0;
        /** @var mixed $decoded */
        $decoded = Json::decode('[{"name":"value"},false]', 'list<' . $item::class . '>|list<mixed>');
        self::assertEquals([(object)['name' => 'value'], false], $decoded);
        self::assertSame(0, $item::$calls);
    }

    public function testSelectedConstructorErrorsAreNotUnionMismatches(): void
    {
        $object = new class ('') {
            public function __construct(public string $name)
            {
                if ($name !== '') {
                    throw new RuntimeException('Constructor failed');
                }
            }
        };
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Constructor failed');
        Json::decode('{"name":"value"}', $object::class . '|bool');
    }

    public function testOmittedNanDoesNotPreventDecodingOrEncodingPresentFields(): void
    {
        $object = new class {
            public string $name = '';
            public float $unused = NAN;
        };
        Json::decode('{"name":"changed"}', $object);
        self::assertSame('changed', $object->name);
        self::assertSame('{"name":"changed"}', Json::encode($object));
        $object->unused = 2.0;
        self::assertSame('{"name":"changed","unused":2}', Json::encode($object));
    }

    public function testOmittedObjectSnapshotDetectsNestedMutations(): void
    {
        $nested = (object)['name' => 'before'];
        $object = new class ((object)['nested' => $nested]) {
            public function __construct(public object $value)
            {
            }
        };
        Json::decode('{}', $object);
        self::assertSame('{}', Json::encode($object));
        $nested->name = 'after';
        self::assertSame('{"value":{"nested":{"name":"after"}}}', Json::encode($object));
    }

    public function testOmittedCycleDoesNotPreventDecodeOrRetainTheObject(): void
    {
        $object = new stdClass();
        $object->self = $object;
        $reference = WeakReference::create($object);
        Json::decode('{}', $object);
        self::assertSame('{}', Json::encode($object));
        unset($object->self, $object);
        self::assertNull($reference->get());
    }

    public function testOmittedResourceDoesNotPreventDecoding(): void
    {
        $resource = fopen('php://memory', 'r+');
        self::assertIsResource($resource);
        try {
            $object = new stdClass();
            $object->stream = $resource;
            Json::decode('{}', $object);
            self::assertSame('{}', Json::encode($object));
        } finally {
            fclose($resource);
        }
    }

    public function testSingleArgumentArrayChecksItsItems(): void
    {
        $object = new class {
            /** @var array<int> */
            public array $values = [];
        };
        Json::decode('{"values":[1,2]}', $object);
        self::assertSame([1, 2], $object->values);
        $this->expectException(JsonError::class);
        $this->expectExceptionMessage('Expected int');
        Json::decode('{"values":["wrong"]}', $object);
    }

    public function testSingleArgumentArrayPreservesMapShape(): void
    {
        $object = new class {
            /** @var array<int> */
            public array $values = [];
        };
        Json::decode('{"values":{"named":2}}', $object);
        self::assertSame(['named' => 2], $object->values);
        self::assertSame('{"values":{"named":2}}', Json::encode($object));
    }

    public function testNestedSingleArgumentArrayUsesItsElementContractWhenEncoding(): void
    {
        $object = new class {
            /** @var array<array<string, int>> */
            public array $values = [[]];
        };
        self::assertSame('{"values":[{}]}', Json::encode($object));
    }

    public function testCollectionUnionUsesTheMatchingElementContract(): void
    {
        $object = new class {
            /** @var list<int>|list<array<string, int>> */
            public array $values = [[]];
        };
        self::assertSame('{"values":[{}]}', Json::encode($object));
        $object->values = [42];
        self::assertSame('{"values":[42]}', Json::encode($object));
    }

    public function testCollectionUnionUsesTheCurrentContainerShape(): void
    {
        $object = new class {
            /** @var list<array<string, int>>|array<string, list<int>> */
            public array $values = [[]];
        };
        self::assertSame('{"values":[{}]}', Json::encode($object));
        $object->values = ['named' => []];
        self::assertSame('{"values":{"named":[]}}', Json::encode($object));
    }

    public function testNullableConstructedMapKeepsItsDeclaredShape(): void
    {
        $object = new class {
            /** @var array<string, int>|null */
            public array|null $values = [];
        };
        self::assertSame('{"values":{}}', Json::encode($object));
    }

    public function testAmbiguousCollectionEncodingReportsItsContract(): void
    {
        $object = new class {
            /** @var list<array<string, int>>|list<list<int>> */
            public array $values = [[]];
        };
        $this->expectException(JsonError::class);
        $this->expectExceptionMessage('Ambiguous collection type');
        Json::encode($object);
    }

    public function testPromotedPropertyKeepsItsCollectionAnnotation(): void
    {
        $object = new class ([]) {
            /**
             * @param array<string, int> $values
             */
            public function __construct(public array $values)
            {
            }
        };
        self::assertSame('{"values":{}}', Json::encode($object));
    }

    public function testUnrelatedStaticPropertiesDoNotStopPopulation(): void
    {
        $object = new class {
            public static int $count = 0;
            public string $name = '';
            public string|null $unused = null;
        };
        Json::decode('{"name":"new"}', $object);
        self::assertSame('new', $object->name);
        self::assertSame('{"name":"new"}', Json::encode($object));
    }

    public function testNanSnapshotDoesNotHideAReplacementString(): void
    {
        $object = new stdClass();
        $object->value = NAN;
        Json::decode('{}', $object);
        $object->value = 'NaN';
        self::assertSame('{"value":"NaN"}', Json::encode($object));
    }

    public function testSnapshotDoesNotInvokeObjectHooks(): void
    {
        $object = new stdClass();
        $object->value = new class {
            public string $name = 'before';

            /** @return array<array-key, mixed> */
            public function __serialize(): array
            {
                throw new RuntimeException('Must not serialize defaults');
            }

            public function __clone()
            {
                throw new RuntimeException('Must not clone defaults');
            }
        };
        Json::decode('{}', $object);
        self::assertSame('{}', Json::encode($object));
        $object->value->name = 'after';
        self::assertSame('{"value":{"name":"after"}}', Json::encode($object));
    }

    public function testOmittedDeepDefaultsAreBoundedWithoutBlockingDecode(): void
    {
        $value = null;
        for ($depth = 0; $depth < 512; $depth++) {
            $value = [$value];
        }
        $object = new stdClass();
        $object->value = $value;
        Json::decode('{}', $object);
        self::assertSame('{}', Json::encode($object));
        $object->value = [$value];
        Json::decode('{}', $object);
        $object->value = 'changed';
        self::assertSame('{"value":"changed"}', Json::encode($object));
    }

    public function testOmittedRecursiveArrayDoesNotBlockDecoding(): void
    {
        $array = [];
        $array['self'] = &$array;
        $object = new stdClass();
        $object->value = $array;
        Json::decode('{}', $object);
        $object->value = 'changed';
        self::assertSame('{"value":"changed"}', Json::encode($object));
    }

    public function testRootNumericUnionPreservesTheSelectedPhpType(): void
    {
        self::assertSame(42, Json::decode('42', 'float|int'));
        self::assertSame(42.0, Json::decode('42', 'float'));
        self::assertSame(42.5, Json::decode('42.5', 'int|float'));
        self::assertSame(42, Json::decode('42', 'null|mixed|int'));
    }

    public function testEmptyCollectionArgumentIsRejected(): void
    {
        $this->expectException(JsonError::class);
        $this->expectExceptionMessage('Invalid collection type "array<>"');
        Json::decode('[]', 'array<>');
    }

    public function testInvalidCollectionArityIsRejected(): void
    {
        $this->expectException(JsonError::class);
        $this->expectExceptionMessage('Invalid collection type "array<int,string,bool>"');
        Json::decode('[]', 'array<int,string,bool>');
    }

    public function testInvalidListArityIsRejected(): void
    {
        $this->expectException(JsonError::class);
        $this->expectExceptionMessage('Invalid collection type "list<int,string>"');
        Json::decode('[]', 'list<int,string>');
    }
}
