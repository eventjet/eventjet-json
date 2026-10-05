<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\Test\Acceptance\Fixtures\ArrayScalarUnionPublicProperty;
use Eventjet\Json\Test\Acceptance\Fixtures\UntypedArrayField;
use RuntimeException;

/** @internal */
final class CollectionDeclarationErrorCases
{
    /**
     * @return iterable<string, array{string, class-string, string, int}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        $untyped = new UntypedArrayField([]);

        foreach (['{}', '{"value":[]}', '{"value":{}}', '{"value":null}'] as $json) {
            yield 'missing constructor declaration ' . $json => [
                $json,
                $untyped::class,
                self::message($untyped::class, 'param'),
                3,
            ];
        }

        $union = new ArrayScalarUnionPublicProperty();

        foreach (['{}', '{"value":"value"}', '{"value":{"answer":42}}', '{"value":[1,2]}'] as $json) {
            yield 'untyped array union ' . $json => [$json, $union::class, self::message($union::class, 'var'), 3];
        }

        foreach (CollectionDeclarationFixture::declarations() as $label => $specification) {
            foreach (['param', 'var'] as $tag) {
                $class = CollectionDeclarationFixture::create($specification[0], $specification[1], $tag);

                foreach (['{}', '{"value":[]}', '{"value":{}}', '{"value":null}', '{"value":[42]}'] as $json) {
                    yield $label . ' ' . $tag . ' ' . $json => [
                        $json,
                        $class,
                        self::message($class, $tag, $specification[2] ?? null),
                        3,
                    ];
                }
            }
        }
    }

    /** @param class-string $class */
    private static function message(string $class, string $tag, string|null $detail = null): string
    {
        $detail ??=
            'Field value has a missing or unrecognized collection declaration. Use @'
            . $tag
            . ' with list<T>, non-empty-list<T>, array{T1, T2}, non-empty-array<string, T>, or ArrayObject<string, T>, where T is a supported scalar, backed enum, or final class.';

        return 'Could not create ' . $class . ' from the JSON object: ' . $detail;
    }
}
