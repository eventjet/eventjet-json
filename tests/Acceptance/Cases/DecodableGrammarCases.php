<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeErrorKind;

/**
 * Collection declarations from {@see DecodableGrammarSource}. The value must round-trip, and a value of the wrong type
 * must make the decoder print the declaration without the formatting.
 *
 * @internal
 */
final class DecodableGrammarCases
{
    /**
     * @return iterable<string, DecodeSuccess>
     * @throws \ReflectionException
     * @throws \RuntimeException
     */
    public static function successes(): iterable
    {
        foreach (DecodableGrammarSource::generated() as $label => [$source, , $value, $tag]) {
            yield $label => DecodeSuccess::roundTrip(CollectionDeclarationFixture::object(
                'array',
                $source,
                $tag,
                $value,
            ));
        }
    }

    /** @return iterable<string, array{string, class-string, string, DecodeErrorKind}> */
    public static function failures(): iterable
    {
        foreach (DecodableGrammarSource::generated() as $label => [$source, $printed, , $tag]) {
            $class = CollectionDeclarationFixture::create('array', $source, $tag);
            yield $label . ' is printed without formatting' => [
                '{"value":7}',
                $class,
                'Could not create '
                    . $class
                    . ' from the JSON object: Field value must be of type '
                    . $printed
                    . ', int given.',
                DecodeErrorKind::InvalidValue,
            ];
        }
    }
}
