<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeErrorKind;
use ReflectionClass;
use ReflectionProperty;

/**
 * Mapped JSON names reach only public instance properties, in both directions.
 *
 * @internal
 */
final class MappedVisibilityCases
{
    /**
     * @return iterable<string, DecodeSuccess>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function successes(): iterable
    {
        $private = CollectionNameSource::load(
            'MappedPrivateState',
            'use Eventjet\\Json\\Field; final class MappedPrivateState implements JsonSerializable { use Eventjet\\Json\\MappedJsonFields; private string $secret = "private"; protected string $hidden = "protected"; public static string $shared = "static"; #[Field("wire")] public string $value = "public"; }',
        );
        yield 'mapped serialization leaves out non-public and static state' => DecodeSuccess::document(
            '{"wire":"public"}',
            new ReflectionClass($private)->newInstance(),
        );

        $names = CollectionNameSource::load(
            'MappedNonPublicNames',
            'use Eventjet\\Json\\Field; final class MappedNonPublicNames implements JsonSerializable { use Eventjet\\Json\\MappedJsonFields; private string $internal = "private"; public static string $shared = "static"; #[Field("internal")] public string $value = "public"; #[Field("shared")] public string $other = "other"; }',
        );
        $object = new ReflectionClass($names)->newInstance();
        new ReflectionProperty($object, 'value')->setValue($object, 'decoded');
        new ReflectionProperty($object, 'other')->setValue($object, 'also decoded');
        yield 'mapped names may repeat the names of non-public and static properties' => DecodeSuccess::document(
            '{"internal":"decoded","shared":"also decoded"}',
            $object,
        );
    }

    /**
     * @return iterable<string, array{string, class-string, string, DecodeErrorKind}>
     * @throws \RuntimeException
     */
    public static function failures(): iterable
    {
        foreach (['', 'static '] as $modifier) {
            foreach ([1, 2] as $depth) {
                $name = 'PrivateAncestorMapping' . ($modifier === '' ? 'Instance' : 'Static') . $depth;
                $parent = $name . 'Base';
                $source =
                    'use Eventjet\\Json\\Field; class '
                    . $parent
                    . ' { private string $unmapped = ""; #[Field("hidden")] private '
                    . $modifier
                    . 'string $value = "secret"; }';
                if ($depth === 2) {
                    $source .= ' class ' . $name . 'Middle extends ' . $parent . ' {}';
                    $parent = $name . 'Middle';
                }
                $class = CollectionNameSource::load(
                    $name,
                    $source
                    . ' final class '
                    . $name
                    . ' extends '
                    . $parent
                    . ' implements JsonSerializable { use Eventjet\\Json\\MappedJsonFields; #[Field("wire")] public string $value = "public"; }',
                );
                $label = $modifier === '' ? 'private' : 'private static';
                yield 'a ' . $label . ' mapped property ' . $depth . ' ancestors up is rejected' => [
                    '{}',
                    $class,
                    'Could not create '
                        . $class
                        . ' from the JSON object: #[Field] requires a public instance property; value is not one.',
                    DecodeErrorKind::UnsupportedDeclaration,
                ];
            }
        }
    }
}
