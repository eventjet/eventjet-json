<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\JsonType;
use Eventjet\Json\Test\Acceptance\Fixtures\ScalarFields;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;

use function class_alias;

/**
 * A declaration that names a class that does not exist yet decodes once the class exists.
 *
 * @internal
 */
final class DeferredDependencyCases
{
    private const string SCALARS = '{"string":"a","integer":1,"float":1.5,"boolean":true}';

    /** @return iterable<string, DecodeSuccess> */
    public static function successes(): iterable
    {
        $scalars = new ScalarFields('a', 1, 1.5, true);
        foreach ([
            'a constructor parameter' => ['DeferredParameterClass', '', 'param'],
            'a property' => ['DeferredPropertyClass', '', 'var'],
            'a union member' => ['DeferredUnionClass|int', '', 'var'],
        ] as $label => [$native, $declaration, $tag]) {
            $class = CollectionDeclarationFixture::create($native, $declaration, $tag);
            $dependency = $native === 'DeferredUnionClass|int' ? 'DeferredUnionClass' : $native;
            yield 'a class defined after a failed decode, in ' . $label => DecodeSuccess::afterDefining(
                '{"value":' . self::SCALARS . '}',
                static fn(): string => $class,
                /** @throws \ReflectionException */
                static fn(): object => $tag === 'param'
                    ? new ReflectionClass($class)->newInstance($scalars)
                    : self::withValue($class, $scalars),
                self::alias(ScalarFields::class, $dependency),
            );
        }
        $enumUnion = CollectionDeclarationFixture::create('DeferredUnionEnum|int', '', 'var');
        yield 'an enum defined after a failed decode, in a union member' => DecodeSuccess::afterDefining(
            '{"value":"ready"}',
            static fn(): string => $enumUnion,
            /** @throws \ReflectionException */
            static fn(): object => self::withValue($enumUnion, StringBackedStatus::Ready),
            self::alias(StringBackedStatus::class, 'DeferredUnionEnum'),
        );
        yield 'a root list item class defined after a failed decode' => DecodeSuccess::afterDefining(
            '[' . self::SCALARS . ']',
            /** @return JsonType<list<object>> */
            static fn(): JsonType => JsonType::array(self::className('DeferredRootItem')),
            /** @return list<ScalarFields> */
            static fn(): array => [$scalars],
            self::alias(ScalarFields::class, 'DeferredRootItem'),
        );
    }

    /**
     * @param class-string $class
     * @throws \ReflectionException
     */
    private static function withValue(string $class, mixed $value): object
    {
        $object = new ReflectionClass($class)->newInstanceWithoutConstructor();
        new ReflectionProperty($class, 'value')->setValue($object, $value);
        return $object;
    }

    /** @return class-string */
    private static function className(string $name): string
    {
        /** @var class-string */
        return $name;
    }

    /**
     * @param class-string $class
     * @param non-empty-string $alias
     * @return \Closure(): void
     */
    private static function alias(string $class, string $alias): \Closure
    {
        return (
            /** @throws RuntimeException */
            static function () use ($class, $alias): void {
                $defined = class_alias($class, $alias);
                if (!$defined) {
                    throw new RuntimeException('Could not define ' . $alias . '.');
                }
            }
        );
    }
}
