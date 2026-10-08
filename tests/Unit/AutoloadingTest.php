<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Unit;

use Eventjet\Json\Internal\ClassFieldTypeValidator;
use Eventjet\Json\Internal\ConstructorParameter;
use Eventjet\Json\Internal\ConstructorParameters;
use Eventjet\Json\Internal\FieldTypeNameResolver;
use Eventjet\Json\Internal\FieldTypeResolver;
use Eventjet\Json\Internal\FieldTypeValidator;
use Eventjet\Json\Internal\MetadataCache;
use Eventjet\Json\Internal\NamedFieldValueConverter;
use Eventjet\Json\Internal\ObjectTypeValidator;
use Eventjet\Json\Internal\PublicPropertyNamedValueConverter;
use Eventjet\Json\Internal\RootTypeValidator;
use Eventjet\Json\Internal\ValueTypeMatcher;
use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\UnknownClassOrInterfaceException;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use TypeError;

use function array_fill_keys;
use function array_keys;
use function spl_autoload_register;
use function spl_autoload_unregister;

#[CoversClass(NamedFieldValueConverter::class)]
#[CoversClass(ObjectTypeValidator::class)]
#[CoversClass(PublicPropertyNamedValueConverter::class)]
#[UsesClass(ClassFieldTypeValidator::class)]
#[UsesClass(ConstructorParameter::class)]
#[UsesClass(ConstructorParameters::class)]
#[UsesClass(FieldTypeNameResolver::class)]
#[UsesClass(FieldTypeResolver::class)]
#[UsesClass(FieldTypeValidator::class)]
#[UsesClass(MetadataCache::class)]
#[UsesClass(RootTypeValidator::class)]
#[UsesClass(ValueTypeMatcher::class)]
final class AutoloadingTest extends TestCase
{
    /**
     * @throws Exception
     * @throws TypeError
     * @throws UnknownClassOrInterfaceException
     * @throws JsonException
     * @throws ReflectionException
     */
    public function testBuiltinFieldsNeverInvokeAutoloaders(): void
    {
        $target = new class {
            public function __construct(
                public int $integer = 1,
                public float $float = 1.5,
                public string $string = 'value',
                public bool $boolean = true,
                public int|null $nullable = null,
            ) {}
        };
        /** @var array<string, bool|float|int|string|null> $values */
        $values = [
            'integer' => 1,
            'float' => 1.5,
            'string' => 'value',
            'boolean' => true,
            'nullable' => null,
        ];
        $class = new ReflectionClass($target);
        $requests = new class {
            /** @var list<string> */
            public array $names = [];
        };
        $autoload = static function (string $name) use ($requests): void {
            $requests->names[] = $name;
        };
        spl_autoload_register($autoload);

        try {
            static::assertSame(
                array_fill_keys(array_keys($values), value: null),
                ObjectTypeValidator::validate($class, $values, ''),
            );
            foreach ($values as $name => $value) {
                $parameter = new ReflectionParameter([$class->getName(), '__construct'], $name);
                $type = $parameter->getType();
                static::assertInstanceOf(ReflectionNamedType::class, $type);
                static::assertSame($value, NamedFieldValueConverter::convert(
                    $class->getName(),
                    new ConstructorParameter($parameter, $class),
                    $value,
                    $name,
                ));
                $property = $class->getProperty($name);
                static::assertSame(
                    ['property' => $property, 'value' => $value],
                    PublicPropertyNamedValueConverter::convert(
                        $class->getName(),
                        $property,
                        $type,
                        ['collection' => null, 'path' => $name],
                        $value,
                    ),
                );
            }
        } finally {
            spl_autoload_unregister($autoload);
        }

        foreach (['int', 'float', 'string', 'bool'] as $builtin) {
            static::assertNotContains($builtin, $requests->names);
        }
    }
}
