<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit;

use Eventjet\Json\Internal\PhpDocConstantValues;
use Eventjet\Json\Internal\PhpDocLiteralNumber;
use Eventjet\Json\Internal\PhpDocLiteralString;
use Eventjet\Json\Internal\PhpDocStringEscape;
use Eventjet\Json\Internal\ValueTypeMatcher;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionNamedType;
use ReflectionProperty;

use const INF;
use const NAN;
use const PHP_INT_MAX;
use const PHP_INT_MIN;

#[CoversClass(PhpDocLiteralNumber::class)]
#[CoversClass(PhpDocLiteralString::class)]
#[CoversClass(PhpDocStringEscape::class)]
#[CoversClass(PhpDocConstantValues::class)]
#[CoversClass(ValueTypeMatcher::class)]
final class LiteralTypeTest extends TestCase
{
    public function testIntegerBoundariesAndNotation(): void
    {
        foreach ([
            ['0', 0],
            ['-0', 0],
            ['+42', 42],
            ['-42', -42],
            ['4_2', 42],
            ['0X2A', 42],
            ['0B101010', 42],
            ['0O52', 42],
            ['1E2', 100.0],
            [(string) PHP_INT_MIN, PHP_INT_MIN],
            [(string) PHP_INT_MAX, PHP_INT_MAX],
            ['9223372036854775808', null],
            ['-9223372036854775809', null],
            ['92233720368547758070', null],
            ['-92233720368547758080', null],
            ['0xffffffffffffffffffff', null],
            ['0b11111111111111111111111111111111111111111111111111111111111111111', null],
            ['foo', null],
            ['0xg', null],
            ['1e999', null],
        ] as [$source, $expected]) {
            static::assertSame($expected, PhpDocLiteralNumber::parse($source), $source);
        }
    }

    public function testUnicodeScalarBoundaries(): void
    {
        foreach ([
            ['0',                        "\u{0}"],
            ['d7ff',                     "\u{d7ff}"],
            ['e000',                     "\u{e000}"],
            ['ffff',                     "\u{ffff}"],
            ['10000',                    "\u{10000}"],
            ['103ff',                    "\u{103ff}"],
            ['10400',                    "\u{10400}"],
            ['10ffff',                   "\u{10ffff}"],
            ['d800',                     null],
            ['dfff',                     null],
            ['110000',                   null],
            ['ffffffffffffffffffffffff', null],
        ] as [$code, $expected]) {
            static::assertSame($expected, PhpDocLiteralString::parse('"\\u{' . $code . '}"'), $code);
        }
        static::assertNull(PhpDocLiteralString::parse('not quoted'));
        static::assertNull(PhpDocLiteralString::parse("'mismatched\""));
    }

    public function testConstantValuesKeepScalarTypesAndEscapedStrings(): void
    {
        static::assertSame("'a\\\\b\\'c'", PhpDocConstantValues::name("a\\b'c"));
        static::assertSame('null', PhpDocConstantValues::name(null));
        static::assertSame('true', PhpDocConstantValues::name(true));
        static::assertSame('false', PhpDocConstantValues::name(false));
        static::assertSame(
            StringBackedStatus::class . '::Ready',
            PhpDocConstantValues::name(StringBackedStatus::Ready),
        );
        static::assertNull(PhpDocConstantValues::name(INF));
        static::assertNull(PhpDocConstantValues::name(NAN));
        static::assertNull(PhpDocConstantValues::name([]));
    }

    /**
     * @throws \ReflectionException
     * @throws \PHPUnit\Framework\Exception
     * @throws \PHPUnit\Framework\UnknownClassOrInterfaceException
     */
    public function testNativeBooleanLiteralsRejectTheirOppositeValues(): void
    {
        $object = new class {
            public true $yes = true;
            public false $no = false;
        };
        foreach (['yes' => true, 'no' => false] as $field => $expected) {
            $type = new ReflectionProperty($object, $field)->getType();
            static::assertInstanceOf(ReflectionNamedType::class, $type);
            static::assertTrue(ValueTypeMatcher::matches($expected, $type));
            static::assertFalse(ValueTypeMatcher::matches(!$expected, $type));
        }
    }
}
