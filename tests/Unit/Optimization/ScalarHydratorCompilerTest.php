<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit\Optimization;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\ConstructorDecoder;
use Eventjet\Json\Internal\ConstructorPlan;
use Eventjet\Json\Internal\ConstructorValueValidator;
use Eventjet\Json\Internal\FieldPath;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use Eventjet\Json\Internal\ObjectHydrator;
use Eventjet\Json\Internal\PublicProperties;
use Eventjet\Json\Internal\RootTypeValidator;
use Eventjet\Json\Internal\ScalarHydratorCompiler;
use Eventjet\Json\Internal\ValueTypeMatcher;
use Eventjet\Json\Test\Acceptance\Fixtures\DefaultedConstructorFields;
use Eventjet\Json\Test\Acceptance\Fixtures\LiteralBooleanFields;
use Eventjet\Json\Test\Acceptance\Fixtures\NullableScalarFields;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarConstructorGuard;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\UnknownClassOrInterfaceException;
use ReflectionException;
use RuntimeException;
use stdClass;

#[CoversClass(ConstructorPlan::class)]
#[CoversClass(ObjectHydrator::class)]
#[CoversClass(ScalarHydratorCompiler::class)]
#[UsesClass(ConstructorDecoder::class)]
#[UsesClass(ConstructorValueValidator::class)]
#[UsesClass(FieldPath::class)]
#[UsesClass(DecodeError::class)]
#[UsesClass(\Eventjet\Json\Internal\PhpDocLiteralParameter::class)]
#[UsesClass(FieldTypeNameResolver::class)]
#[UsesClass(RootTypeValidator::class)]
#[UsesClass(ValueTypeMatcher::class)]
#[UsesClass(PublicProperties::class)]
final class ScalarHydratorCompilerTest extends TestCase
{
    /** @param array<array-key, mixed> $values */
    private static function input(array $values): stdClass
    {
        /**
         * @psalm-var stdClass
         * @mago-expect analysis:redundant-docblock-type Psalm infers object for an array cast, rather than stdClass.
         */
        return (object) $values;
    }

    /** @throws ReflectionException */
    public function testCompiledHydratorsValidateScalarsAndCreateFreshObjects(): void
    {
        foreach ([
            new ScalarFields("'); throw new RuntimeException('input is data'); //", -42, 3.5, false),
            new NullableScalarFields('text', 0, 0.0, true, null),
            new NullableScalarFields(null, null, null, null, null),
            new LiteralBooleanFields(true, false),
        ] as $original) {
            $hydrate = ScalarHydratorCompiler::compile($original::class);
            static::assertNotNull($hydrate);
            $input = self::input(\get_object_vars($original));
            $first = $hydrate($input);
            static::assertEquals($original, $first);
            $second = $hydrate($input);
            static::assertEquals($original, $second);
            static::assertNotSame($first, $second);
            foreach (\array_keys(\get_object_vars($original)) as $field) {
                $missing = \get_object_vars($original);
                unset($missing[$field]);
                static::assertNull($hydrate(self::input($missing)));
                $invalid = \get_object_vars($original);
                $invalid[$field] = [];
                static::assertNull($hydrate(self::input($invalid)));
                $invalid[$field] = [true];
                static::assertNull($hydrate(self::input($invalid)));
            }
        }
        $hydrate = ScalarHydratorCompiler::compile(ScalarFields::class);
        static::assertNotNull($hydrate);
        static::assertEquals(
            new ScalarFields('value', 1, 3.0, true),
            $hydrate(self::input([
                'string' => 'value',
                'integer' => 1,
                'float' => 3,
                'boolean' => true,
            ])),
        );
        static::assertNull($hydrate(self::input([
            'string' => null,
            'integer' => 1,
            'float' => 3,
            'boolean' => true,
        ])));
        $literals = ScalarHydratorCompiler::compile(LiteralBooleanFields::class);
        static::assertNotNull($literals);
        static::assertNull($literals(self::input(['true' => false, 'false' => false])));
        static::assertNull($literals(self::input(['true' => true, 'false' => true])));

        $anonymous = new class {
            public function __construct(
                public int $value = 0,
            ) {}
        };
        static::assertNull(ScalarHydratorCompiler::compile($anonymous::class));
        static::assertNull(ScalarHydratorCompiler::compile(
            \Eventjet\Json\Test\Acceptance\Fixtures\DistinctEnumScalarUnionField::class,
        ));
        static::assertNull(ScalarHydratorCompiler::compile(
            \Eventjet\Json\Test\Acceptance\Fixtures\BackedEnumFields::class,
        ));
        static::assertNull(ScalarHydratorCompiler::compile(
            \Eventjet\Json\Test\Acceptance\Fixtures\ScalarListFields::class,
        ));
        $empty = ScalarHydratorCompiler::compile(stdClass::class);
        static::assertNotNull($empty);
        static::assertEquals(new stdClass(), $empty(new stdClass()));
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws UnknownClassOrInterfaceException
     * @throws RuntimeException
     */
    public function testCompiledHydrationFallsBackForDefaultsAndErrors(): void
    {
        $class = DefaultedConstructorFields::class;
        $original = new DefaultedConstructorFields('required', 'explicit', null);
        $input = self::input(\get_object_vars($original));
        for ($index = 0; $index < 129; ++$index) {
            static::assertEquals($original, ObjectHydrator::hydrate($class, $input));
        }
        static::assertEquals($original, ObjectHydrator::hydrate($class, $input));
        $plan = ConstructorDecoder::cachedPlan($class);
        static::assertNotNull($plan);
        static::assertNotNull($plan->hydrate);
        static::assertEquals(
            new DefaultedConstructorFields('required'),
            ObjectHydrator::hydrate($class, self::input([
                'required' => 'required',
                'ignored' => 'not a constructor argument',
            ])),
        );
        static::assertEquals(
            DecodeError::fieldTypeMismatch($class, 'nested.required', 'string', false),
            ObjectHydrator::hydrate(
                $class,
                self::input(['required' => false, 'label' => 'explicit', 'nullableCount' => null]),
                'nested',
            ),
        );
        static::assertInstanceOf(DecodeError::class, ObjectHydrator::hydrate($class, new stdClass()));

        $class = ScalarConstructorGuard::class;
        $expected = new ScalarConstructorGuard(0);
        for ($index = 0; $index < 129; ++$index) {
            static::assertEquals($expected, ObjectHydrator::hydrate($class, self::input(['value' => 0])));
        }
        $before = ScalarConstructorGuard::$calls;
        static::assertEquals($expected, ObjectHydrator::hydrate($class, self::input([
            'value' => 0,
        ])));
        $expected->value = 1;
        static::assertEquals($expected, ObjectHydrator::hydrate($class, self::input([
            'value' => 1,
        ])));
        $error = ObjectHydrator::hydrate($class, self::input(['value' => -1]));
        static::assertInstanceOf(DecodeError::class, $error);
        static::assertSame(ScalarConstructorGuard::exception(), $error->getPrevious());
        static::assertSame($before + 3, ScalarConstructorGuard::$calls);
    }
}
