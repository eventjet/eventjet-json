<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit\Optimization;

use Eventjet\Json\DecodeError;
use Eventjet\Json\Internal\CompiledHydrators;
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
use Eventjet\Json\Test\Acceptance\Fixtures\BackedEnumFields;
use Eventjet\Json\Test\Acceptance\Fixtures\DefaultedConstructorFields;
use Eventjet\Json\Test\Acceptance\Fixtures\IntBackedStatus;
use Eventjet\Json\Test\Acceptance\Fixtures\LiteralBooleanFields;
use Eventjet\Json\Test\Acceptance\Fixtures\NullableScalarFields;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarConstructorGuard;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use Eventjet\Json\Test\Unit\Fixtures\CompiledHydrationTarget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\UnknownClassOrInterfaceException;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;
use stdClass;

#[CoversClass(CompiledHydrators::class)]
#[CoversClass(ConstructorPlan::class)]
#[CoversClass(ObjectHydrator::class)]
#[CoversClass(ScalarHydratorCompiler::class)]
#[UsesClass(\Eventjet\Json\Internal\BackedEnumValueConverter::class)]
#[UsesClass(ConstructorDecoder::class)]
#[UsesClass(\Eventjet\Json\Internal\FieldValueConverter::class)]
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
        static::assertEquals($original, CompiledHydrators::hydrate($class, $input));
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

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws UnknownClassOrInterfaceException
     */
    public function testScalarPlansCompileAfterTheirHydrationThreshold(): void
    {
        $class = CompiledHydrationTarget::class;
        $input = self::input(['value' => 7]);
        for ($hydration = 1; $hydration < 128; ++$hydration) {
            static::assertNull(CompiledHydrators::hydrate($class, $input));
        }
        $hydrated = CompiledHydrators::hydrate($class, $input);
        static::assertInstanceOf(CompiledHydrationTarget::class, $hydrated);
        static::assertSame(7, $hydrated->value);
        static::assertEquals(
            new CompiledHydrationTarget(8),
            CompiledHydrators::hydrate($class, self::input([
                'value' => 8,
            ])),
        );
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    public function testEveryCachedScalarPlanUsesCompiledHydration(): void
    {
        $scalar = new ScalarFields('value', 1, 1.5, true);
        $nullable = new NullableScalarFields(null, null, null, null, null);
        $enums = new BackedEnumFields(StringBackedStatus::Ready, StringBackedStatus::Pending, IntBackedStatus::Ready);
        static::assertEquals($scalar, ObjectHydrator::hydrate($scalar::class, self::input(\get_object_vars($scalar))));
        static::assertEquals($nullable, ObjectHydrator::hydrate(
            $nullable::class,
            self::input(\get_object_vars($nullable)),
        ));
        $enumInput = self::input(['stringStatus' => 'ready', 'nullableStatus' => 'pending', 'intStatus' => 1]);
        static::assertEquals($enums, ObjectHydrator::hydrate($enums::class, $enumInput));

        /** @var array<class-string, true> $scalarPlans */
        $scalarPlans = new ReflectionProperty(ObjectHydrator::class, 'scalarPlans')->getValue();
        static::assertArrayHasKey($scalar::class, $scalarPlans);
        static::assertArrayHasKey($nullable::class, $scalarPlans);
        static::assertArrayNotHasKey($enums::class, $scalarPlans);
    }
}
