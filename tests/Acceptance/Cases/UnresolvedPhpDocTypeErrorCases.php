<?php

declare(strict_types=1);

namespace Eventjet\Json\Test\Acceptance\Cases;

use Eventjet\Json\DecodeErrorKind;
use Eventjet\Json\Test\Acceptance\Fixtures\Person;
use Eventjet\Json\Test\Acceptance\Fixtures\StringBackedStatus;
use RuntimeException;

/**
 * A PHPDoc type that the decoder cannot resolve is rejected on every field, even when the JSON member is missing. Static
 * analysis accepts some of these types, such as type aliases, so ignoring them would decode values that the declared
 * type does not allow.
 *
 * @internal
 */
final class UnresolvedPhpDocTypeErrorCases
{
    /**
     * @return iterable<string, array{string, class-string, string, DecodeErrorKind}>
     * @throws RuntimeException
     */
    public static function errors(): iterable
    {
        $status = '\\' . StringBackedStatus::class;
        $person = '\\' . Person::class;
        foreach ([
            ['string', "UnknownLiteralClass|'x'", 'UnknownLiteralClass'],
            ['string', 'Status', 'Status'],
            ['string', "'a'|lowercase-string", 'lowercase-string'],
            ['?string', "'a'|null|T", 'T'],
            ['int', 'int|static', 'static'],
            ['int', 'parent|int', 'parent'],
            ['float', 'float|number', 'number'],
            ['string', 'scalar|string', 'scalar'],
            [$status, $status . '|UnknownStatus', 'UnknownStatus'],
            ['?' . $person, $person . '|UnknownPerson|null', 'UnknownPerson'],
            ['string', 'non-empty-string&lowercase-string', null],
            ['?string', "('a'|'b')|null", null],
            ['string', 'value-of<' . $status . '>', null],
            ['string', 'array{UnknownItem}', null],
        ] as [$native, $declaration, $unresolved]) {
            foreach (['param', 'var'] as $tag) {
                $class = CollectionDeclarationFixture::create($native, $declaration, $tag);
                yield 'unresolved PHPDoc type ' . $declaration . ' on ' . $native . ' in ' . $tag => [
                    '{}',
                    $class,
                    self::message($class, $unresolved),
                    DecodeErrorKind::UnsupportedDeclaration,
                ];
            }
        }
    }

    /** Returns the error for the field `value`, naming the part of its PHPDoc type that cannot be resolved, if any. */
    public static function message(string $class, string|null $unresolved): string
    {
        $problem = $unresolved === null
            ? 'has a PHPDoc type that the decoder cannot read'
            : 'uses ' . $unresolved . ' in its PHPDoc type, which the decoder cannot resolve';
        return (
            'Could not create '
            . $class
            . ' from the JSON object: Field value '
            . $problem
            . '. Use a type that the decoder supports, or remove the PHPDoc type to decode the native type.'
        );
    }
}
